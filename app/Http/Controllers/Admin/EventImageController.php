<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\EventImage;
use App\Models\Slideshow;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
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
                'webp_path' => $image->webp_path,
                'alt' => $image->alt,
                'sort_order' => $image->sort_order,
                'file_format' => strtoupper(pathinfo($image->path, PATHINFO_EXTENSION) ?: '-'),
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

        return Inertia::render('Admin/EventImage/Index', [
            'event' => $event,
            'images' => $images,
            'slideshows' => $slideshows,
            'slideshowPositions' => $slideshowPositions,
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
        
        // 利用可能なドライバーでImageManagerを初期化
        $manager = $this->createImageManager();

        foreach ($request->file('images') as $index => $file) {
            $path = $file->store('events/' . $event->id, 'public');
            
            // WebP版を生成（ドライバーが利用可能な場合のみ）
            $webpPath = null;
            if ($manager) {
                $webpPath = $this->convertPathToWebp($path, $manager);
            }
            
            EventImage::create([
                'event_id' => $event->id,
                'path' => $path,
                'webp_path' => $webpPath,
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
     * 画像パスをWebP形式に変換
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
            
            // 画像を読み込んでWebPに変換
            $image = $manager->read($fullPath);
            
            // WebPとして保存（品質80%）
            $image->toWebp(80)->save($webpFullPath);
            
            return $webpPath;
        } catch (\Exception $e) {
            // エラーが発生した場合はnullを返す（既存画像と同様に扱う）
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

            $webpPath = $this->convertPathToWebp($image->path, $manager);
        if ($webpPath) {
            $image->update(['webp_path' => $webpPath]);
            return redirect()->route('admin.events.images.index', $event->id)
                ->with('success', 'WebPに変換しました。');
        }

        return redirect()->route('admin.events.images.index', $event->id)
            ->with('error', 'WebP変換に失敗しました。');
    }

    /**
     * イベント画像を削除
     */
    public function destroy(EventImage $image)
    {
        $eventId = $image->event_id;
        
        // ストレージから元のファイルを削除
        if (Storage::disk('public')->exists($image->path)) {
            Storage::disk('public')->delete($image->path);
        }
        
        // WebPファイルも削除
        if ($image->webp_path && Storage::disk('public')->exists($image->webp_path)) {
            Storage::disk('public')->delete($image->webp_path);
        }

        $image->delete();

        return redirect()->route('admin.events.images.index', $eventId)
            ->with('success', '画像を削除しました。');
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
            ->with('success', 'ソート順を更新しました。');
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
}

