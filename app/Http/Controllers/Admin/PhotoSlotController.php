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
        $photoSlots = PhotoSlot::with(['studio', 'customer', 'shops', 'user', 'plan'])
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
            ->with(['studio', 'shops', 'plan'])
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

        // 既存のphoto_slot_shopに紐づいているスタジオ情報を取得
        $photoSlotsWithShops = PhotoSlot::with(['studio', 'shops'])
            ->whereHas('shops')
            ->get();

        // ログインユーザーの所属店舗を取得
        $currentUser = auth()->user();
        $userShops = $currentUser ? $currentUser->shops()
            ->where('shops.is_active', true)
            ->select('shops.id', 'shops.name')
            ->orderBy('shops.name')
            ->get() : collect();

        return Inertia::render('Admin/PhotoSlot/Create', [
            'photoStudios' => $photoStudios,
            'shops' => $shops,
            'photoSlotsWithShops' => $photoSlotsWithShops,
            'userShops' => $userShops,
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
            'shop_ids' => 'nullable|array',
            'shop_ids.*' => 'exists:shops,id',
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

            $photoSlot = PhotoSlot::create([
                'photo_studio_id' => $validated['photo_studio_id'],
                'shoot_date' => $validated['shoot_date'],
                'shoot_time' => $shootTime,
            ]);

            // 担当店舗を中間テーブルに保存
            if (!empty($validated['shop_ids'])) {
                $photoSlot->shops()->attach($validated['shop_ids']);
            }

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
            'shop_ids' => 'nullable|array',
            'shop_ids.*' => 'exists:shops,id',
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
            'assignment_label' => $validated['assignment_label'] ?? null,
            'user_id' => $validated['user_id'] ?? null,
            'plan_id' => $validated['plan_id'] ?? null,
            'remarks' => $validated['remarks'] ?? null,
        ]);

        // 担当店舗を更新（中間テーブル）
        if (isset($validated['shop_ids'])) {
            $newSlot->shops()->sync($validated['shop_ids']);
        } else {
            $newSlot->shops()->detach();
        }

        // 元の枠が異なる場合は、元の枠から顧客を解除
        if ($photoSlot->id !== $newSlot->id) {
            $photoSlot->update([
                'customer_id' => null,
                'assignment_label' => null,
                'user_id' => null,
                'plan_id' => null,
                'remarks' => null,
            ]);
            $photoSlot->shops()->detach();
        }

        return redirect()->route('admin.photo-slots.index')
            ->with('success', '前撮り枠を更新しました。');
    }

    /**
     * 前撮り枠を一括更新（日付・担当店舗・スタジオ）
     */
    public function bulkUpdate(Request $request)
    {
        $validated = $request->validate([
            'slot_ids' => 'required|array|min:1',
            'slot_ids.*' => 'exists:photo_slots,id',
            'shoot_date' => 'required|date',
            'photo_studio_id' => 'required|exists:photo_studios,id',
            'shop_ids' => 'nullable|array',
            'shop_ids.*' => 'exists:shops,id',
        ]);

        $updatedCount = 0;
        $skippedCount = 0;
        $errors = [];

        foreach ($validated['slot_ids'] as $slotId) {
            $photoSlot = PhotoSlot::findOrFail($slotId);
            
            // 顧客が紐づいている場合はスキップ
            if ($photoSlot->customer_id !== null) {
                $skippedCount++;
                $errors[] = "ID {$slotId} の枠は顧客が紐づいているためスキップしました。";
                continue;
            }

            // 同じスタジオ、日付、時間の組み合わせが既に存在するかチェック（自分自身以外）
            $existingSlot = PhotoSlot::where('photo_studio_id', $validated['photo_studio_id'])
                ->where('shoot_date', $validated['shoot_date'])
                ->where('shoot_time', $photoSlot->shoot_time)
                ->where('id', '!=', $photoSlot->id)
                ->first();

            if ($existingSlot) {
                $skippedCount++;
                $errors[] = "ID {$slotId} の枠（{$photoSlot->shoot_time}）は既に同じ時間枠が存在するためスキップしました。";
                continue;
            }

            // 日付とスタジオを更新
            $photoSlot->update([
                'shoot_date' => $validated['shoot_date'],
                'photo_studio_id' => $validated['photo_studio_id'],
            ]);

            // 担当店舗を更新（中間テーブル）
            if (isset($validated['shop_ids'])) {
                $photoSlot->shops()->sync($validated['shop_ids']);
            } else {
                $photoSlot->shops()->detach();
            }

            $updatedCount++;
        }

        $message = "{$updatedCount}件の前撮り枠を更新しました。";
        if ($skippedCount > 0) {
            $message .= "（{$skippedCount}件はスキップ）";
        }

        $redirect = redirect()->route('admin.photo-slots.index')
            ->with('success', $message);
        
        if (!empty($errors)) {
            $redirect->with('slotErrors', $errors);
        }
        
        return $redirect;
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

