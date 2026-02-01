<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ConstraintTemplate;
use App\Models\Shop;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ConstraintTemplateController extends Controller
{
    public function index()
    {
        $constraintTemplates = ConstraintTemplate::with('shops')
            ->withCount('customerConstraints')
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        return Inertia::render('Admin/ConstraintTemplate/Index', [
            'constraintTemplates' => $constraintTemplates,
        ]);
    }

    public function create()
    {
        $shops = Shop::where('is_active', true)->orderBy('name')->get(['id', 'name']);

        return Inertia::render('Admin/ConstraintTemplate/Create', [
            'shops' => $shops,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'body' => 'required|string',
            'is_active' => 'boolean',
            'shop_ids' => 'nullable|array',
            'shop_ids.*' => 'exists:shops,id',
            'display_settings' => 'nullable|array',
            'display_settings.padding_mm' => 'nullable|numeric|min:2|max:20',
            'display_settings.line_height' => 'nullable|numeric|min:1|max:2.5',
            'display_settings.font_size_px' => 'nullable|numeric|min:6|max:24',
            'display_settings.signature_height_px' => 'nullable|numeric|min:40|max:200',
            'display_settings.checkbox_size_px' => 'nullable|numeric|min:6|max:24',
        ]);

        $validated['is_active'] = $validated['is_active'] ?? true;
        $shopIds = $validated['shop_ids'] ?? [];
        unset($validated['shop_ids']);
        $displaySettings = $validated['display_settings'] ?? null;
        unset($validated['display_settings']);
        if (is_array($displaySettings)) {
            $filtered = [];
            foreach ($displaySettings as $k => $v) {
                if ($v !== null && $v !== '') {
                    $filtered[$k] = is_numeric($v) ? (float) $v : $v;
                }
            }
            $validated['display_settings'] = !empty($filtered) ? $filtered : null;
        }

        $template = ConstraintTemplate::create($validated);
        $template->shops()->sync($shopIds);

        return redirect()->route('admin.constraint-templates.index')
            ->with('success', '制約テンプレートを追加しました。');
    }

    public function edit(ConstraintTemplate $constraintTemplate)
    {
        $constraintTemplate->load('shops');
        $shops = Shop::where('is_active', true)->orderBy('name')->get(['id', 'name']);

        return Inertia::render('Admin/ConstraintTemplate/Edit', [
            'constraintTemplate' => $constraintTemplate,
            'shops' => $shops,
        ]);
    }

    public function update(Request $request, ConstraintTemplate $constraintTemplate)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'body' => 'required|string',
            'is_active' => 'boolean',
            'shop_ids' => 'nullable|array',
            'shop_ids.*' => 'exists:shops,id',
            'display_settings' => 'nullable|array',
            'display_settings.padding_mm' => 'nullable|numeric|min:2|max:20',
            'display_settings.line_height' => 'nullable|numeric|min:1|max:2.5',
            'display_settings.font_size_px' => 'nullable|numeric|min:6|max:24',
            'display_settings.signature_height_px' => 'nullable|numeric|min:40|max:200',
            'display_settings.checkbox_size_px' => 'nullable|numeric|min:6|max:24',
        ]);

        $validated['is_active'] = $validated['is_active'] ?? true;
        $shopIds = $validated['shop_ids'] ?? [];
        unset($validated['shop_ids']);
        $displaySettings = $validated['display_settings'] ?? null;
        unset($validated['display_settings']);
        if (is_array($displaySettings)) {
            $filtered = [];
            foreach ($displaySettings as $k => $v) {
                if ($v !== null && $v !== '') {
                    $filtered[$k] = is_numeric($v) ? (float) $v : $v;
                }
            }
            $validated['display_settings'] = !empty($filtered) ? $filtered : null;
        }

        $constraintTemplate->update($validated);
        $constraintTemplate->shops()->sync($shopIds);

        return redirect()->route('admin.constraint-templates.index')
            ->with('success', '制約テンプレートを更新しました。');
    }

    /**
     * 制約テンプレートの印刷プレビューページを表示
     * GET ?template_id=123: 保存済みテンプレートを表示（Edit用）
     * POST name, body, display_settings: 未保存データで表示（Create用）
     */
    public function preview(Request $request)
    {
        if ($request->isMethod('post')) {
            $validated = $request->validate([
                'name' => 'required|string|max:255',
                'body' => 'required|string',
                'display_settings' => 'nullable|array',
                'display_settings.padding_mm' => 'nullable|numeric|min:2|max:20',
                'display_settings.line_height' => 'nullable|numeric|min:1|max:2.5',
                'display_settings.font_size_px' => 'nullable|numeric|min:6|max:24',
                'display_settings.signature_height_px' => 'nullable|numeric|min:40|max:200',
                'display_settings.checkbox_size_px' => 'nullable|numeric|min:6|max:24',
            ]);
            $displaySettings = $validated['display_settings'] ?? [];
            $filtered = [];
            foreach ($displaySettings as $k => $v) {
                if ($v !== null && $v !== '') {
                    $filtered[$k] = is_numeric($v) ? (float) $v : $v;
                }
            }
            $template = [
                'id' => 0,
                'name' => $validated['name'],
                'body' => $validated['body'],
                'display_settings' => array_merge(ConstraintTemplate::defaultDisplaySettings(), $filtered),
            ];
            $backUrl = route('admin.constraint-templates.create');
        } else {
            $templateId = $request->query('template_id');
            if (!$templateId) {
                abort(404, 'template_id is required');
            }
            $t = ConstraintTemplate::findOrFail($templateId);
            $baseSettings = $t->getDisplaySettings();
            // クエリで表示設定の上書きを許可（編集中の未保存の値でプレビュー）
            $overrides = [];
            if ($request->has('padding_mm')) {
                $overrides['padding_mm'] = (float) $request->query('padding_mm');
            }
            if ($request->has('line_height')) {
                $overrides['line_height'] = (float) $request->query('line_height');
            }
            if ($request->has('font_size_px')) {
                $overrides['font_size_px'] = (int) $request->query('font_size_px');
            }
            if ($request->has('signature_height_px')) {
                $overrides['signature_height_px'] = (int) $request->query('signature_height_px');
            }
            if ($request->has('checkbox_size_px')) {
                $overrides['checkbox_size_px'] = (int) $request->query('checkbox_size_px');
            }
            $template = [
                'id' => $t->id,
                'name' => $t->name,
                'body' => $t->body,
                'display_settings' => array_merge($baseSettings, $overrides),
            ];
            $backUrl = route('admin.constraint-templates.edit', $t);
        }

        return Inertia::render('Admin/Customer/ConstraintSign', [
            'customer' => ['id' => 0, 'name' => 'プレビュー', 'kana' => ''],
            'template' => $template,
            'staff' => [],
            'signedAt' => date('Y-m-d'),
            'explainerUserId' => null,
            'explainerName' => null,
            'checkValues' => [],
            'editId' => null,
            'existingSignature' => null,
            'isPreviewMode' => true,
            'backUrl' => $backUrl,
        ]);
    }

    public function destroy(ConstraintTemplate $constraintTemplate)
    {
        if ($constraintTemplate->customerConstraints()->exists()) {
            return redirect()->route('admin.constraint-templates.index')
                ->with('error', 'この制約テンプレートは顧客に紐づいているため削除できません。');
        }

        $constraintTemplate->delete();

        return redirect()->route('admin.constraint-templates.index')
            ->with('success', '制約テンプレートを削除しました。');
    }
}
