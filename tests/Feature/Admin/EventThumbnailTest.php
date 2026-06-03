<?php

namespace Tests\Feature\Admin;

use App\Models\Event;
use App\Models\MediaFile;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EventThumbnailTest extends TestCase
{
    use RefreshDatabase;

    private function adminUser(): User
    {
        $user = User::factory()->create();
        $shop = Shop::create([
            'name' => 'テスト店',
            'is_active' => true,
        ]);
        $user->shops()->attach($shop->id);
        return $user;
    }

    public function test_thumbnail_url_accessor_returns_null_when_path_missing(): void
    {
        $event = Event::create([
            'slug' => 'thumb-null',
            'title' => 'テスト',
            'form_type' => 'reservation',
            'is_public' => true,
        ]);

        $this->assertNull($event->thumbnail_url);
    }

    public function test_thumbnail_url_accessor_returns_local_storage_url(): void
    {
        $event = Event::create([
            'slug' => 'thumb-public',
            'title' => 'テスト',
            'form_type' => 'reservation',
            'is_public' => true,
            'thumbnail_path' => 'media/abc.webp',
            'thumbnail_storage_disk' => 'public',
        ]);

        $this->assertNotNull($event->thumbnail_url);
        $this->assertStringContainsString('media/abc.webp', $event->thumbnail_url);
    }

    public function test_thumbnail_url_accessor_returns_external_url_as_is(): void
    {
        $event = Event::create([
            'slug' => 'thumb-ext',
            'title' => 'テスト',
            'form_type' => 'reservation',
            'is_public' => true,
            'thumbnail_path' => 'https://cdn.example.com/x.webp',
        ]);

        $this->assertSame('https://cdn.example.com/x.webp', $event->thumbnail_url);
    }

    public function test_event_update_persists_thumbnail_fields(): void
    {
        $user = $this->adminUser();
        $event = Event::create([
            'slug' => 'thumb-update',
            'title' => '元タイトル',
            'form_type' => 'reservation',
            'is_public' => true,
        ]);

        $media = MediaFile::create([
            'original_filename' => 'test.webp',
            'path' => 'media/uploaded/test.webp',
            'storage_disk' => 'public',
            'mime_type' => 'image/webp',
        ]);

        $this->actingAs($user)
            ->put(route('admin.events.update', $event), [
                'title' => '元タイトル',
                'slug' => $event->slug,
                'form_type' => 'reservation',
                'is_public' => true,
                'thumbnail_media_file_id' => $media->id,
                'thumbnail_path' => $media->path,
                'thumbnail_storage_disk' => 'public',
            ])
            ->assertRedirect();

        $event->refresh();
        $this->assertSame($media->id, $event->thumbnail_media_file_id);
        $this->assertSame('media/uploaded/test.webp', $event->thumbnail_path);
        $this->assertSame('public', $event->thumbnail_storage_disk);
    }

    public function test_event_update_clears_thumbnail_when_null_passed(): void
    {
        $user = $this->adminUser();
        $media = MediaFile::create([
            'original_filename' => 'init.webp',
            'path' => 'media/init.webp',
            'storage_disk' => 'public',
            'mime_type' => 'image/webp',
        ]);
        $event = Event::create([
            'slug' => 'thumb-clear',
            'title' => 'x',
            'form_type' => 'reservation',
            'is_public' => true,
            'thumbnail_media_file_id' => $media->id,
            'thumbnail_path' => $media->path,
            'thumbnail_storage_disk' => 'public',
        ]);

        $this->actingAs($user)
            ->put(route('admin.events.update', $event), [
                'title' => 'x',
                'slug' => $event->slug,
                'form_type' => 'reservation',
                'is_public' => true,
                'thumbnail_media_file_id' => null,
                'thumbnail_path' => null,
                'thumbnail_storage_disk' => 'public',
            ])
            ->assertRedirect();

        $event->refresh();
        $this->assertNull($event->thumbnail_media_file_id);
        $this->assertNull($event->thumbnail_path);
    }
}
