<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\BlockedIp;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ActivityLogController extends Controller
{
    /**
     * アクティビティログ一覧を表示
     */
    public function index(Request $request)
    {
        // アクティビティログの取得（フィルタリング対応）
        // user_idがnullのログも取得できるようにする（with()はnullでも問題なし）
        $activityLogsQuery = ActivityLog::with(['user', 'shop'])
            ->orderBy('created_at', 'desc');

        // フィルタリング
        // user_idが指定されている場合のみフィルタリング（nullのログも表示される）
        if ($request->has('user_id') && $request->user_id !== '' && $request->user_id !== null) {
            $activityLogsQuery->where('user_id', $request->user_id);
        }
        if ($request->has('shop_id') && $request->shop_id !== '' && $request->shop_id !== null) {
            $activityLogsQuery->where('shop_id', $request->shop_id);
        }
        if ($request->has('action_type') && $request->action_type) {
            $activityLogsQuery->where('action_type', $request->action_type);
        }
        if ($request->has('resource_type') && $request->resource_type) {
            $activityLogsQuery->where('resource_type', $request->resource_type);
        }
        if ($request->has('date_from') && $request->date_from) {
            $activityLogsQuery->whereDate('created_at', '>=', $request->date_from);
        }
        if ($request->has('date_to') && $request->date_to) {
            $activityLogsQuery->whereDate('created_at', '<=', $request->date_to);
        }

        $activityLogs = $activityLogsQuery->paginate(50);

        // フィルタリング用の選択肢を取得
        $filterOptions = [
            'users' => User::select('id', 'name')->orderBy('name')->get(),
            'shops' => Shop::where('is_active', true)->select('id', 'name')->orderBy('name')->get(),
            'actionTypes' => [
                ['value' => 'view', 'label' => '閲覧'],
                ['value' => 'create', 'label' => '作成'],
                ['value' => 'update', 'label' => '更新'],
                ['value' => 'delete', 'label' => '削除'],
                ['value' => 'login', 'label' => 'ログイン'],
                ['value' => 'logout', 'label' => 'ログアウト'],
                ['value' => 'login_failed', 'label' => 'ログイン失敗'],
            ],
            'resourceTypes' => [
                ['value' => 'Event', 'label' => 'イベント'],
                ['value' => 'EventReservation', 'label' => '予約'],
                ['value' => 'EventImage', 'label' => 'イベント画像'],
                ['value' => 'EventTimeslot', 'label' => '予約枠'],
                ['value' => 'Shop', 'label' => '店舗'],
                ['value' => 'User', 'label' => 'スタッフ'],
                ['value' => 'Venue', 'label' => '会場'],
                ['value' => 'Customer', 'label' => '顧客'],
                ['value' => 'ReservationNote', 'label' => '予約メモ'],
                ['value' => 'CustomerNote', 'label' => '顧客メモ'],
            ],
        ];

        // ブロックされているIPアドレスの一覧を取得（blocked_ipsテーブルから）
        $blockedIps = BlockedIp::where('is_active', true)
            ->orderBy('blocked_at', 'desc')
            ->get()
            ->map(function ($item) {
                return [
                    'id' => $item->id,
                    'ip_address' => $item->ip_address,
                    'failure_count' => $item->failure_count,
                    'last_failed_at' => $item->last_failed_at,
                    'blocked_at' => $item->blocked_at,
                ];
            });

        return Inertia::render('Admin/ActivityLog/Index', [
            'activityLogs' => $activityLogs,
            'filterOptions' => $filterOptions,
            'blockedIps' => $blockedIps,
            'filters' => [
                'user_id' => $request->user_id,
                'shop_id' => $request->shop_id,
                'action_type' => $request->action_type,
                'resource_type' => $request->resource_type,
                'date_from' => $request->date_from,
                'date_to' => $request->date_to,
            ],
        ]);
    }

    /**
     * ログ詳細を表示
     */
    public function show(ActivityLog $activityLog)
    {
        $activityLog->load(['user', 'shop']);
        
        // このIPアドレスがブロックされているかチェック
        $blockedIp = null;
        if ($activityLog->ip_address && $activityLog->action_type === 'login_failed') {
            $blockedIp = BlockedIp::where('ip_address', $activityLog->ip_address)
                ->where('is_active', true)
                ->first();
        }

        return Inertia::render('Admin/ActivityLog/Show', [
            'activityLog' => $activityLog,
            'blockedIp' => $blockedIp,
        ]);
    }

    /**
     * IPアドレスのブロックを解除
     */
    public function unblockIp(Request $request, string $ipAddress)
    {
        $blockedIp = BlockedIp::where('ip_address', $ipAddress)
            ->where('is_active', true)
            ->first();

        if (!$blockedIp) {
            return redirect()->back()->with('error', 'ブロックされているIPアドレスが見つかりませんでした。');
        }

        $blockedIp->update([
            'is_active' => false,
            'unblocked_at' => now(),
            'unblocked_by_user_id' => auth()->id(),
            'unblock_reason' => $request->input('reason', '管理者による手動解除'),
        ]);

        // ログ詳細画面から来た場合は、ログ詳細画面に戻る
        if ($request->has('log_id')) {
            return redirect()->route('admin.activity-logs.show', $request->input('log_id'))
                ->with('success', "IPアドレス {$ipAddress} のブロックを解除しました。");
        }

        return redirect()->back()->with('success', "IPアドレス {$ipAddress} のブロックを解除しました。");
    }
}

