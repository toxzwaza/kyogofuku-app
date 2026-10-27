<?php

namespace Tests\Feature\Admin;

use App\Models\Event;
use App\Models\GtmTag;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GtmTagTest extends TestCase
{
    use RefreshDatabase;

    private Shop $shop;

    private function user(?string $role = 'manager'): User
    {
        $user = User::factory()->create(['attendance_role' => $role]);
        $this->shop ??= Shop::create(['name' => 'テスト店', 'is_active' => true]);
        $user->shops()->attach($this->shop->id);

        return $user;
    }

    private function event(array $attrs = []): Event
    {
        return Event::create(array_merge([
            'slug' => 'gtm-'.uniqid(),
            'title' => 'テスト',
            'form_type' => 'reservation',
            'is_public' => true,
        ], $attrs));
    }

    public function test_manager_can_create_tag_with_normalized_id_and_shops(): void
    {
        $user = $this->user();

        $this->actingAs($user)->post(route('admin.gtm-tags.store'), [
            'company_name' => 'BE FANG',
            'gtm_id' => ' gtm-abc1234 ',
            'memo' => 'LP計測用',
            'is_active' => true,
            'shop_ids' => [$this->shop->id],
        ])->assertRedirect(route('admin.gtm-tags.index'));

        $tag = GtmTag::firstOrFail();
        $this->assertSame('GTM-ABC1234', $tag->gtm_id);
        $this->assertSame([$this->shop->id], $tag->shops->pluck('id')->all());
    }

    public function test_invalid_or_duplicate_gtm_id_is_rejected(): void
    {
        $user = $this->user();
        GtmTag::create(['gtm_id' => 'GTM-DUP1234']);

        $this->actingAs($user)->post(route('admin.gtm-tags.store'), ['gtm_id' => 'UA-12345'])
            ->assertSessionHasErrors('gtm_id');
        $this->actingAs($user)->post(route('admin.gtm-tags.store'), ['gtm_id' => 'gtm-dup1234'])
            ->assertSessionHasErrors('gtm_id');
        $this->assertSame(1, GtmTag::count());
    }

    public function test_non_manager_cannot_manage_tags(): void
    {
        $user = $this->user(null);

        $this->actingAs($user)->get(route('admin.gtm-tags.index'))->assertForbidden();
        $this->actingAs($user)->post(route('admin.gtm-tags.store'), ['gtm_id' => 'GTM-ABC1234'])->assertForbidden();
    }

    public function test_event_update_copies_gtm_id_from_selected_tag(): void
    {
        $user = $this->user();
        $tag = GtmTag::create(['gtm_id' => 'GTM-SEL1234']);
        $event = $this->event(['gtm_id' => 'GTM-OLD0000']);

        $this->actingAs($user)->put(route('admin.events.update', $event), [
            'title' => 'テスト',
            'slug' => $event->slug,
            'form_type' => 'reservation',
            'is_public' => true,
            'gtm_tag_id' => $tag->id,
        ])->assertRedirect();

        $event->refresh();
        $this->assertSame($tag->id, $event->gtm_tag_id);
        $this->assertSame('GTM-SEL1234', $event->gtm_id);

        // 「設定しない」に戻すとLP出力値も消える
        $this->actingAs($user)->put(route('admin.events.update', $event), [
            'title' => 'テスト',
            'slug' => $event->slug,
            'form_type' => 'reservation',
            'is_public' => true,
            'gtm_tag_id' => null,
        ])->assertRedirect();

        $event->refresh();
        $this->assertNull($event->gtm_tag_id);
        $this->assertNull($event->gtm_id);
    }

    public function test_changing_tag_id_updates_linked_events(): void
    {
        $user = $this->user();
        $tag = GtmTag::create(['gtm_id' => 'GTM-BEFORE1']);
        $linked = $this->event(['gtm_tag_id' => $tag->id, 'gtm_id' => 'GTM-BEFORE1']);
        $other = $this->event(['gtm_id' => 'GTM-OTHER11']);

        $this->actingAs($user)->put(route('admin.gtm-tags.update', $tag), [
            'gtm_id' => 'GTM-AFTER11',
            'is_active' => true,
        ])->assertRedirect(route('admin.gtm-tags.index'));

        $this->assertSame('GTM-AFTER11', $linked->fresh()->gtm_id);
        $this->assertSame('GTM-OTHER11', $other->fresh()->gtm_id);
    }

    public function test_update_without_gtm_tag_id_keeps_existing_gtm_id(): void
    {
        $user = $this->user();
        $tag = GtmTag::create(['gtm_id' => 'GTM-KEEP123']);
        $event = $this->event(['gtm_tag_id' => $tag->id, 'gtm_id' => 'GTM-KEEP123']);

        // 旧画面のように gtm_tag_id を送らない保存
        $this->actingAs($user)->put(route('admin.events.update', $event), [
            'title' => '変更後',
            'slug' => $event->slug,
            'form_type' => 'reservation',
            'is_public' => true,
            'gtm_id' => 'GTM-OLDFORM',
        ])->assertRedirect();

        $event->refresh();
        $this->assertSame('変更後', $event->title);
        $this->assertSame($tag->id, $event->gtm_tag_id);
        $this->assertSame('GTM-KEEP123', $event->gtm_id);
    }

    public function test_changing_tag_id_also_fills_linked_events_with_null_gtm_id(): void
    {
        $user = $this->user();
        $tag = GtmTag::create(['gtm_id' => 'GTM-BEFORE2']);
        $linked = $this->event(['gtm_tag_id' => $tag->id, 'gtm_id' => null]);

        $this->actingAs($user)->put(route('admin.gtm-tags.update', $tag), [
            'gtm_id' => 'GTM-AFTER22',
            'is_active' => true,
        ])->assertRedirect(route('admin.gtm-tags.index'));

        $this->assertSame('GTM-AFTER22', $linked->fresh()->gtm_id);
    }

    public function test_options_include_inactive_tags_with_flag(): void
    {
        GtmTag::create(['gtm_id' => 'GTM-ACTIVE1']);
        GtmTag::create(['gtm_id' => 'GTM-STOPPED', 'is_active' => false]);

        $flags = GtmTag::options()->pluck('is_active', 'gtm_id')->all();
        $this->assertSame(['GTM-ACTIVE1' => true, 'GTM-STOPPED' => false], $flags);
    }

    public function test_tag_in_use_cannot_be_deleted(): void
    {
        $user = $this->user();
        $tag = GtmTag::create(['gtm_id' => 'GTM-INUSE12']);
        $this->event(['gtm_tag_id' => $tag->id, 'gtm_id' => 'GTM-INUSE12']);

        $this->actingAs($user)->delete(route('admin.gtm-tags.destroy', $tag))
            ->assertSessionHas('error');
        $this->assertNotNull($tag->fresh());

        $unused = GtmTag::create(['gtm_id' => 'GTM-UNUSED1']);
        $this->actingAs($user)->delete(route('admin.gtm-tags.destroy', $unused))
            ->assertSessionHas('success');
        $this->assertNull($unused->fresh());
    }
}
