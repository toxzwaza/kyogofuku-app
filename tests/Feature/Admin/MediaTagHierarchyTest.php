<?php

namespace Tests\Feature\Admin;

use App\Http\Controllers\Admin\MediaFileController;
use App\Http\Controllers\Admin\MediaTagController;
use App\Models\MediaFile;
use App\Models\MediaTag;
use Illuminate\Http\Request;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * 階層タグのロジック検証。
 * この環境ではHTTP経由の認証（actingAs）がローカルで安定しないため、
 * コントローラ・モデルを直接呼び出して検証する。
 */
class MediaTagHierarchyTest extends TestCase
{
    use RefreshDatabase;

    private function media(string $name): MediaFile
    {
        return MediaFile::create([
            'original_filename' => $name,
            'path' => 'media/'.$name.'.webp',
            'storage_disk' => 's3',
            'mime_type' => 'image/webp',
        ]);
    }

    public function test_filter_by_ancestor_includes_descendant_tagged_media(): void
    {
        // 2026 > イベント > イベント1
        $y = MediaTag::create(['name' => '2026', 'parent_id' => null]);
        $e = MediaTag::create(['name' => 'イベント', 'parent_id' => $y->id]);
        $e1 = MediaTag::create(['name' => 'イベント1', 'parent_id' => $e->id]);

        $hit = $this->media('hit');
        $hit->mediaTags()->sync([$e1->id]);
        $miss = $this->media('miss');

        // コントローラの絞り込みと同じロジック
        $allTags = MediaTag::all();
        $ids = $y->selfAndDescendantIds($allTags);
        $result = MediaFile::whereHas('mediaTags', fn ($q) => $q->whereIn('media_tags.id', $ids))
            ->pluck('id')->all();

        $this->assertContains($hit->id, $result);
        $this->assertNotContains($miss->id, $result);
        $this->assertEqualsCanonicalizing([$y->id, $e->id, $e1->id], $ids);
    }

    public function test_full_path_builds_breadcrumb(): void
    {
        $y = MediaTag::create(['name' => '2026', 'parent_id' => null]);
        $e = MediaTag::create(['name' => 'イベント', 'parent_id' => $y->id]);
        $e1 = MediaTag::create(['name' => 'イベント1', 'parent_id' => $e->id]);

        $this->assertSame('2026 > イベント > イベント1', $e1->fullPath());
    }

    public function test_can_create_root_and_child_tag(): void
    {
        $controller = new MediaTagController();

        $rootResp = $controller->store(Request::create('/', 'POST', ['name' => '2026']));
        $rootId = $rootResp->getData(true)['tag']['id'];

        $controller->store(Request::create('/', 'POST', ['name' => 'イベント', 'parent_id' => $rootId]));

        $this->assertDatabaseHas('media_tags', ['name' => '2026', 'parent_id' => null]);
        $this->assertDatabaseHas('media_tags', ['name' => 'イベント', 'parent_id' => $rootId]);
    }

    public function test_duplicate_sibling_name_is_rejected(): void
    {
        MediaTag::create(['name' => '2026', 'parent_id' => null]);

        $this->expectException(ValidationException::class);
        (new MediaTagController())->store(Request::create('/', 'POST', ['name' => '2026']));
    }

    public function test_cannot_move_tag_under_its_own_descendant(): void
    {
        $y = MediaTag::create(['name' => '2026', 'parent_id' => null]);
        $e = MediaTag::create(['name' => 'イベント', 'parent_id' => $y->id]);

        $this->expectException(ValidationException::class);
        (new MediaTagController())->update(
            Request::create('/', 'PATCH', ['name' => '2026', 'parent_id' => $e->id]),
            $y
        );
    }

    public function test_delete_cascades_descendants_and_pivot(): void
    {
        $y = MediaTag::create(['name' => '2026', 'parent_id' => null]);
        $e = MediaTag::create(['name' => 'イベント', 'parent_id' => $y->id]);
        $media = $this->media('m');
        $media->mediaTags()->sync([$e->id]);

        (new MediaTagController())->destroy($y);

        $this->assertDatabaseMissing('media_tags', ['id' => $y->id]);
        $this->assertDatabaseMissing('media_tags', ['id' => $e->id]);
        $this->assertDatabaseMissing('media_file_tag', ['media_file_id' => $media->id, 'media_tag_id' => $e->id]);
    }

    public function test_bulk_tag_attaches_to_all_selected(): void
    {
        $tag = MediaTag::create(['name' => 'まとめ', 'parent_id' => null]);
        $existing = MediaTag::create(['name' => '既存', 'parent_id' => null]);
        $m1 = $this->media('m1');
        $m2 = $this->media('m2');
        $m1->mediaTags()->sync([$existing->id]); // 既存タグは維持されること

        (new MediaFileController())->bulkTag(Request::create('/', 'POST', [
            'media_ids' => [$m1->id, $m2->id],
            'tag_ids' => [$tag->id],
        ]));

        $this->assertEqualsCanonicalizing([$existing->id, $tag->id], $m1->fresh()->mediaTags->pluck('id')->all());
        $this->assertEqualsCanonicalizing([$tag->id], $m2->fresh()->mediaTags->pluck('id')->all());
    }

    public function test_bulk_delete_removes_unused_and_skips_in_use(): void
    {
        \Illuminate\Support\Facades\Storage::fake('s3_public');

        $free = $this->media('free');
        $used = $this->media('used');
        // 使用中にする（イベント画像から参照）
        \App\Models\EventImage::create([
            'event_id' => \App\Models\Event::create([
                'slug' => 'ev', 'title' => 'E', 'form_type' => 'reservation', 'is_public' => true,
            ])->id,
            'media_file_id' => $used->id,
            'path' => $used->path,
            'storage_disk' => 's3',
        ]);

        (new MediaFileController())->bulkDestroy(Request::create('/', 'POST', [
            'media_ids' => [$free->id, $used->id],
        ]));

        $this->assertDatabaseMissing('media_files', ['id' => $free->id]);
        $this->assertDatabaseHas('media_files', ['id' => $used->id]); // 使用中はスキップ
    }

    public function test_update_media_syncs_tags(): void
    {
        $tagA = MediaTag::create(['name' => 'A', 'parent_id' => null]);
        $tagB = MediaTag::create(['name' => 'B', 'parent_id' => null]);
        $media = $this->media('m');
        $media->mediaTags()->sync([$tagA->id]);

        (new MediaFileController())->update(
            Request::create('/', 'PATCH', ['alt' => 'updated', 'tag_ids' => [$tagB->id]]),
            $media
        );

        $this->assertEqualsCanonicalizing([$tagB->id], $media->fresh()->mediaTags->pluck('id')->all());
        $this->assertSame('updated', $media->fresh()->alt);
    }
}
