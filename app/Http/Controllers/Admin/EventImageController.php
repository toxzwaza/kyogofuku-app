<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\EventImage;
use App\Models\Slideshow;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Intervention\Image\ImageManager;
use Intervention\Image\Drivers\Gd\Driver as GdDriver;
use Intervention\Image\Drivers\Imagick\Driver as ImagickDriver;

class EventImageController extends Controller
{
    /**
     * イベント画像一覧を表示
     */
    public function index(Event $event)
    {
        $images = $event->images()->orderBy('sort_order')->get()->map(function ($image) {
            return [
                'id' => $image->id,
                'path' => $image->path,
                'url' => $image->url,
                'webp_path' => $image->webp_path,
                'webp_url' => $image->webp_url,
                'alt' => $image->alt,
                'sort_order' => $image->sort_order,
                'file_format' => strtoupper(pathinfo($image->path, PATHINFO_EXTENSION) ?: '-'),
                'margin_top_px' => $image->margin_top_px,
                'margin_bottom_px' => $image->margin_bottom_px,
            ];
        })->values();
        $slideshows = Slideshow::orderBy('created_at', 'desc')->get();
        
        // 複数のスライドショーに対応した位置情報を取得
        $slideshowPositions = DB::table('event_slideshow_positions')
            ->where('event_id', $event->id)
            ->orderBy('position')
            ->orderBy('sort_order')
            ->get()
            ->groupBy('position')
            ->map(function ($items) {
                return $items->map(function ($item) {
                    return [
                        'slideshow_id' => $item->slideshow_id,
                        'sort_order' => $item->sort_order,
                    ];
                })->toArray();
            })
            ->toArray();

        // CTAボタン表示位置
        $ctaButtonPositions = DB::table('event_cta_button_positions')
            ->where('event_id', $event->id)
            ->orderBy('position')
            ->pluck('position')
            ->values()
            ->toArray();

        return Inertia::render('Admin/EventImage/Index', [
            'event' => $event,
            'images' => $images,
            'slideshows' => $slideshows,
            'slideshowPositions' => $slideshowPositions,
            'ctaButtonPositions' => $ctaButtonPositions,
        ]);
    }

    /**
     * イベント画像追加フォームを表示
     */
    public function create(Event $event)
    {
        return Inertia::render('Admin/EventImage/Create', [
            'event' => $event,
        ]);
    }

    /**
     * イベント画像を保存
     */
    public function store(Request $request, Event $event)
    {
        $validated = $request->validate([
            'images' => 'required|array|min:1',
            'images.*' => 'image|mimes:jpeg,png,jpg,gif|max:10240',
            'alt' => 'nullable|string|max:255',
        ]);

        $maxSortOrder = $event->images()->max('sort_order') ?? 0;

        $manager = $this->createImageManager();
        if (!$manager) {
            return redirect()->route('admin.events.images.create', $event->id)
                ->with('error', 'WebP変換に必要な画像ドライバー（GD/Imagick）が利用できません。');
        }

        foreach ($request->file('images') as $index => $file) {
            // S3 には WebP のみ保存（元の JPEG 等は保存しない）
            $webpPath = $this->convertUploadToWebpAndPutS3($file, (int) $event->id, $manager);
            if (!$webpPath) {
                return redirect()->route('admin.events.images.create', $event->id)
                    ->with('error', '画像の WebP 変換に失敗しました。');
            }

            EventImage::create([
                'event_id' => $event->id,
                'path' => $webpPath,
                'storage_disk' => 's3',
                'webp_path' => null,
                'alt' => $request->alt ?? null,
                'sort_order' => $maxSortOrder + $index + 1,
            ]);
        }

        return redirect()->route('admin.events.images.index', $event->id)
            ->with('success', '画像を追加しました。');
    }

    /**
     * 利用可能なドライバーでImageManagerを作成
     */
    private function createImageManager()
    {
        // GD拡張機能が利用可能かチェック
        if (extension_loaded('gd') && function_exists('imagecreatetruecolor')) {
            try {
                return new ImageManager(new GdDriver());
            } catch (\Exception $e) {
                \Log::warning("GDドライバーの初期化に失敗: " . $e->getMessage());
            }
        }
        
        // Imagick拡張機能が利用可能かチェック
        if (extension_loaded('imagick')) {
            try {
                return new ImageManager(new ImagickDriver());
            } catch (\Exception $e) {
                \Log::warning("Imagickドライバーの初期化に失敗: " . $e->getMessage());
            }
        }
        
        // どちらのドライバーも利用できない場合
        \Log::warning("画像処理ドライバー（GD/Imagick）が利用できません。WebP変換をスキップします。");
        return null;
    }

