<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\StaffSchedule;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Http\Request;
use Carbon\Carbon;
use Inertia\Inertia;

class ScheduleController extends Controller
{
    /**
     * スケジュール管理ページを表示
     */
    public function show()
    {
        $shops = Shop::where('is_active', true)->orderBy('name')->get();
        $currentUser = auth()->user();
        $userShops = $currentUser ? $currentUser->shops()
            ->where('shops.is_active', true)
            ->select('shops.id', 'shops.name')
            ->orderBy('shops.name')
            ->get() : collect();

        return Inertia::render('Admin/Schedule/Index', [
            'shops' => $shops,
            'currentUser' => $currentUser ? [
                'id' => $currentUser->id,
                'name' => $currentUser->name,
            ] : null,
            'userShops' => $userShops->map(function($shop) {
                return [
                    'id' => $shop->id,
                    'name' => $shop->name,
                ];
            }),
        ]);
    }

    /**
     * 店舗に紐づくユーザー一覧を取得
     */
    public function getShopUsers(Request $request)
    {
        $shopId = $request->input('shop_id');
        
        if (!$shopId) {
            return response()->json([]);
        }

        $shop = Shop::with('users')->find($shopId);
        
        if (!$shop) {
            return response()->json([]);
        }

        $users = $shop->users()->orderBy('name')->get()->map(function($user) {
            return [
                'id' => $user->id,
                'name' => $user->name,
            ];
        });

        return response()->json($users);
    }

    /**
     * スケジュール一覧を取得（カレンダー用API）
     */
    public function index(Request $request)
    {
        $start = $request->input('start');
        $end = $request->input('end');
        $mode = $request->input('mode', 'shop'); // 'shop' or 'user'
        $shopId = $request->input('shop_id');
        $userId = $request->input('user_id');

        $query = StaffSchedule::with(['user', 'participantUsers']);

        // 日付範囲でフィルタリング
        if ($start && $end) {
            $query->where(function($q) use ($start, $end) {
                $q->whereBetween('start_at', [$start, $end])
                  ->orWhereBetween('end_at', [$start, $end])
                  ->orWhere(function($q2) use ($start, $end) {
                      $q2->where('start_at', '<=', $start)
                         ->where('end_at', '>=', $end);
                  });
            });
        }

        // モードに応じたフィルタリング
        if ($mode === 'shop' && $shopId) {
            // 店舗単位：特定の店舗に所属するユーザーのスケジュールを取得
            $shop = Shop::find($shopId);
            if ($shop) {
                $shopUserIds = $shop->users()->pluck('users.id');
                $query->whereIn('user_id', $shopUserIds);
            }
        } elseif ($mode === 'user' && $userId) {
            // ユーザー単位：特定のユーザーが参加者として登録されているスケジュールを取得
            $query->whereHas('participantUsers', function($q) use ($userId) {
                $q->where('users.id', $userId);
            });
        }

        $schedules = $query->get()->map(function($schedule) {
            return [
                'id' => $schedule->id,
                'title' => $schedule->title,
                'start' => $schedule->start_at->toIso8601String(),
                'end' => $schedule->end_at ? $schedule->end_at->toIso8601String() : $schedule->start_at->toIso8601String(),
                'allDay' => $schedule->all_day,
                'backgroundColor' => $schedule->color,
                'borderColor' => $schedule->color,
                'extendedProps' => [
                    'description' => $schedule->description,
                    'user' => $schedule->user ? [
                        'id' => $schedule->user->id,
                        'name' => $schedule->user->name,
                    ] : null,
                    'participants' => $schedule->participantUsers->map(function($user) {
                        return [
                            'id' => $user->id,
                            'name' => $user->name,
                        ];
                    })->toArray(),
                ],
            ];
        });

        return response()->json($schedules);
    }

