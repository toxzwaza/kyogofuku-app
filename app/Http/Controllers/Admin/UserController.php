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
    public function index()
    {
        $users = User::with('shops')
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        return Inertia::render('Admin/User/Index', [
            'users' => $users,
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
            'email' => 'required|string|email|max:255|unique:users',
            'login_id' => 'nullable|string|max:255|unique:users',
            'password' => 'required|string|min:8|confirmed',
            'shop_ids' => 'nullable|array',
            'shop_ids.*' => 'exists:shops,id',
        ]);

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'login_id' => $validated['login_id'] ?? null,
            'password' => Hash::make($validated['password']),
        ]);

        if ($request->has('shop_ids')) {
            $user->shops()->attach($request->shop_ids);
        }

        return redirect()->route('admin.users.index')
            ->with('success', 'スタッフを追加しました。');
    }

    /**
     * スタッフ編集フォームを表示
     */
    public function edit(User $user)
    {
        $user->load('shops');
        $shops = Shop::where('is_active', true)->get();

        return Inertia::render('Admin/User/Edit', [
            'user' => $user,
            'shops' => $shops,
        ]);
    }

    /**
     * スタッフを更新
     */
    public function update(Request $request, User $user)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users,email,' . $user->id,
            'login_id' => 'nullable|string|max:255|unique:users,login_id,' . $user->id,
            'password' => 'nullable|string|min:8|confirmed',
            'shop_ids' => 'nullable|array',
            'shop_ids.*' => 'exists:shops,id',
        ]);

        $user->update([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'login_id' => $validated['login_id'] ?? null,
        ]);

        if ($request->filled('password')) {
            $user->update([
                'password' => Hash::make($validated['password']),
            ]);
        }

        if ($request->has('shop_ids')) {
            $user->shops()->sync($request->shop_ids);
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

