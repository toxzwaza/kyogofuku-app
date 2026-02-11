<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Slideshow;
use App\Models\SlideshowImage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Intervention\Image\ImageManager;
use Intervention\Image\Drivers\Gd\Driver as GdDriver;
use Intervention\Image\Drivers\Imagick\Driver as ImagickDriver;

class SlideshowController extends Controller
{
    /**
     * スライドショー一覧を表示
     */
    public function index()
    {
        $slideshows = Slideshow::with('images')->orderBy('created_at', 'desc')->get();

        return Inertia::render('Admin/Slideshow/Index', [
            'slideshows' => $slideshows,
        ]);
    }

    /**
     * スライドショー作成フォームを表示
     */
    public function create()
    {
        return Inertia::render('Admin/Slideshow/Create');
    }

    /**
     * スライドショー詳細を表示
     */
    public function show(Slideshow $slideshow)
    {
        $slideshow->load('images');
        $slideshowForInertia = $slideshow->toArray();
        $slideshowForInertia['images'] = $slideshow->images->map(function ($image) {
            $item = $image->toArray();
            $item['url'] = $image->url;
            return $item;
        })->values()->all();

        return Inertia::render('Admin/Slideshow/Show', [
            'slideshow' => $slideshowForInertia,
        ]);
    }