    /**
     * スケジュールを保存
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'start_at' => 'required|date',
            'end_at' => 'required|date|after_or_equal:start_at',
            'all_day' => 'boolean',
            'color' => 'nullable|string|max:7',
            'user_id' => 'required|exists:users,id',
            'participant_ids' => 'nullable|array',
            'participant_ids.*' => 'exists:users,id',
        ]);

        // デフォルトのユーザーIDを設定（認証ユーザー）
        if (!isset($validated['user_id'])) {
            $validated['user_id'] = auth()->id();
        }

        // デフォルトの色を設定
        if (!isset($validated['color'])) {
            $validated['color'] = '#3788d8';
        }

        $participantIds = $validated['participant_ids'] ?? [];
        unset($validated['participant_ids']);

        $schedule = StaffSchedule::create($validated);

        // 参加者を追加
        if (!empty($participantIds)) {
            $schedule->participantUsers()->sync($participantIds);
        }

        $schedule->load(['user', 'participantUsers']);

        return response()->json([
            'success' => true,
            'schedule' => [
                'id' => $schedule->id,
                'title' => $schedule->title,
                'start' => $schedule->start_at->toIso8601String(),
                'end' => $schedule->end_at->toIso8601String(),
                'allDay' => $schedule->all_day,
                'backgroundColor' => $schedule->color,
                'borderColor' => $schedule->color,
                'extendedProps' => [
                    'description' => $schedule->description,
                    'user' => $schedule->user ? [
                        'id' => $schedule->user->id,
                        'name' => $schedule->user->name,
                    ] : null,
                    'shop' => $schedule->shop ? [
                        'id' => $schedule->shop->id,
                        'name' => $schedule->shop->name,
                    ] : null,
                ],
            ],
        ]);
    }

    /**
     * スケジュールを更新
     */
    public function update(Request $request, StaffSchedule $schedule)
    {
        // 空文字列をnullに変換
        $request->merge([
            'end_at' => $request->end_at ?: null,
        ]);

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'start_at' => 'required|date',
            'end_at' => 'nullable|date|after_or_equal:start_at',
            'all_day' => 'boolean',
            'color' => 'nullable|string|max:7',
            'user_id' => 'required|exists:users,id',
            'participant_ids' => 'nullable|array',
            'participant_ids.*' => 'exists:users,id',
        ]);

        $participantIds = $validated['participant_ids'] ?? [];
        unset($validated['participant_ids']);

        // end_atがnullの場合、start_atと同じ日の23:59:59を設定
        if (empty($validated['end_at'])) {
            $validated['end_at'] = \Carbon\Carbon::parse($validated['start_at'])->setTime(23, 59, 59);
        }

        $schedule->update($validated);

        // 参加者を更新
        $schedule->participantUsers()->sync($participantIds);

        // photo_slot_idが紐づいている場合、同じ日付・スタジオの全ての前撮り枠の日付も更新
        if ($schedule->photo_slot_id) {
            $photoSlot = \App\Models\PhotoSlot::find($schedule->photo_slot_id);
            if ($photoSlot) {
                $originalDate = $photoSlot->shoot_date;
                $originalStudioId = $photoSlot->photo_studio_id;
                $newDate = \Carbon\Carbon::parse($validated['start_at'])->format('Y-m-d');
                
                // 同じ日付・スタジオの全ての前撮り枠を更新
                \App\Models\PhotoSlot::where('shoot_date', $originalDate)
                    ->where('photo_studio_id', $originalStudioId)
                    ->update(['shoot_date' => $newDate]);
            }
        }

        $schedule->load(['user', 'participantUsers']);

        return response()->json([
            'success' => true,
            'schedule' => [
                'id' => $schedule->id,
                'title' => $schedule->title,
                'start' => $schedule->start_at->toIso8601String(),
                'end' => $schedule->end_at->toIso8601String(),
                'allDay' => $schedule->all_day,
                'backgroundColor' => $schedule->color,
                'borderColor' => $schedule->color,
                'extendedProps' => [
                    'description' => $schedule->description,
                    'user' => $schedule->user ? [
                        'id' => $schedule->user->id,
                        'name' => $schedule->user->name,
                    ] : null,
                    'participants' => $schedule->participantUsers->map(function($user) {
                        return [
                            'id' => $user->id,
                            'name' => $user->name,
                        ];
                    })->toArray(),
                ],
            ],
        ]);
    }

    /**
     * スケジュールを削除
     */
    public function destroy(StaffSchedule $schedule)
    {
        $schedule->delete();

        return response()->json([
            'success' => true,
        ]);
    }
}
