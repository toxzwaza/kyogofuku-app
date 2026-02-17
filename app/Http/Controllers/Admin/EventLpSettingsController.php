<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Event;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Intervention\Image\ImageManager;
use Intervention\Image\Drivers\Gd\Driver as GdDriver;
use Intervention\Image\Drivers\Imagick\Driver as ImagickDriver;

class EventLpSettingsController extends Controller
{
    /**
     * LP設定画面を表示
     */
    public function edit(Event $event)
    {
        return Inertia::render('Admin/Event/LpSettings/Edit', [
            'event' => $event,
        ]);
    }

    /**
     * LP設定を更新（背景色・背景画像の有無・背景画像アップロード）
     */
    public function update(Request $request, Event $event)
    {
        $request->validate([
            'background_color' => 'nullable|string|max:50',
            'content_background_color' => 'nullable|string|max:50',
            'background_image_enabled' => 'nullable|boolean',
            'background_image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:10240',
            'remove_background_image' => 'nullable|boolean',
        ]);

        $updates = [
            'background_color' => $request->input('background_color') ?: null,
            'content_background_color' => $request->input('content_background_color') ?: null,
            'background_image_enabled' => $request->boolean('background_image_enabled'),
        ];

        // 背景画像の削除または差し替え
        if ($request->boolean('remove_background_image') || $request->hasFile('background_image')) {
            $this->deleteBackgroundImage($event->background_image_path, $event->background_image_storage_disk);
            $updates['background_image_path'] = null;
            $updates['background_image_storage_disk'] = null;

            if ($request->hasFile('background_image')) {
                $manager = $this->createImageManager();
                if ($manager) {
                    $path = 'events/' . $event->id . '/lp/background.webp';
                    if ($this->putWebpToS3($request->file('background_image'), $path, $manager)) {
                        $updates['background_image_path'] = $path;
                        $updates['background_image_storage_disk'] = 's3';
                    }
                }
            }
        }

        $event->update($updates);

        return redirect()->route('admin.events.lp-settings.edit', $event)
            ->with('success', 'LP設定を更新しました。');
    }

    private function createImageManager(): ?ImageManager
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
        return null;
    }

    private function putWebpToS3($uploadedFile, string $path, ImageManager $manager): bool
    {
        try {
            $path = str_replace('\\', '/', $path);
            $image = $manager->read($uploadedFile->getRealPath());
            $tmpPath = tempnam(sys_get_temp_dir(), 'webp');
            $image->toWebp(85)->save($tmpPath);
            $content = file_get_contents($tmpPath);
            @unlink($tmpPath);
            Storage::disk('s3_public')->put($path, $content);
            return true;
        } catch (\Exception $e) {
            Log::error('LP背景画像 WebP変換エラー (' . $path . '): ' . $e->getMessage());
            return false;
        }
    }

    private function deleteBackgroundImage(?string $path, ?string $storageDisk): void
    {
        if (!$path) {
            return;
        }
        $disk = ($storageDisk ?? 'public') === 's3' ? 's3_public' : 'public';
        $path = str_replace('\\', '/', $path);
        if (Storage::disk($disk)->exists($path)) {
            Storage::disk($disk)->delete($path);
        }
    }
}
