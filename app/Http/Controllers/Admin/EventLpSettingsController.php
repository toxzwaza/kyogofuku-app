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
        $templates = collect(config('lp_designs.templates', []))
            ->map(fn (array $cfg, string $key) => [
                'slug' => $key,
                'label' => $cfg['label'] ?? $key,
                'allowed_form_types' => $cfg['allowed_form_types'] ?? [],
            ])
            ->values()
            ->all();

        return Inertia::render('Admin/Event/LpSettings/Edit', [
            'event' => $event,
            'lpTemplates' => $templates,
        ]);
    }

    /**
     * LP設定を更新（背景色・背景画像の有無・背景画像アップロード）
     */
    public function update(Request $request, Event $event)
    {
        $rules = [
            'background_color' => 'nullable|string|max:50',
            'content_background_color' => 'nullable|string|max:50',
            'background_image_enabled' => 'nullable|boolean',
            'background_image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:10240',
            'remove_background_image' => 'nullable|boolean',
            'include_lp_design' => 'nullable|boolean',
        ];
        if ($request->boolean('include_lp_design')) {
            $rules['lp_design_slug'] = 'nullable|string|max:100';
            $rules['lp_theme_tokens_json'] = 'nullable|string|max:20000';
        }
        $request->validate($rules);

        $updates = [
            'background_color' => $request->input('background_color') ?: null,
            'content_background_color' => $request->input('content_background_color') ?: null,
            'background_image_enabled' => $request->boolean('background_image_enabled'),
        ];

        if ($request->boolean('include_lp_design')) {
            $slug = $request->input('lp_design_slug');
            if ($slug === null || $slug === '') {
                $updates['lp_design_slug'] = null;
                $updates['lp_theme_tokens'] = null;
            } else {
                $tpl = config('lp_designs.templates.'.$slug);
                if (!is_array($tpl)) {
                    return redirect()->back()
                        ->withErrors(['lp_design_slug' => '無効なテンプレートです。'])
                        ->withInput();
                }
                $allowedTypes = $tpl['allowed_form_types'] ?? [];
                if (is_array($allowedTypes) && $allowedTypes !== [] && !in_array($event->form_type, $allowedTypes, true)) {
                    return redirect()->back()
                        ->withErrors(['lp_design_slug' => 'このフォーム種別では利用できないテンプレートです。'])
                        ->withInput();
                }
                $updates['lp_design_slug'] = $slug;
                $updates['lp_theme_tokens'] = $this->parseLpThemeTokens($request->input('lp_theme_tokens_json'));
            }
        }

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

    /**
     * @return array<string, string>|null
     */
    private function parseLpThemeTokens(?string $json): ?array
    {
        if ($json === null || trim($json) === '') {
            return null;
        }
        $decoded = json_decode($json, true);
        if (!is_array($decoded)) {
            return null;
        }
        $allowed = array_flip(config('lp_designs.allowed_token_keys', []));
        $out = [];
        foreach ($decoded as $key => $value) {
            if (!is_string($key) || !isset($allowed[$key])) {
                continue;
            }
            if (!is_scalar($value) || $value === '') {
                continue;
            }
            $out[$key] = (string) $value;
        }

        return $out === [] ? null : $out;
    }
}
