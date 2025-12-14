<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PhotoSlot;
use App\Models\PhotoStudio;
use App\Models\Shop;
use App\Models\Plan;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;

class PhotoSlotController extends Controller
{
    /**
     * 前撮り枠一覧を表示
     */
    public function index()
    {
        $photoSlots = PhotoSlot::with(['studio', 'customer', 'shop', 'user', 'plan'])
            ->orderBy('shoot_date', 'asc')
            ->orderBy('shoot_time', 'asc')
            ->get();

        // 編集用のマスターデータを取得
        $photoStudios = PhotoStudio::orderBy('name')->get();
        $shops = Shop::where('is_active', true)->orderBy('name')->get();
        $plans = Plan::orderBy('name')->get();
        $users = User::orderBy('name')->get();

        // 利用可能な前撮り枠（customer_idがnullのもの）を取得
        $availablePhotoSlots = PhotoSlot::whereNull('customer_id')
            ->with(['studio', 'shop', 'plan'])
            ->orderBy('shoot_date', 'asc')
            ->orderBy('shoot_time', 'asc')
            ->get();

        return Inertia::render('Admin/PhotoSlot/Index', [
            'photoSlots' => $photoSlots,
            'photoStudios' => $photoStudios,
            'shops' => $shops,
            'plans' => $plans,
            'users' => $users,
            'availablePhotoSlots' => $availablePhotoSlots,
        ]);
    }

    /**
     * 前撮り枠追加フォームを表示
     */
    public function create()
    {
        $photoStudios = PhotoStudio::orderBy('name')->get();
        $shops = Shop::where('is_active', true)->orderBy('name')->get();

        return Inertia::render('Admin/PhotoSlot/Create', [
            'photoStudios' => $photoStudios,
            'shops' => $shops,
        ]);
    }

    /**
     * 前撮り枠を保存（複数枠対応）
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'photo_studio_id' => 'required|exists:photo_studios,id',
            'shoot_date' => 'required|date',
            'shoot_times' => 'required|array|min:1',
            'shoot_times.*' => 'required|date_format:H:i',
            'shop_id' => 'nullable|exists:shops,id',
        ]);

        $createdCount = 0;
        $skippedCount = 0;
        $errors = [];

        foreach ($validated['shoot_times'] as $index => $shootTime) {
            // 同じスタジオ、日付、時間の組み合わせが既に存在するかチェック
            $existingSlot = PhotoSlot::where('photo_studio_id', $validated['photo_studio_id'])
                ->where('shoot_date', $validated['shoot_date'])
                ->where('shoot_time', $shootTime)
                ->first();

            if ($existingSlot) {
                $skippedCount++;
                $errors[] = "{$shootTime} の枠は既に存在するためスキップしました。";
                continue;
            }

            PhotoSlot::create([
                'photo_studio_id' => $validated['photo_studio_id'],
                'shoot_date' => $validated['shoot_date'],
                'shoot_time' => $shootTime,
                'shop_id' => $validated['shop_id'] ?? null,
            ]);

            $createdCount++;
        }

        $message = "{$createdCount}件の前撮り枠を追加しました。";
        if ($skippedCount > 0) {
            $message .= "（{$skippedCount}件は既存のためスキップ）";
        }

        $redirect = redirect()->route('admin.photo-slots.index')
            ->with('success', $message);
        
        if (!empty($errors)) {
            $redirect->with('slotErrors', $errors);
        }
        
        return $redirect;
    }

    /**
     * 前撮り枠を更新
     */
    public function update(Request $request, PhotoSlot $photoSlot)
    {
        $validated = $request->validate([
            'photo_slot_id' => 'required|exists:photo_slots,id',
            'shop_id' => 'nullable|exists:shops,id',
            'assignment_label' => 'nullable|string|max:255',
            'user_id' => 'nullable|exists:users,id',
            'plan_id' => 'nullable|exists:plans,id',
            'remarks' => 'nullable|string',
        ]);

        // 新しい枠を取得
        $newSlot = PhotoSlot::findOrFail($validated['photo_slot_id']);
        
        // 既に顧客が割り当てられている場合はエラー（自分自身以外）
        if ($newSlot->customer_id !== null && $newSlot->id !== $photoSlot->id) {
            return back()->withErrors([
                'photo_slot_id' => 'この前撮り枠は既に他の顧客に割り当てられています。',
            ]);
        }

        // 顧客IDを保持
        $customerId = $photoSlot->customer_id;

        // 新しい枠に顧客情報を移行
        $newSlot->update([
            'customer_id' => $customerId,
            'shop_id' => $validated['shop_id'] ?? null,
            'assignment_label' => $validated['assignment_label'] ?? null,
            'user_id' => $validated['user_id'] ?? null,
            'plan_id' => $validated['plan_id'] ?? null,
            'remarks' => $validated['remarks'] ?? null,
        ]);

        // 元の枠が異なる場合は、元の枠から顧客を解除
        if ($photoSlot->id !== $newSlot->id) {
            $photoSlot->update([
                'customer_id' => null,
                'shop_id' => null,
                'assignment_label' => null,
                'user_id' => null,
                'plan_id' => null,
                'remarks' => null,
            ]);
        }

        return redirect()->route('admin.photo-slots.index')
            ->with('success', '前撮り枠を更新しました。');
    }

    /**
     * 前撮り枠を削除
     */
    public function destroy(PhotoSlot $photoSlot)
    {
        // 顧客が紐づいている場合は削除できない
        if ($photoSlot->customer_id !== null) {
            return back()->withErrors([
                'error' => '顧客が紐づいている前撮り枠は削除できません。',
            ]);
        }

        $photoSlot->delete();

        return redirect()->route('admin.photo-slots.index')
            ->with('success', '前撮り枠を削除しました。');
    }
}

