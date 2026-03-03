<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AttendanceBreak;
use App\Models\AttendanceRecord;
use App\Models\Shop;
use App\Models\User;
use App\Services\AttendanceScopeService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AttendanceController extends Controller
{
    /**
     * 勤怠管理一覧（管理者: 自店舗 / 勤怠管理者: 全店舗）
     */
    public function index(Request $request)
    {
        $user = $request->user();
        if (!AttendanceScopeService::canAccessManagement($user)) {
            abort(403);
        }

        // デフォルト: 前月21日〜今月20日
        $now = Carbon::now();
        $defaultFrom = $now->copy()->subMonth()->day(21)->format('Y-m-d');
        $defaultTo = $now->copy()->day(20)->format('Y-m-d');
        $from = $request->filled('from') ? $request->from : $defaultFrom;
        $to = $request->filled('to') ? $request->to : $defaultTo;

        $query = AttendanceRecord::with(['user:id,name', 'shop:id,name', 'breaks']);
        $query = AttendanceScopeService::scopeForUser($query, $user);

        if ($request->filled('shop_id')) {
            $query->where('shop_id', $request->shop_id);
        }
        if ($request->filled('user_id')) {
            $query->where('user_id', $request->user_id);
        }
        $query->whereDate('date', '>=', $from);
        $query->whereDate('date', '<=', $to);

        $records = $query->orderBy('date')->orderBy('created_at')->get();

        $shopIds = $user->shops()->pluck('shops.id');
        $shops = $user->isAttendanceManager()
            ? Shop::where('is_active', true)->orderBy('name')->get(['id', 'name'])
            : Shop::whereIn('id', $shopIds)->where('is_active', true)->orderBy('name')->get(['id', 'name']);

        $userIds = $user->isAttendanceManager()
            ? User::orderBy('name')->pluck('id')->toArray()
            : User::whereHas('shops', fn ($q) => $q->whereIn('shops.id', $shopIds))->orderBy('name')->pluck('id')->toArray();
        $users = User::whereIn('id', $userIds)->orderBy('name')->get(['id', 'name']);

        // 店舗ごとのユーザー一覧（絞り込み用）
        $usersByShop = [];
        foreach ($shops as $shop) {
            $usersByShop[$shop->id] = User::whereHas('shops', fn ($q) => $q->where('shops.id', $shop->id))
                ->whereIn('id', $userIds)
                ->orderBy('name')
                ->get(['id', 'name'])
                ->values()
                ->all();
        }

        return Inertia::render('Admin/Attendance/Index', [
            'records' => ['data' => $records->values()->all()],
            'shops' => $shops,
            'users' => $users,
            'usersByShop' => $usersByShop,
            'filters' => [
                'shop_id' => $request->shop_id,
                'user_id' => $request->user_id,
                'from' => $from,
                'to' => $to,
            ],
        ]);
    }

    /**
     * 勤怠レコードの編集（開始・終了時刻、休憩の変更・追加・削除）
     */
    public function update(Request $request, AttendanceRecord $record)
    {
        $user = $request->user();
        if (!AttendanceScopeService::canAccessManagement($user)) {
            abort(403);
        }
        if (!AttendanceScopeService::canApproveRecord($user, $record)) {
            abort(403);
        }

        $validated = $request->validate([
            'clock_in_at' => 'nullable|date',
            'clock_out_at' => ['nullable', 'date', function ($attr, $val, $fail) use ($request) {
                if ($val && $request->clock_in_at && Carbon::parse($val)->lt(Carbon::parse($request->clock_in_at))) {
                    $fail('退勤時刻は出勤時刻より後である必要があります。');
                }
            }],
            'breaks' => 'nullable|array',
            'breaks.*.start_at' => 'nullable|date',
            'breaks.*.end_at' => 'nullable|date',
        ]);

        $clockInAt = isset($validated['clock_in_at']) ? Carbon::parse($validated['clock_in_at']) : null;
        $clockOutAt = isset($validated['clock_out_at']) ? Carbon::parse($validated['clock_out_at']) : null;

        if ($clockInAt && $clockOutAt && $clockOutAt->lte($clockInAt)) {
            return redirect()->route('admin.attendance.index', $request->only(['shop_id', 'user_id', 'from', 'to']))
                ->withErrors(['clock_out_at' => '退勤時刻は出勤時刻より後である必要があります。']);
        }

        DB::transaction(function () use ($record, $clockInAt, $clockOutAt, $validated) {
            $record->update([
                'clock_in_at' => $clockInAt,
                'clock_out_at' => $clockOutAt,
                'version' => $record->version + 1,
            ]);

            $record->breaks()->delete();
            $breaks = $validated['breaks'] ?? [];
            foreach ($breaks as $b) {
                if (!empty($b['start_at'])) {
                    $start = Carbon::parse($b['start_at']);
                    $end = !empty($b['end_at']) ? Carbon::parse($b['end_at']) : null;
                    AttendanceBreak::create([
                        'attendance_record_id' => $record->id,
                        'start_at' => $start,
                        'end_at' => $end,
                    ]);
                }
            }
        });

        return redirect()->route('admin.attendance.index', $request->only(['shop_id', 'user_id', 'from', 'to']))
            ->with('success', '勤怠を更新しました。');
    }

    /**
     * 勤怠データをCSVでエクスポート（選択ユーザー・現在の絞り込み条件）
     */
    public function exportCsv(Request $request): StreamedResponse
    {
        $user = $request->user();
        if (!AttendanceScopeService::canAccessManagement($user)) {
            abort(403);
        }

        $now = Carbon::now();
        $defaultFrom = $now->copy()->subMonth()->day(21)->format('Y-m-d');
        $defaultTo = $now->copy()->day(20)->format('Y-m-d');
        $from = $request->filled('from') ? $request->from : $defaultFrom;
        $to = $request->filled('to') ? $request->to : $defaultTo;

        $query = AttendanceRecord::with(['user:id,name', 'shop:id,name', 'breaks']);
        $query = AttendanceScopeService::scopeForUser($query, $user);

        if ($request->filled('shop_id')) {
            $query->where('shop_id', $request->shop_id);
        }
        $userIds = $request->input('user_ids', []);
        if (is_array($userIds) && count($userIds) > 0) {
            $userIds = array_filter(array_map('intval', $userIds));
            $query->whereIn('user_id', $userIds);
        } elseif ($request->filled('user_id')) {
            $query->where('user_id', $request->user_id);
        }
        $query->whereDate('date', '>=', $from)->whereDate('date', '<=', $to);
        $records = $query->orderBy('date')->orderBy('created_at')->get();

        $statusLabels = [
            'draft' => '未申請',
            'applied' => '申請済',
            'approved' => '承認済',
        ];

        $filename = 'attendance_' . $now->format('YmdHis') . '.csv';

        return new StreamedResponse(function () use ($records, $statusLabels) {
            $stream = fopen('php://output', 'w');
            fprintf($stream, chr(0xEF) . chr(0xBB) . chr(0xBF));
            fputcsv($stream, ['日付', 'ユーザー名', '店舗', 'ステータス', '出勤', '退勤', '休憩']);
            foreach ($records as $r) {
                $dateStr = $r->date ? $r->date->format('Y-m-d') : '';
                $clockIn = $r->clock_in_at ? $r->clock_in_at->format('H:i') : '';
                $clockOut = $r->clock_out_at ? $r->clock_out_at->format('H:i') : '';
                $breaksStr = $r->breaks->map(function ($b) {
                    $s = $b->start_at ? $b->start_at->format('H:i') : '';
                    $e = $b->end_at ? $b->end_at->format('H:i') : '';
                    return $s . '-' . $e;
                })->filter()->join(', ');
                fputcsv($stream, [
                    $dateStr,
                    $r->user->name ?? '',
                    $r->shop->name ?? '',
                    $statusLabels[$r->status] ?? $r->status,
                    $clockIn,
                    $clockOut,
                    $breaksStr,
                ]);
            }
            fclose($stream);
        }, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }
}
