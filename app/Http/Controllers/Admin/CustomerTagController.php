<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CustomerTag;
use Illuminate\Http\Request;
use Inertia\Inertia;

class CustomerTagController extends Controller
{
    /**
     * 顧客タグ一覧を表示
     */
    public function index()
    {
        $customerTags = CustomerTag::orderBy('created_at', 'desc')->paginate(20);

        return Inertia::render('Admin/CustomerTag/Index', [
            'customerTags' => $customerTags,
        ]);
    }

    /**
     * 顧客タグ追加フォームを表示
     */
    public function create()
    {
        return Inertia::render('Admin/CustomerTag/Create');
    }

    /**
     * 顧客タグを保存
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'color' => 'nullable|string|max:255',
            'is_active' => 'boolean',
        ]);

        $validated['is_active'] = $validated['is_active'] ?? true;

        CustomerTag::create($validated);

        return redirect()->route('admin.customer-tags.index')
            ->with('success', '顧客タグを追加しました。');
    }

    /**
     * 顧客タグ詳細を表示
     */
    public function show(CustomerTag $customerTag)
    {
        $customerTag->load('customers');

        return Inertia::render('Admin/CustomerTag/Show', [
            'customerTag' => $customerTag,
        ]);
    }

    /**
     * 顧客タグ編集フォームを表示
     */
    public function edit(CustomerTag $customerTag)
    {
        return Inertia::render('Admin/CustomerTag/Edit', [
            'customerTag' => $customerTag,
        ]);
    }

    /**
     * 顧客タグを更新
     */
    public function update(Request $request, CustomerTag $customerTag)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'color' => 'nullable|string|max:255',
            'is_active' => 'boolean',
        ]);

        $validated['is_active'] = $validated['is_active'] ?? true;

        $customerTag->update($validated);

        return redirect()->route('admin.customer-tags.index')
            ->with('success', '顧客タグを更新しました。');
    }

    /**
     * 顧客タグを削除
     */
    public function destroy(CustomerTag $customerTag)
    {
        // 顧客に紐づいている場合は削除不可
        if ($customerTag->customers()->exists()) {
            return redirect()->route('admin.customer-tags.index')
                ->with('error', 'このタグは顧客に紐づいているため削除できません。');
        }

        $customerTag->delete();

        return redirect()->route('admin.customer-tags.index')
            ->with('success', '顧客タグを削除しました。');
    }
}

