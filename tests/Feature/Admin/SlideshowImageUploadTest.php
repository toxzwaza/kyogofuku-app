<?php

namespace Tests\Feature\Admin;

use App\Models\Slideshow;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * スライドショー画像の追加：WebP（ブランド用に作ったスライド画像）も受け付け、
 * 選んだ順に sort_order が付くこと。
 */
class SlideshowImageUploadTest extends TestCase
{
    use RefreshDatabase;

    private function webp(string $name, int $w = 40, int $h = 60): UploadedFile
    {
        $img = imagecreatetruecolor($w, $h);
        imagefill($img, 0, 0, imagecolorallocate($img, 200, 30, 60));
        $tmp = tempnam(sys_get_temp_dir(), 'wp');
        imagewebp($img, $tmp, 80);
        imagedestroy($img);

        return new UploadedFile($tmp, $name, 'image/webp', null, true);
    }

    public function test_webp画像を追加でき_選んだ順に並ぶ(): void
    {
        Storage::fake('s3_public');
        $user = User::factory()->create();
        $slideshow = Slideshow::create(['name' => '平田 ブランド左流れ', 'type' => 'marquee']);

        $res = $this->actingAs($user)->post(route('admin.slideshows.images.store', $slideshow), [
            'images' => [$this->webp('01_AS-0026_allure.webp'), $this->webp('02_AS-0126_yoshiki.webp')],
        ]);

        $res->assertRedirect(route('admin.slideshows.show', $slideshow->id));
        $res->assertSessionHasNoErrors();
        $images = $slideshow->images()->orderBy('sort_order')->get();
        $this->assertCount(2, $images);
        $this->assertSame([1, 2], $images->pluck('sort_order')->all());
        foreach ($images as $image) {
            $this->assertSame('s3', $image->storage_disk);
            $this->assertStringEndsWith('.webp', $image->path);
            Storage::disk('s3_public')->assertExists($image->path);
        }
    }

    public function test_画像以外は受け付けない(): void
    {
        Storage::fake('s3_public');
        $user = User::factory()->create();
        $slideshow = Slideshow::create(['name' => 'テスト', 'type' => 'marquee']);

        $res = $this->actingAs($user)->post(route('admin.slideshows.images.store', $slideshow), [
            'images' => [UploadedFile::fake()->create('memo.pdf', 10, 'application/pdf')],
        ]);

        $res->assertSessionHasErrors('images.0');
        $this->assertSame(0, $slideshow->images()->count());
    }
}