    /**
     * アップロードファイルを WebP に変換して S3 に保存（S3 には WebP のみ保存）
     * @return string|null 保存した WebP のパス（events/{id}/{unique}.webp）、失敗時は null
     */
    private function convertUploadToWebpAndPutS3($uploadedFile, int $eventId, $manager)
    {
        if (!$manager) {
            return null;
        }
        try {
            $webpPath = 'events/' . $eventId . '/' . Str::random(40) . '.webp';
            $image = $manager->read($uploadedFile->getRealPath());
            $tmpPath = tempnam(sys_get_temp_dir(), 'webp');
            $image->toWebp(80)->save($tmpPath);
            $content = file_get_contents($tmpPath);
            @unlink($tmpPath);
            Storage::disk('s3_public')->put($webpPath, $content);
            return $webpPath;
        } catch (\Exception $e) {
            \Log::error('WebP変換エラー (S3 events/' . $eventId . '): ' . $e->getMessage());
            return null;
        }
    }

    /**
     * 画像パスをWebP形式に変換（ローカル public 用）
     */
    private function convertPathToWebp($originalPath, $manager)
    {
        if (!$manager) {
            return null;
        }
        try {
            $fullPath = Storage::disk('public')->path($originalPath);
            $pathInfo = pathinfo($originalPath);
            $webpPath = $pathInfo['dirname'] . '/' . $pathInfo['filename'] . '.webp';
            $webpFullPath = Storage::disk('public')->path($webpPath);

            $image = $manager->read($fullPath);
            $image->toWebp(80)->save($webpFullPath);

            return $webpPath;
        } catch (\Exception $e) {
            \Log::error("WebP変換エラー ({$originalPath}): " . $e->getMessage());
            return null;
        }
    }

    /**
     * 既存画像をWebP形式に変換（管理画面から実行）
     */
    public function convertToWebp(Event $event, EventImage $image)
    {
        if ($image->event_id !== $event->id) {
            abort(404);
        }

        $manager = $this->createImageManager();
        if (!$manager) {
            return redirect()->route('admin.events.images.index', $event->id)
                ->with('error', '画像処理ドライバー（GD/Imagick）が利用できません。');
        }

        $webpPath = null;
        if (($image->storage_disk ?? 'public') === 's3') {
            $webpPath = $this->convertS3PathToWebp($image->path, $manager);
        } else {
            $webpPath = $this->convertPathToWebp($image->path, $manager);
        }
        if ($webpPath) {
            $image->update(['webp_path' => $webpPath]);
            return redirect()->route('admin.events.images.index', $event->id)
                ->with('success', 'WebPに変換しました。');
        }

        return redirect()->route('admin.events.images.index', $event->id)
            ->with('error', 'WebP変換に失敗しました。');
    }

    /**
     * S3上の画像をWebPに変換してS3に保存（既存画像用）
     */
    private function convertS3PathToWebp(string $originalPath, $manager)
    {
        if (!$manager) {
            return null;
        }
        try {
            $path = str_replace('\\', '/', $originalPath);
            $content = Storage::disk('s3_public')->get($path);
            $pathInfo = pathinfo($path);
            $webpPath = $pathInfo['dirname'] . '/' . $pathInfo['filename'] . '.webp';
            $tmpPath = tempnam(sys_get_temp_dir(), 'img');
            file_put_contents($tmpPath, $content);
            $image = $manager->read($tmpPath);
            $webpTmp = tempnam(sys_get_temp_dir(), 'webp');
            $image->toWebp(80)->save($webpTmp);
            $webpContent = file_get_contents($webpTmp);
            @unlink($tmpPath);
            @unlink($webpTmp);
            Storage::disk('s3_public')->put($webpPath, $webpContent);
            return $webpPath;
        } catch (\Exception $e) {
            \Log::error("WebP変換エラー (S3 {$originalPath}): " . $e->getMessage());
            return null;
        }
    }

    /**
     * イベント画像を削除
     * 同一 path を参照する他の EventImage がいる場合はストレージのファイルは削除しない（複製で共有しているため）
     */
    public function destroy(EventImage $image)
    {
        $eventId = $image->event_id;
        $disk = ($image->storage_disk ?? 'public') === 's3' ? 's3_public' : 'public';

        $otherUsesSamePath = EventImage::where('path', $image->path)->where('id', '!=', $image->id)->exists();
        $otherUsesSameWebpPath = $image->webp_path
            && EventImage::where('webp_path', $image->webp_path)->where('id', '!=', $image->id)->exists();

        $path = $disk === 's3_public' ? str_replace('\\', '/', $image->path) : $image->path;
        if (!$otherUsesSamePath && Storage::disk($disk)->exists($path)) {
            Storage::disk($disk)->delete($path);
        }
        if (!$otherUsesSameWebpPath && $image->webp_path) {
            $webpPath = $disk === 's3_public' ? str_replace('\\', '/', $image->webp_path) : $image->webp_path;
            if (Storage::disk($disk)->exists($webpPath)) {
                Storage::disk($disk)->delete($webpPath);
            }
        }

        $image->delete();

        return redirect()->route('admin.events.images.index', $eventId)
            ->with('success', '画像を削除しました。');
    }

