<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Shop;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Inertia\Inertia;

class UserController extends Controller
{
    /**
     * スタッフ一覧を表示
     */
    public function index(Request $request)
    {
        $currentUser = $request->user();
        $currentUserShops = $currentUser->shops()->withPivot('main')->get();
        
        // デフォルト店舗を取得（メイン店舗、なければ最初の店舗）
        $defaultShop = $currentUserShops->firstWhere('pivot.main', true) ?? $currentUserShops->first();
        $defaultShopId = $defaultShop ? $defaultShop->id : null;
        
        $query = User::with(['shops' => function($query) {
            $query->withPivot('main');
        }]);

        // 店舗でフィルタリング
        $shopId = $request->filled('shop_id') ? $request->shop_id : $defaultShopId;
        if ($shopId) {
            $query->whereHas('shops', function($q) use ($shopId) {
                $q->where('shops.id', $shopId);
            });
        }

        // 名前で検索
        if ($request->filled('name')) {
            $query->where('name', 'LIKE', '%' . $request->name . '%');
        }

        // メールアドレスで検索
        if ($request->filled('email')) {
            $query->where('email', 'LIKE', '%' . $request->email . '%');
        }

        $users = $query->orderBy('created_at', 'desc')->paginate(20)->withQueryString();

        $shops = Shop::where('is_active', true)->get();

        return Inertia::render('Admin/User/Index', [
            'users' => $users,
            'shops' => $shops,
            'filters' => [
                'shop_id' => $shopId,
                'name' => $request->name ?? '',
                'email' => $request->email ?? '',
            ],
        ]);
    }

    /**
     * スタッフ追加フォームを表示
     */
    public function create()
    {
        $shops = Shop::where('is_active', true)->get();

        return Inertia::render('Admin/User/Create', [
            'shops' => $shops,
        ]);
    }

    /**
     * スタッフを保存
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'nullable|string|email|max:255|unique:users',
            'login_id' => 'nullable|string|max:255|unique:users',
            'password' => 'required|string|min:8|confirmed',
            'theme_color' => 'nullable|string|regex:/^#[0-9A-Fa-f]{6}$/',
            'shop_ids' => 'nullable|array',
            'shop_ids.*' => 'exists:shops,id',
            'main_shop_id' => 'nullable|exists:shops,id',
        ]);

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'] ?? null,
            'login_id' => $validated['login_id'] ?? null,
            'password' => Hash::make($validated['password']),
            'theme_color' => $validated['theme_color'] ?? null,
        ]);

        if ($request->has('shop_ids')) {
            $shopIds = $request->shop_ids;
            $mainShopId = $request->main_shop_id;
            
            // 店舗をアタッチ（mainフラグを設定）
            foreach ($shopIds as $shopId) {
                $user->shops()->attach($shopId, [
                    'main' => ($shopId == $mainShopId)
                ]);
            }
        }

        return redirect()->route('admin.users.index')
            ->with('success', 'スタッフを追加しました。');
    }

    /**
     * スタッフ編集フォームを表示
     */
    public function edit(User $user)
    {
        $user->load(['shops' => function($query) {
            $query->withPivot('main');
        }]);
        $shops = Shop::where('is_active', true)->get();
        
        // メイン店舗のIDを取得
        $mainShop = $user->shops->firstWhere('pivot.main', true);
        $mainShopId = $mainShop ? $mainShop->id : null;

        return Inertia::render('Admin/User/Edit', [
            'user' => $user,
            'shops' => $shops,
            'main_shop_id' => $mainShopId,
        ]);
    }

    /**
     * スタッフを更新
     */
    public function update(Request $request, User $user)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'nullable|string|email|max:255|unique:users,email,' . $user->id,
            'login_id' => 'nullable|string|max:255|unique:users,login_id,' . $user->id,
            'password' => 'nullable|string|min:8|confirmed',
            'theme_color' => 'nullable|string|regex:/^#[0-9A-Fa-f]{6}$/',
            'shop_ids' => 'nullable|array',
            'shop_ids.*' => 'exists:shops,id',
            'main_shop_id' => 'nullable|exists:shops,id',
        ]);

        $user->update([
            'name' => $validated['name'],
            'email' => $validated['email'] ?? null,
            'login_id' => $validated['login_id'] ?? null,
            'theme_color' => $validated['theme_color'] ?? null,
        ]);

        if ($request->filled('password')) {
            $user->update([
                'password' => Hash::make($validated['password']),
            ]);
        }

        if ($request->has('shop_ids')) {
            $shopIds = $request->shop_ids;
            $mainShopId = $request->main_shop_id;
            
            // 店舗を同期（mainフラグを設定）
            $syncData = [];
            foreach ($shopIds as $shopId) {
                $syncData[$shopId] = [
                    'main' => ($shopId == $mainShopId)
                ];
            }
            $user->shops()->sync($syncData);
        } else {
            $user->shops()->detach();
        }

        return redirect()->route('admin.users.index')
            ->with('success', 'スタッフを更新しました。');
    }

    /**
     * スタッフを削除
     */
    public function destroy(User $user)
    {
        $user->delete();

        return redirect()->route('admin.users.index')
            ->with('success', 'スタッフを削除しました。');
    }
}

