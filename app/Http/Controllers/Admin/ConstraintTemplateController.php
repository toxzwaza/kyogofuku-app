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
        ]);

        $validated['is_active'] = $validated['is_active'] ?? true;
        $shopIds = $validated['shop_ids'] ?? [];
        unset($validated['shop_ids']);

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
        ]);

        $validated['is_active'] = $validated['is_active'] ?? true;
        $shopIds = $validated['shop_ids'] ?? [];
        unset($validated['shop_ids']);

        $constraintTemplate->update($validated);
        $constraintTemplate->shops()->sync($shopIds);

        return redirect()->route('admin.constraint-templates.index')
            ->with('success', '制約テンプレートを更新しました。');
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
