<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\MediaFile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use App\Http\Controllers\Concerns\ResolvesUiView;
use Inertia\Inertia;
use Intervention\Image\ImageManager;
use Intervention\Image\Drivers\Gd\Driver as GdDriver;
use Intervention\Image\Drivers\Imagick\Driver as ImagickDriver;

class EventLpSettingsController extends Controller
{
    use ResolvesUiView;

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
                'render_type' => $cfg['render_type'] ?? 'inertia',
                'requires_form_schema' => (bool) ($cfg['requires_form_schema'] ?? false),
            ])
            ->values()
            ->all();

        return Inertia::render($this->viewFor('Admin/Event/LpSettings/Edit'), [
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
            'media_background_image_id' => 'nullable|integer|exists:media_files,id',
            'include_lp_design' => 'nullable|boolean',
        ];
        if ($request->boolean('include_lp_design')) {
            $rules['lp_design_slug'] = 'nullable|string|max:100';
            $rules['lp_theme_tokens_json'] = 'nullable|string|max:20000';
            $rules['form_schema_json'] = 'nullable|string|max:50000';
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
                $updates['form_schema'] = null;
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

                $renderType = $tpl['render_type'] ?? 'inertia';
                if ($renderType === 'blade') {
                    $parsed = $this->parseFormSchema($request->input('form_schema_json'));
                    if ($parsed['error']) {
                        return redirect()->back()
                            ->withErrors(['form_schema_json' => $parsed['error']])
                            ->withInput();
                    }
                    $updates['form_schema'] = $parsed['value'];
                    if (!empty($tpl['requires_form_schema']) && empty($parsed['value'])) {
                        return redirect()->back()
                            ->withErrors(['form_schema_json' => 'このテンプレートではフォーム定義（form_schema）が必須です。'])
                            ->withInput();
                    }
                } else {
                    $updates['form_schema'] = null;
                }
            }
        }

        // 背景画像の削除または差し替え
        if ($request->filled('media_background_image_id')) {
            $media = MediaFile::findOrFail($request->input('media_background_image_id'));
            $updates['background_image_path'] = $media->path;
            $updates['background_image_storage_disk'] = $media->storage_disk;
        } elseif ($request->boolean('remove_background_image') || $request->hasFile('background_image')) {
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
     * form_schema_json を検証して配列に変換する。
     * 期待する形式: 各要素が { key, label, type, required?, options?, placeholder?, help?, default?, ... }
     *
     * @return array{value: array<int, array<string, mixed>>|null, error: string|null}
     */
    private function parseFormSchema(?string $json): array
    {
        if ($json === null || trim($json) === '') {
            return ['value' => null, 'error' => null];
        }
        $decoded = json_decode($json, true);
        if (!is_array($decoded)) {
            return ['value' => null, 'error' => 'JSONの形式が不正です。配列として解釈できません。'];
        }

        $allowedTypes = \App\Services\BladeLp\FormSchemaValidator::SUPPORTED_TYPES;
        $usedKeys = [];
        $out = [];
        foreach ($decoded as $i => $field) {
            if (!is_array($field)) {
                return ['value' => null, 'error' => "{$i} 番目の要素がオブジェクト形式ではありません。"];
            }
            $key = $field['key'] ?? null;
            $type = $field['type'] ?? 'text';
            $label = $field['label'] ?? null;

            if (!is_string($key) || !preg_match('/^[a-zA-Z][a-zA-Z0-9_]{0,63}$/', $key)) {
                return ['value' => null, 'error' => "{$i} 番目: key は英数字・アンダースコアで始まる識別子（最大64文字）にしてください。"];
            }
            if (isset($usedKeys[$key])) {
                return ['value' => null, 'error' => "key '{$key}' が重複しています。"];
            }
            $usedKeys[$key] = true;

            if (!in_array($type, $allowedTypes, true)) {
                return ['value' => null, 'error' => "{$i} 番目: type '{$type}' はサポートされていません。"];
            }
            if (!is_string($label) || $label === '') {
                return ['value' => null, 'error' => "{$i} 番目: label を設定してください。"];
            }

            $entry = [
                'key' => $key,
                'type' => $type,
                'label' => $label,
                'required' => (bool) ($field['required'] ?? false),
            ];
            foreach (['placeholder', 'help', 'default', 'min', 'max', 'pattern', 'rows', 'auto_options', 'options_from', 'filter_by_venue_field'] as $optKey) {
                if (array_key_exists($optKey, $field)) {
                    $entry[$optKey] = $field[$optKey];
                }
            }
            if (in_array($type, ['select', 'radio', 'checkbox'], true) && isset($field['options'])) {
                if (!is_array($field['options'])) {
                    return ['value' => null, 'error' => "{$i} 番目: options は配列にしてください。"];
                }
                $entry['options'] = array_values(array_map('strval', $field['options']));
            }

            // show_if: 他フィールドの値に応じた条件付き表示
            if (isset($field['show_if']) && is_array($field['show_if'])) {
                $depKey = $field['show_if']['key'] ?? null;
                $op = $field['show_if']['op'] ?? 'not_empty';
                if (is_string($depKey) && $depKey !== '' && in_array($op, ['not_empty', 'equals'], true)) {
                    $showIf = ['key' => $depKey, 'op' => $op];
                    if ($op === 'equals' && array_key_exists('value', $field['show_if'])) {
                        $showIf['value'] = (string) $field['show_if']['value'];
                    }
                    $entry['show_if'] = $showIf;
                }
            }

            $out[] = $entry;
        }

        // show_if の依存先 key が実在するか検証
        $allKeys = array_flip(array_column($out, 'key'));
        foreach ($out as $i => $f) {
            if (!isset($f['show_if'])) continue;
            if (!isset($allKeys[$f['show_if']['key']])) {
                return ['value' => null, 'error' => "{$i} 番目: 条件付き表示の依存先 '{$f['show_if']['key']}' が見つかりません。"];
            }
            if ($f['show_if']['key'] === $f['key']) {
                return ['value' => null, 'error' => "{$i} 番目: 条件付き表示の依存先に自分自身を指定できません。"];
            }
        }

        return ['value' => $out, 'error' => null];
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