    /**
     * 複数のイベント画像をまとめて削除
     */
    public function destroyBulk(Request $request, Event $event)
    {
        $validated = $request->validate([
            'image_ids' => 'required|array|min:1',
            'image_ids.*' => 'integer|exists:event_images,id',
        ]);

        $images = EventImage::where('event_id', $event->id)
            ->whereIn('id', $validated['image_ids'])
            ->get();

        $idsBeingDeleted = $validated['image_ids'];
        foreach ($images as $image) {
            $disk = ($image->storage_disk ?? 'public') === 's3' ? 's3_public' : 'public';
            $otherUsesSamePath = EventImage::where('path', $image->path)->whereNotIn('id', $idsBeingDeleted)->exists();
            $otherUsesSameWebpPath = $image->webp_path
                && EventImage::where('webp_path', $image->webp_path)->whereNotIn('id', $idsBeingDeleted)->exists();

            $path = $disk === 's3_public' ? str_replace('\\', '/', $image->path) : $image->path;
            if (!$otherUsesSamePath && Storage::disk($disk)->exists($path)) {
                Storage::disk($disk)->delete($path);
            }
            if (!$otherUsesSameWebpPath && $image->webp_path) {
                $webpPath = $disk === 's3_public' ? str_replace('\\', '/', $image->webp_path) : $image->webp_path;
                if (Storage::disk($disk)->exists($webpPath)) {
                    Storage::disk($disk)->delete($webpPath);
                }
            }
            $image->delete();
        }

        $count = $images->count();
        return redirect()->route('admin.events.images.index', $event->id)
            ->with('success', "{$count}件の画像を削除しました。");
    }

    /**
     * ソート順を更新
     */
    public function updateSortOrder(Request $request, Event $event)
    {
        $validated = $request->validate([
            'image_ids' => 'required|array',
            'image_ids.*' => 'exists:event_images,id',
        ]);

        foreach ($validated['image_ids'] as $index => $imageId) {
            EventImage::where('id', $imageId)
                ->where('event_id', $event->id)
                ->update(['sort_order' => $index + 1]);
        }

        return redirect()->route('admin.events.images.index', $event->id)
            ->with('success', 'ソート順を保存しました。');
    }

    /**
     * スライドショー位置を更新
     */
    public function updateSlideshowPositions(Request $request, Event $event)
    {
        $validated = $request->validate([
            'positions' => 'required|array',
            'positions.*.position' => 'required|integer|min:0',
            'positions.*.slideshows' => 'required|array',
            'positions.*.slideshows.*.slideshow_id' => 'nullable|exists:slideshows,id',
            'positions.*.slideshows.*.sort_order' => 'required|integer|min:0',
        ]);

        // 既存の位置情報を削除
        DB::table('event_slideshow_positions')
            ->where('event_id', $event->id)
            ->delete();

        // 新しい位置情報を追加
        foreach ($validated['positions'] as $positionData) {
            foreach ($positionData['slideshows'] as $slideshowData) {
                if (!empty($slideshowData['slideshow_id'])) {
                    DB::table('event_slideshow_positions')->insert([
                        'event_id' => $event->id,
                        'slideshow_id' => $slideshowData['slideshow_id'],
                        'position' => $positionData['position'],
                        'sort_order' => $slideshowData['sort_order'],
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }
        }

        return redirect()->route('admin.events.images.index', $event->id)
            ->with('success', 'スライドショー位置を更新しました。');
    }

    /**
     * CTAボタン表示位置を更新
     */
    public function updateCtaButtonPositions(Request $request, Event $event)
    {
        $validated = $request->validate([
            'positions' => 'required|array',
            'positions.*' => 'integer|min:0',
        ]);

        $positions = array_values(array_unique($validated['positions']));
        sort($positions);

        DB::table('event_cta_button_positions')
            ->where('event_id', $event->id)
            ->delete();

        $now = now();
        foreach ($positions as $position) {
            DB::table('event_cta_button_positions')->insert([
                'event_id' => $event->id,
                'position' => $position,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        return redirect()->route('admin.events.images.index', $event->id)
            ->with('success', 'CTAボタン位置を更新しました。');
    }

    /**
     * 画像の上下マージン（px）を更新
     */
    public function updateMargin(Request $request, Event $event, EventImage $image)
    {
        if ($image->event_id !== $event->id) {
            abort(404);
        }

        $validated = $request->validate([
            'margin_top_px' => 'nullable|integer|min:0|max:500',
            'margin_bottom_px' => 'nullable|integer|min:0|max:500',
        ]);

        $image->update([
            'margin_top_px' => $validated['margin_top_px'] ?? null,
            'margin_bottom_px' => $validated['margin_bottom_px'] ?? null,
        ]);

        return redirect()->route('admin.events.images.index', $event->id)
            ->with('success', 'マージンを更新しました。');
    }
}

