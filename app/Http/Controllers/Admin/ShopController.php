<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Shop;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class ShopController extends Controller
{
    /**
     * 店舗一覧を表示
     */
    public function index()
    {
        $shops = Shop::orderBy('created_at', 'desc')->paginate(20);

        return Inertia::render('Admin/Shop/Index', [
            'shops' => $shops,
        ]);
    }

    /**
     * 店舗追加フォームを表示
     */
    public function create()
    {
        return Inertia::render('Admin/Shop/Create');
    }

    /**
     * 店舗を保存
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'address' => 'nullable|string|max:255',
            'phone' => 'nullable|string|max:255',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:10240',
            'is_active' => 'boolean',
            'line_group_id' => 'nullable|string|max:255',
        ]);

        $validated['is_active'] = $request->has('is_active') ? $request->is_active : true;

        if ($request->hasFile('image')) {
            $validated['image'] = $request->file('image')->store('shops', 'public');
        }

        Shop::create($validated);

        return redirect()->route('admin.shops.index')
            ->with('success', '店舗を追加しました。');
    }

    /**
     * 店舗編集フォームを表示
     */
    public function edit(Shop $shop)
    {
        return Inertia::render('Admin/Shop/Edit', [
            'shop' => $shop,
        ]);
    }

    /**
     * 店舗を更新
     */
    public function update(Request $request, Shop $shop)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'address' => 'nullable|string|max:255',
            'phone' => 'nullable|string|max:255',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:10240',
            'is_active' => 'boolean',
            'line_group_id' => 'nullable|string|max:255',
        ]);

        $validated['is_active'] = $request->has('is_active') ? $request->is_active : $shop->is_active;

        if ($request->hasFile('image')) {
            // 古い画像を削除
            if ($shop->image && Storage::disk('public')->exists($shop->image)) {
                Storage::disk('public')->delete($shop->image);
            }
            $validated['image'] = $request->file('image')->store('shops', 'public');
        } elseif ($request->has('remove_image')) {
            // 画像削除リクエスト
            if ($shop->image && Storage::disk('public')->exists($shop->image)) {
                Storage::disk('public')->delete($shop->image);
            }
            $validated['image'] = null;
        }

        $shop->update($validated);

        return redirect()->route('admin.shops.index')
            ->with('success', '店舗を更新しました。');
    }

    /**
     * 店舗を削除
     */
    public function destroy(Shop $shop)
    {
        // 関連するイベントやユーザーがある場合は削除不可にする
        if ($shop->events()->count() > 0 || $shop->users()->count() > 0) {
            return redirect()->back()
                ->withErrors(['error' => '関連するイベントまたはスタッフが存在するため削除できません。']);
        }

        $shop->delete();

        return redirect()->route('admin.shops.index')
            ->with('success', '店舗を削除しました。');
    }
}

