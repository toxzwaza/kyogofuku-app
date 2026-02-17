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

class EventCtaDesignController extends Controller
{
    /**
     * CTAデザイン設定画面を表示
     */
    public function edit(Event $event)
    {
        return Inertia::render('Admin/Event/CtaDesign/Edit', [
            'event' => $event,
        ]);
    }

    /**
     * CTAデザインを更新（画像アップロード・削除）
     */
    public function update(Request $request, Event $event)
    {
        $request->validate([
            'cta_background' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:10240',
            'cta_web_button' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:10240',
            'cta_phone_button' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:10240',
            'remove_cta_background' => 'nullable|boolean',
            'remove_cta_web_button' => 'nullable|boolean',
            'remove_cta_phone_button' => 'nullable|boolean',
        ]);

        $manager = $this->createImageManager();
        $disk = 's3_public';
        $basePath = 'events/' . $event->id . '/cta/';

        $updates = [];

        // ボタン背景
        if ($request->boolean('remove_cta_background') || $request->hasFile('cta_background')) {
            $this->deleteCtaFile($event->cta_background_path, $event->cta_storage_disk);
            $updates['cta_background_path'] = null;
            if ($request->hasFile('cta_background') && $manager) {
                $path = $basePath . 'background.webp';
                if ($this->putWebpToS3($request->file('cta_background'), $path, $manager)) {
                    $updates['cta_background_path'] = $path;
                    $updates['cta_storage_disk'] = 's3';
                }
            }
        }

        // WEB予約ボタン
        if ($request->boolean('remove_cta_web_button') || $request->hasFile('cta_web_button')) {
            $this->deleteCtaFile($event->cta_web_button_path, $event->cta_storage_disk);
            $updates['cta_web_button_path'] = null;
            if ($request->hasFile('cta_web_button') && $manager) {
                $path = $basePath . 'web_button.webp';
                if ($this->putWebpToS3($request->file('cta_web_button'), $path, $manager)) {
                    $updates['cta_web_button_path'] = $path;
                    $updates['cta_storage_disk'] = 's3';
                }
            }
        }

        // 電話予約ボタン
        if ($request->boolean('remove_cta_phone_button') || $request->hasFile('cta_phone_button')) {
            $this->deleteCtaFile($event->cta_phone_button_path, $event->cta_storage_disk);
            $updates['cta_phone_button_path'] = null;
            if ($request->hasFile('cta_phone_button') && $manager) {
                $path = $basePath . 'phone_button.webp';
                if ($this->putWebpToS3($request->file('cta_phone_button'), $path, $manager)) {
                    $updates['cta_phone_button_path'] = $path;
                    $updates['cta_storage_disk'] = 's3';
                }
            }
        }

        if (!empty($updates)) {
            $event->update($updates);
        }

        return redirect()->route('admin.events.cta-design.edit', $event)
            ->with('success', 'CTAデザインを更新しました。');
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

    /**
     * アップロードを WebP に変換して S3 に保存
     */
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
            Log::error('CTA WebP変換エラー (' . $path . '): ' . $e->getMessage());
            return false;
        }
    }

    private function deleteCtaFile(?string $path, ?string $storageDisk): void
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
