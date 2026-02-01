<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PhotoSlot;
use App\Models\PhotoStudio;
use App\Models\Shop;
use App\Models\Plan;
use App\Models\User;
use App\Models\StaffSchedule;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
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

        // スケジュール登録済みの前撮り枠IDを取得
        $scheduledSlotIds = StaffSchedule::whereNotNull('photo_slot_id')
            ->pluck('photo_slot_id')
            ->toArray();

        return Inertia::render('Admin/PhotoSlot/Index', [
            'photoSlots' => $photoSlots,
            'photoStudios' => $photoStudios,
            'shops' => $shops,
            'plans' => $plans,
            'users' => $users,
            'availablePhotoSlots' => $availablePhotoSlots,
            'scheduledSlotIds' => $scheduledSlotIds,
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
        $firstCreatedSlot = null;

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

            // 最初に作成した枠を保存（スケジュール作成用）
            if ($firstCreatedSlot === null) {
                $firstCreatedSlot = $photoSlot;
            }

            $createdCount++;
        }

        // スタッフスケジュールを1つだけ作成（枠が1つ以上作成された場合）
        if ($firstCreatedSlot !== null) {
            $firstCreatedSlot->load(['studio', 'shops.users']);
            $studioName = $firstCreatedSlot->studio ? $firstCreatedSlot->studio->name : '未設定';
            
            $shootDate = Carbon::parse($firstCreatedSlot->shoot_date);
            
            // 選択された時間の中で一番早い時間と一番遅い時間を取得
            $sortedTimes = collect($validated['shoot_times'])->sort()->values();
            $earliestTime = $sortedTimes->first();
            $latestTime = $sortedTimes->last();
            
            // 一番早い時間をstart_atに設定
            [$startHour, $startMinute] = explode(':', $earliestTime);
            $startAt = $shootDate->copy()->setTime((int)$startHour, (int)$startMinute, 0);
            
            // 一番遅い時間をend_atに設定
            [$endHour, $endMinute] = explode(':', $latestTime);
            $endAt = $shootDate->copy()->setTime((int)$endHour, (int)$endMinute, 0);
            
            $schedule = StaffSchedule::create([
                'user_id' => $request->user()->id,
                'photo_slot_id' => $firstCreatedSlot->id,
                'title' => '[前撮り]' . $studioName,
                'start_at' => $startAt,
                'end_at' => $endAt,
                'all_day' => true,
            ]);

            // 担当店舗に所属するスタッフ全員を参加者として登録
            $participantUserIds = [];
            foreach ($firstCreatedSlot->shops as $shop) {
                foreach ($shop->users as $user) {
                    $participantUserIds[] = $user->id;
                }
            }
            if (!empty($participantUserIds)) {
                $schedule->participantUsers()->sync(array_unique($participantUserIds));
            }

            app(\App\Services\GoogleCalendarSyncService::class)->syncScheduleToShopCalendars($schedule);
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

            // 紐づくスタッフスケジュールの日付も更新
            $newShootDate = Carbon::parse($validated['shoot_date']);
            StaffSchedule::where('photo_slot_id', $photoSlot->id)
                ->update([
                    'start_at' => $newShootDate->copy()->setTime(0, 0, 1),
                    'end_at' => $newShootDate->copy()->setTime(23, 59, 59),
                ]);

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

    public function createSchedule(Request $request)
    {
        try {
            $validated = $request->validate([
                'photo_slot_id' => 'required|exists:photo_slots,id',
            ]);

            $photoSlot = PhotoSlot::with(['studio', 'shops.users'])->findOrFail($validated['photo_slot_id']);

            // 既にスケジュールが存在するか確認
            $existingSchedule = StaffSchedule::where('photo_slot_id', $photoSlot->id)->first();
            if ($existingSchedule) {
                return response()->json([
                    'success' => false,
                    'message' => 'この前撮り枠には既にスケジュールが登録されています。',
                ], 400);
            }

            // shoot_dateが存在するか確認
            if (!$photoSlot->shoot_date) {
                return response()->json([
                    'success' => false,
                    'message' => '前撮り枠の撮影日が設定されていません。',
                ], 400);
            }

            // 認証ユーザーの確認
            $user = $request->user();
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => '認証が必要です。',
                ], 401);
            }

            $studioName = $photoSlot->studio ? $photoSlot->studio->name : '未設定';
            $shootDate = Carbon::parse($photoSlot->shoot_date);

            $schedule = StaffSchedule::create([
                'user_id' => $user->id,
                'photo_slot_id' => $photoSlot->id,
                'title' => '[前撮り]' . $studioName,
                'start_at' => $shootDate->copy()->setTime(0, 0, 1),
                'end_at' => $shootDate->copy()->setTime(23, 59, 59),
                'all_day' => true,
                'is_public' => true,
            ]);

            // 担当店舗に所属するスタッフ全員を参加者として登録
            $participantUserIds = [];
            foreach ($photoSlot->shops as $shop) {
                foreach ($shop->users as $user) {
                    $participantUserIds[] = $user->id;
                }
            }
            if (!empty($participantUserIds)) {
                $schedule->participantUsers()->sync(array_unique($participantUserIds));
            }

            app(\App\Services\GoogleCalendarSyncService::class)->syncScheduleToShopCalendars($schedule);

            return response()->json([
                'success' => true,
                'message' => 'スケジュールを作成しました。',
                'schedule_id' => $schedule->id,
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'バリデーションエラー: ' . $e->getMessage(),
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            Log::error('スケジュール作成エラー: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
                'photo_slot_id' => $request->input('photo_slot_id'),
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'スケジュールの作成に失敗しました: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * 個別時間枠の担当者スケジュールを作成（顧客が紐づいている場合）
     */
    public function createSlotSchedule(Request $request)
    {
        try {
            $validated = $request->validate([
                'photo_slot_id' => 'required|exists:photo_slots,id',
            ]);

            $photoSlot = PhotoSlot::with(['customer', 'user', 'studio'])->findOrFail($validated['photo_slot_id']);

            // 顧客が紐づいていない場合はエラー
            if (!$photoSlot->customer) {
                return response()->json([
                    'success' => false,
                    'message' => '顧客が紐づいていない前撮り枠にはスケジュールを登録できません。',
                ], 400);
            }

            // 担当者が設定されていない場合はエラー
            if (!$photoSlot->user) {
                return response()->json([
                    'success' => false,
                    'message' => '担当者が設定されていない前撮り枠にはスケジュールを登録できません。',
                ], 400);
            }

            // 既にスケジュールが存在するか確認
            $existingSchedule = StaffSchedule::where('photo_slot_id', $photoSlot->id)->first();
            if ($existingSchedule) {
                return response()->json([
                    'success' => false,
                    'message' => 'この前撮り枠には既にスケジュールが登録されています。',
                ], 400);
            }

            // shoot_dateが存在するか確認
            if (!$photoSlot->shoot_date) {
                return response()->json([
                    'success' => false,
                    'message' => '前撮り枠の撮影日が設定されていません。',
                ], 400);
            }

            $customerName = $photoSlot->customer->name;
            $shootDate = Carbon::parse($photoSlot->shoot_date);
            
            // 撮影時間を取得してstart_atとend_atを設定
            $shootTime = Carbon::parse($photoSlot->shoot_time);
            $startAt = $shootDate->copy()->setTime($shootTime->hour, $shootTime->minute, 0);
            // 30分後をend_atとする
            $endAt = $startAt->copy()->addMinutes(30);

            $schedule = StaffSchedule::create([
                'user_id' => $photoSlot->user->id, // 担当者をuser_idに設定
                'photo_slot_id' => $photoSlot->id,
                'title' => '[前撮り]' . $customerName,
                'start_at' => $startAt,
                'end_at' => $endAt,
                'all_day' => false,
                'is_public' => true,
            ]);

            // 担当者を参加者として追加
            $schedule->participantUsers()->sync([$photoSlot->user->id]);

            app(\App\Services\GoogleCalendarSyncService::class)->syncScheduleToShopCalendars($schedule);

            return response()->json([
                'success' => true,
                'message' => 'スケジュールを作成しました。',
                'schedule_id' => $schedule->id,
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'バリデーションエラー: ' . $e->getMessage(),
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            Log::error('個別スケジュール作成エラー: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
                'photo_slot_id' => $request->input('photo_slot_id'),
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'スケジュールの作成に失敗しました: ' . $e->getMessage(),
            ], 500);
        }
    }
}

