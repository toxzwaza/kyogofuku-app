<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\StaffSchedule;
use App\Models\Shop;
use App\Models\User;
use App\Models\TaskExpenseMapping;
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

        $query = StaffSchedule::with(['user', 'participantUsers', 'photoSlot.studio', 'photoSlot.shops']);

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
            // 店舗単位：参加者から検索
            $shop = Shop::find($shopId);
            if ($shop) {
                $currentUser = auth()->user();
                $isCurrentUserMultiShop = $currentUser && $currentUser->shops()->count() > 1;
                
                if ($isCurrentUserMultiShop) {
                    // ログインユーザーが複数店舗所属者の場合：shop_idに所属する全ユーザーで検索
                    $shopUserIds = $shop->users()->pluck('users.id')->toArray();
                } else {
                    // ログインユーザーが単一店舗所属者の場合：複数所属者はmainフラグで絞り込み
                    $shopUserIds = $shop->users()
                        ->get()
                        ->filter(function($user) use ($shopId) {
                            $userShops = $user->shops;
                            // 1店舗のみ所属している場合は含める
                            if ($userShops->count() === 1) {
                                return true;
                            }
                            // 複数店舗所属の場合、該当店舗がメイン店舗の場合のみ含める
                            $mainShop = $userShops->firstWhere('pivot.main', true);
                            return $mainShop && $mainShop->id == $shopId;
                        })
                        ->pluck('id')
                        ->toArray();
                }
                
                // 参加者のみから検索
                $query->whereHas('participantUsers', function($q) use ($shopUserIds) {
                    $q->whereIn('users.id', $shopUserIds);
                });
                
                // 店舗単位の場合は公開スケジュールのみ表示
                $query->where('is_public', true);
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
                'expense_category' => $schedule->expense_category,
                'extendedProps' => [
                    'original_start' => $schedule->start_at->toIso8601String(),
                    'original_end' => $schedule->end_at ? $schedule->end_at->toIso8601String() : $schedule->start_at->toIso8601String(),
                    'description' => $schedule->description,
                    'is_public' => $schedule->is_public ?? true,
                    'sync_to_google_calendar' => (bool) ($schedule->sync_to_google_calendar ?? false),
                    'photo_slot_id' => $schedule->photo_slot_id,
                    'photo_slot' => $schedule->photoSlot ? [
                        'id' => $schedule->photoSlot->id,
                        'studio' => $schedule->photoSlot->studio ? [
                            'id' => $schedule->photoSlot->studio->id,
                            'name' => $schedule->photoSlot->studio->name,
                        ] : null,
                        'shops' => $schedule->photoSlot->shops->map(function($shop) {
                            return [
                                'id' => $shop->id,
                                'name' => $shop->name,
                            ];
                        })->toArray(),
                    ] : null,
                    'user' => $schedule->user ? [
                        'id' => $schedule->user->id,
                        'name' => $schedule->user->name,
                    ] : null,
                    'participants' => $schedule->participantUsers->map(function($user) {
                        return [
                            'id' => $user->id,
                            'name' => $user->name,
                            'theme_color' => $user->theme_color,
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
            'is_public' => 'boolean',
            'user_id' => 'required|exists:users,id',
            'participant_ids' => 'nullable|array',
            'participant_ids.*' => 'exists:users,id',
            'expense_category' => 'nullable|string|max:255',
            'sync_to_google_calendar' => 'boolean',
        ]);

        // デフォルトのユーザーIDを設定（認証ユーザー）
        if (!isset($validated['user_id'])) {
            $validated['user_id'] = auth()->id();
        }

        // デフォルトのis_publicを設定
        if (!isset($validated['is_public'])) {
            $validated['is_public'] = true;
        }

        // デフォルトのsync_to_google_calendarを設定（ダッシュボード新規は未チェック）
        if (!isset($validated['sync_to_google_calendar'])) {
            $validated['sync_to_google_calendar'] = false;
        }

        $participantIds = $validated['participant_ids'] ?? [];
        unset($validated['participant_ids']);

        $schedule = StaffSchedule::create($validated);

        // 参加者を追加
        if (!empty($participantIds)) {
            $schedule->participantUsers()->sync($participantIds);
        }

        $schedule->load(['user', 'participantUsers']);

        // sync_to_google_calendar が有効な場合のみGoogleカレンダーへ同期
        if ($schedule->sync_to_google_calendar) {
            app(\App\Services\GoogleCalendarSyncService::class)->syncScheduleToShopCalendars($schedule);
        }

        return response()->json([
            'success' => true,
            'schedule' => [
                'id' => $schedule->id,
                'title' => $schedule->title,
                'start' => $schedule->start_at->toIso8601String(),
                'end' => $schedule->end_at->toIso8601String(),
                'allDay' => $schedule->all_day,
                'extendedProps' => [
                    'description' => $schedule->description,
                    'is_public' => $schedule->is_public ?? true,
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
            'is_public' => 'boolean',
            'user_id' => 'required|exists:users,id',
            'participant_ids' => 'nullable|array',
            'participant_ids.*' => 'exists:users,id',
            'expense_category' => 'nullable|string|max:255',
            'sync_to_google_calendar' => 'boolean',
        ]);

        $participantIds = $validated['participant_ids'] ?? [];
        unset($validated['participant_ids']);

        // sync_to_google_calendar（チェックなしの場合は送信されないため boolean で取得）
        $validated['sync_to_google_calendar'] = $request->boolean('sync_to_google_calendar');

        // end_atがnullの場合、start_atと同じ日の23:59:59を設定
        if (empty($validated['end_at'])) {
            $validated['end_at'] = \Carbon\Carbon::parse($validated['start_at'])->setTime(23, 59, 59);
        }

        // 担当者（参加者）を先に更新（Observer 発火時に正しいデータでGoogleカレンダー同期するため）
        $schedule->participantUsers()->sync($participantIds);

        $schedule->update($validated);

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
                'expense_category' => $schedule->expense_category,
                'extendedProps' => [
                    'description' => $schedule->description,
                    'is_public' => $schedule->is_public ?? true,
                    'sync_to_google_calendar' => (bool) $schedule->sync_to_google_calendar,
                    'user' => $schedule->user ? [
                        'id' => $schedule->user->id,
                        'name' => $schedule->user->name,
                    ] : null,
                    'participants' => $schedule->participantUsers->map(function($user) {
                        return [
                            'id' => $user->id,
                            'name' => $user->name,
                            'theme_color' => $user->theme_color,
                        ];
                    })->toArray(),
                ],
            ],
        ]);
    }

    /**
     * スケジュールの費用項目を更新
     */
    public function updateExpenseCategory(Request $request, StaffSchedule $schedule)
    {
        $validated = $request->validate([
            'expense_category' => 'nullable|string|max:255',
        ]);

        $schedule->update(['expense_category' => $validated['expense_category']]);

        // タスク名と費用項目の紐づけを記録
        if ($validated['expense_category'] && $schedule->title) {
            TaskExpenseMapping::recordMapping($schedule->title, $validated['expense_category']);
        }

        return response()->json([
            'success' => true,
            'expense_category' => $schedule->expense_category,
        ]);
    }

    /**
     * タスク名から費用項目を推測
     */
    public function predictExpenseCategory(Request $request)
    {
        $validated = $request->validate([
            'task_title' => 'required|string|max:255',
        ]);

        $predictedCategory = TaskExpenseMapping::predictExpenseCategory($validated['task_title']);

        return response()->json([
            'success' => true,
            'expense_category' => $predictedCategory,
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
