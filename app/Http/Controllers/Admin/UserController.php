<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Shop;
use App\Models\WorkAttribute;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use App\Http\Controllers\Concerns\ResolvesUiView;
use Inertia\Inertia;

class UserController extends Controller
{
    use ResolvesUiView;

    /**
     * スタッフ・権限の管理はシステム管理者のみに限定する。
     * 各アクションの冒頭で呼び出してガードする。
     */
    private function ensureCanManageUsers(): void
    {
        abort_unless(auth()->user()?->canManageUsers(), 403, 'スタッフ・権限の管理はシステム管理者のみ行えます。');
    }

    /**
     * スタッフ一覧を表示
     */
    public function index(Request $request)
    {
        $this->ensureCanManageUsers();

        $currentUser = $request->user();
        $currentUserShops = $currentUser->shops()->withPivot('main')->get();
        
        // デフォルト店舗を取得（メイン店舗、なければ最初の店舗）
        $defaultShop = $currentUserShops->firstWhere('pivot.main', true) ?? $currentUserShops->first();
        $defaultShopId = $defaultShop ? $defaultShop->id : null;
        
        $query = User::with([
            'workAttribute:id,name',
            'shops' => function ($query) {
                $query->withPivot('main');
            },
        ]);

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

        return Inertia::render($this->viewFor('Admin/User/Index'), [
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
        $this->ensureCanManageUsers();

        $shops = Shop::where('is_active', true)->get();
        $workAttributes = WorkAttribute::query()->orderBy('sort_order')->orderBy('id')->get(['id', 'name']);

        return Inertia::render($this->viewFor('Admin/User/Create'), [
            'shops' => $shops,
            'workAttributes' => $workAttributes,
        ]);
    }

    /**
     * スタッフを保存
     */
    public function store(Request $request)
    {
        $this->ensureCanManageUsers();

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'nullable|string|email|max:255|unique:users',
            'login_id' => 'nullable|string|max:255|unique:users',
            'password' => 'required|string|min:8|confirmed',
            'theme_color' => 'nullable|string|regex:/^#[0-9A-Fa-f]{6}$/',
            'attendance_role' => 'nullable|in:shop_manager,attendance_manager,system_admin',
            'shop_ids' => 'nullable|array',
            'shop_ids.*' => 'exists:shops,id',
            'main_shop_id' => 'nullable|exists:shops,id',
            'work_attribute_id' => 'nullable|exists:work_attributes,id',
            'break_mode' => 'required|in:fixed,manual',
            'scheduled_break_minutes' => 'nullable|integer|min:0|max:600|required_if:break_mode,fixed',
        ]);

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'] ?? null,
            'login_id' => $validated['login_id'] ?? null,
            'password' => Hash::make($validated['password']),
            'theme_color' => $validated['theme_color'] ?? null,
            'attendance_role' => $validated['attendance_role'] ?? null,
            'work_attribute_id' => $validated['work_attribute_id'] ?? null,
            'break_mode' => $validated['break_mode'],
            'scheduled_break_minutes' => $validated['break_mode'] === User::BREAK_MODE_FIXED
                ? ($validated['scheduled_break_minutes'] ?? 0)
                : null,
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
        $this->ensureCanManageUsers();

        $user->load(['shops' => function($query) {
            $query->withPivot('main');
        }]);
        $shops = Shop::where('is_active', true)->get();
        $workAttributes = WorkAttribute::query()->orderBy('sort_order')->orderBy('id')->get(['id', 'name']);

        // メイン店舗のIDを取得
        $mainShop = $user->shops->firstWhere('pivot.main', true);
        $mainShopId = $mainShop ? $mainShop->id : null;

        return Inertia::render($this->viewFor('Admin/User/Edit'), [
            'user' => $user,
            'shops' => $shops,
            'workAttributes' => $workAttributes,
            'main_shop_id' => $mainShopId,
        ]);
    }

    /**
     * スタッフを更新
     */
    public function update(Request $request, User $user)
    {
        $this->ensureCanManageUsers();

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'nullable|string|email|max:255|unique:users,email,' . $user->id,
            'login_id' => 'nullable|string|max:255|unique:users,login_id,' . $user->id,
            'password' => 'nullable|string|min:8|confirmed',
            'theme_color' => 'nullable|string|regex:/^#[0-9A-Fa-f]{6}$/',
            'attendance_role' => 'nullable|in:shop_manager,attendance_manager,system_admin',
            'shop_ids' => 'nullable|array',
            'shop_ids.*' => 'exists:shops,id',
            'main_shop_id' => 'nullable|exists:shops,id',
            'work_attribute_id' => 'nullable|exists:work_attributes,id',
            'break_mode' => 'required|in:fixed,manual',
            'scheduled_break_minutes' => 'nullable|integer|min:0|max:600|required_if:break_mode,fixed',
        ]);

        $user->update([
            'name' => $validated['name'],
            'email' => $validated['email'] ?? null,
            'login_id' => $validated['login_id'] ?? null,
            'theme_color' => $validated['theme_color'] ?? null,
            'attendance_role' => $validated['attendance_role'] ?? null,
            'work_attribute_id' => $validated['work_attribute_id'] ?? null,
            'break_mode' => $validated['break_mode'],
            'scheduled_break_minutes' => $validated['break_mode'] === User::BREAK_MODE_FIXED
                ? ($validated['scheduled_break_minutes'] ?? 0)
                : null,
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
        $this->ensureCanManageUsers();

        $user->delete();

        return redirect()->route('admin.users.index')
            ->with('success', 'スタッフを削除しました。');
    }
}