    /**
     * スライドショーを保存
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'type' => 'nullable|string|in:fade,slide,cube,coverflow',
            'autoplay_interval' => 'nullable|integer|min:1000|max:60000',
            'autoplay_enabled' => 'nullable|boolean',
            'fullscreen' => 'nullable|boolean',
        ]);

        $slideshow = Slideshow::create($validated);

        return redirect()->route('admin.slideshows.show', $slideshow->id)
            ->with('success', 'スライドショーを作成しました。');
    }

    /**
     * スライドショーを更新
     */
    public function update(Request $request, Slideshow $slideshow)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'type' => 'nullable|string|in:fade,slide,cube,coverflow',
            'autoplay_interval' => 'nullable|integer|min:1000|max:60000',
            'autoplay_enabled' => 'nullable|boolean',
            'fullscreen' => 'nullable|boolean',
        ]);

        $slideshow->update($validated);

        return redirect()->back()
            ->with('success', 'スライドショーを更新しました。');
    }

    /**
     * スライドショーを削除
     */
    public function destroy(Slideshow $slideshow)
    {
        $slideshow->load('images');
        foreach ($slideshow->images as $image) {
            if ($image->path) {
                $disk = ($image->storage_disk ?? 'public') === 's3' ? 's3_public' : 'public';
                $path = $disk === 's3_public' ? str_replace('\\', '/', $image->path) : $image->path;
                if (Storage::disk($disk)->exists($path)) {
                    Storage::disk($disk)->delete($path);
                }
            }
        }

        $slideshow->delete();

        return redirect()->route('admin.slideshows.index')
            ->with('success', 'スライドショーを削除しました。');
    }

    /**
     * スライドショー画像を追加（WebP に変換して S3 public/slideshows/ にのみ保存）
     */
    public function storeImage(Request $request, Slideshow $slideshow)
    {
        $validated = $request->validate([
            'images' => 'required|array|min:1',
            'images.*' => 'image|mimes:jpeg,png,jpg,gif|max:10240',
            'alt' => 'nullable|string|max:255',
        ]);

        $manager = $this->createImageManager();
        if (! $manager) {
            return redirect()->route('admin.slideshows.show', $slideshow->id)
                ->with('error', 'WebP変換に必要な画像ドライバー（GD/Imagick）が利用できません。');
        }

        $maxSortOrder = $slideshow->images()->max('sort_order') ?? 0;

        foreach ($request->file('images') as $index => $file) {
            $webpPath = $this->convertUploadToWebpAndPutS3($file, (int) $slideshow->id, $manager);
            if (! $webpPath) {
                return redirect()->route('admin.slideshows.show', $slideshow->id)
                    ->with('error', '画像の WebP 変換に失敗しました。');
            }

            SlideshowImage::create([
                'slideshow_id' => $slideshow->id,
                'path' => $webpPath,
                'storage_disk' => 's3',
                'alt' => $request->alt ?? null,
                'sort_order' => $maxSortOrder + $index + 1,
            ]);
        }

        return redirect()->route('admin.slideshows.show', $slideshow->id)
            ->with('success', '画像を追加しました。');
    }

    /**
     * 利用可能なドライバーでImageManagerを作成
     */
    private function createImageManager()
    {
        if (extension_loaded('gd') && function_exists('imagecreatetruecolor')) {
            try {
                return new ImageManager(new GdDriver());
            } catch (\Exception $e) {
                Log::warning('GDドライバーの初期化に失敗: ' . $e->getMessage());
            }
        }
        if (extension_loaded('imagick')) {
            try {
                return new ImageManager(new ImagickDriver());
            } catch (\Exception $e) {
                Log::warning('Imagickドライバーの初期化に失敗: ' . $e->getMessage());
            }
        }
        Log::warning('画像処理ドライバー（GD/Imagick）が利用できません。');
        return null;
    }

    /**
     * アップロードファイルを WebP に変換して S3（s3_public）に保存（public/slideshows/ 配下）
     * @return string|null 保存した WebP のパス（slideshows/{id}/{unique}.webp）、失敗時は null
     */
    private function convertUploadToWebpAndPutS3($uploadedFile, int $slideshowId, $manager)
    {
        if (! $manager) {
            return null;
        }
        try {
            $webpPath = 'slideshows/' . $slideshowId . '/' . Str::random(40) . '.webp';
            $image = $manager->read($uploadedFile->getRealPath());
            $tmpPath = tempnam(sys_get_temp_dir(), 'webp');
            $image->toWebp(80)->save($tmpPath);
            $content = file_get_contents($tmpPath);
            @unlink($tmpPath);
            Storage::disk('s3_public')->put($webpPath, $content);
            return $webpPath;
        } catch (\Exception $e) {
            Log::error('WebP変換エラー (S3 slideshows/' . $slideshowId . '): ' . $e->getMessage());
            return null;
        }
    }

    /**
     * スライドショー画像を S3 に移行（public → s3_public、WebP に変換して保存）
     */
    public function migrateSlideshowImageToS3(Slideshow $slideshow, SlideshowImage $image)
    {
        if ($image->slideshow_id !== $slideshow->id) {
            abort(404);
        }
        if (($image->storage_disk ?? 'public') === 's3') {
            return redirect()->route('admin.slideshows.show', $slideshow->id)
                ->with('info', 'この画像は既に S3 に保存されています。');
        }

        if (! Storage::disk('public')->exists($image->path)) {
            return redirect()->route('admin.slideshows.show', $slideshow->id)
                ->with('error', '元のファイルが見つかりません。');
        }

        $manager = $this->createImageManager();
        if (! $manager) {
            return redirect()->route('admin.slideshows.show', $slideshow->id)
                ->with('error', 'WebP変換に必要な画像ドライバーが利用できません。');
        }

        try {
            $content = Storage::disk('public')->get($image->path);
            $pathInfo = pathinfo($image->path);
            $webpPath = 'slideshows/' . $slideshow->id . '/' . Str::random(40) . '.webp';
            $tmpPath = tempnam(sys_get_temp_dir(), 'img');
            file_put_contents($tmpPath, $content);
            $img = $manager->read($tmpPath);
            $webpTmp = tempnam(sys_get_temp_dir(), 'webp');
            $img->toWebp(80)->save($webpTmp);
            $webpContent = file_get_contents($webpTmp);
            @unlink($tmpPath);
            @unlink($webpTmp);
            Storage::disk('s3_public')->put($webpPath, $webpContent);
            $image->update(['storage_disk' => 's3', 'path' => $webpPath]);
            Storage::disk('public')->delete($image->path);
        } catch (\Exception $e) {
            Log::error('スライドショー画像 S3 移行エラー: ' . $e->getMessage());
            return redirect()->route('admin.slideshows.show', $slideshow->id)
                ->with('error', 'S3 への移行に失敗しました。');
        }

        return redirect()->route('admin.slideshows.show', $slideshow->id)
            ->with('success', '画像を S3 に移行しました。');
    }

    /**
     * スライドショー画像を削除
     */
    public function destroyImage(SlideshowImage $image)
    {
        $slideshowId = $image->slideshow_id;
        $disk = ($image->storage_disk ?? 'public') === 's3' ? 's3_public' : 'public';
        $path = $disk === 's3_public' ? str_replace('\\', '/', $image->path) : $image->path;
        if ($image->path && Storage::disk($disk)->exists($path)) {
            Storage::disk($disk)->delete($path);
        }
        $image->delete();

        return redirect()->route('admin.slideshows.show', $slideshowId)
            ->with('success', '画像を削除しました。');
    }

    /**
     * スライドショー画像を一括削除
     */
    public function destroyBulk(Request $request, Slideshow $slideshow)
    {
        $validated = $request->validate([
            'image_ids' => 'required|array|min:1',
            'image_ids.*' => 'integer|exists:slideshow_images,id',
        ]);

        $images = SlideshowImage::where('slideshow_id', $slideshow->id)
            ->whereIn('id', $validated['image_ids'])
            ->get();

        foreach ($images as $image) {
            $disk = ($image->storage_disk ?? 'public') === 's3' ? 's3_public' : 'public';
            $path = $disk === 's3_public' ? str_replace('\\', '/', $image->path) : $image->path;
            if ($image->path && Storage::disk($disk)->exists($path)) {
                Storage::disk($disk)->delete($path);
            }
            $image->delete();
        }

        $count = $images->count();

        return redirect()->route('admin.slideshows.show', $slideshow->id)
            ->with('success', "{$count}件の画像を削除しました。");
    }

    /**
     * スライドショー画像のソート順を更新
     */
    public function updateImageSortOrder(Request $request, Slideshow $slideshow)
    {
        $validated = $request->validate([
            'image_ids' => 'required|array',
            'image_ids.*' => 'exists:slideshow_images,id',
        ]);

        foreach ($validated['image_ids'] as $index => $imageId) {
            SlideshowImage::where('id', $imageId)
                ->where('slideshow_id', $slideshow->id)
                ->update(['sort_order' => $index + 1]);
        }

        return redirect()->route('admin.slideshows.show', $slideshow->id)
            ->with('success', 'ソート順を更新しました。');
    }
}
