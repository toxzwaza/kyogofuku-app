<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AttendanceBreak;
use App\Models\AttendanceLeave;
use App\Models\AttendancePayrollSetting;
use App\Models\AttendanceRecord;
use App\Models\Shop;
use App\Models\User;
use App\Services\AttendancePayrollTimeService;
use App\Services\AttendanceScopeService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Concerns\ResolvesUiView;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AttendanceController extends Controller
{
    use ResolvesUiView;

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

        $query = AttendanceRecord::with([
            'user:id,name,work_attribute_id',
            'shop:id,name',
            'breaks',
        ]);
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

        $payrollService = app(AttendancePayrollTimeService::class);
        $patterns = $payrollService->loadCalendarPatternsBetween($from, $to);
        $payrollSetting = AttendancePayrollSetting::query()->orderBy('id')->firstOrFail();

        $enriched = $records->map(function (AttendanceRecord $r) use ($payrollService, $patterns, $payrollSetting) {
            $arr = $r->toArray();
            $arr['payroll'] = $payrollService->payrollPayloadForRecord($r, $patterns, $payrollSetting);
            // 月次集計の各列に寄与する1日分の指標（サマリ行・詳細モーダルの根拠データ用）
            $arr['metrics'] = $payrollService->monthlyMetricsForRecord($r, $patterns, $payrollSetting);

            return $arr;
        });

        $shopIds = $user->shops()->pluck('shops.id');
        $shops = $user->isAttendanceManager()
            ? Shop::where('is_active', true)->orderBy('name')->get(['id', 'name'])
            : Shop::whereIn('id', $shopIds)->where('is_active', true)->orderBy('name')->get(['id', 'name']);

        $userIds = $user->isAttendanceManager()
            ? User::orderBy('name')->pluck('id')->toArray()
            : User::whereHas('shops', fn ($q) => $q->whereIn('shops.id', $shopIds))->orderBy('name')->pluck('id')->toArray();
        $users = User::whereIn('id', $userIds)->orderBy('name')->get(['id', 'name']);

        // 期間内の休暇（有給/特別休暇/欠勤）を取得（スコープ内ユーザーのみ）
        $leaves = AttendanceLeave::whereIn('user_id', $userIds)
            ->whereDate('date', '>=', $from)
            ->whereDate('date', '<=', $to)
            ->orderBy('date')
            ->get(['id', 'user_id', 'date', 'leave_type', 'note'])
            ->map(function (AttendanceLeave $l) {
                return [
                    'id' => $l->id,
                    'user_id' => $l->user_id,
                    'date' => $l->date instanceof Carbon ? $l->date->toDateString() : (string) $l->date,
                    'leave_type' => $l->leave_type,
                    'note' => $l->note,
                ];
            })
            ->values()
            ->all();

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

        return Inertia::render($this->viewFor('Admin/Attendance/Index'), [
            'records' => ['data' => $enriched->values()->all()],
            'shops' => $shops,
            'users' => $users,
            'usersByShop' => $usersByShop,
            'leaves' => $leaves,
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
            'pattern_override' => 'nullable|in:A,B,C',
            'substitute_for_date' => 'nullable|date',
        ]);

        $clockInAt = isset($validated['clock_in_at']) ? Carbon::parse($validated['clock_in_at']) : null;
        $clockOutAt = isset($validated['clock_out_at']) ? Carbon::parse($validated['clock_out_at']) : null;
        $patternOverride = $validated['pattern_override'] ?? null;
        $substituteForDate = !empty($validated['substitute_for_date']) ? Carbon::parse($validated['substitute_for_date'])->toDateString() : null;

        if ($clockInAt && $clockOutAt && $clockOutAt->lte($clockInAt)) {
            return redirect()->route('admin.attendance.index', $request->only(['shop_id', 'user_id', 'from', 'to']))
                ->withErrors(['clock_out_at' => '退勤時刻は出勤時刻より後である必要があります。']);
        }

        DB::transaction(function () use ($record, $clockInAt, $clockOutAt, $patternOverride, $substituteForDate, $validated) {
            $record->update([
                'clock_in_at' => $clockInAt,
                'clock_out_at' => $clockOutAt,
                'pattern_override' => $patternOverride,
                'substitute_for_date' => $substituteForDate,
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
     * 勤怠レコードの承認（未申請・申請済を承認済にする）
     */
    public function approve(Request $request, AttendanceRecord $record)
    {
        $user = $request->user();
        if (!AttendanceScopeService::canAccessManagement($user)) {
            abort(403);
        }
        if (!AttendanceScopeService::canApproveRecord($user, $record)) {
            abort(403);
        }
        if ($record->isApproved()) {
            return redirect()->route('admin.attendance.index', $request->only(['shop_id', 'user_id', 'from', 'to']))
                ->withErrors(['record' => 'すでに承認済みです。']);
        }

        DB::transaction(function () use ($record, $user) {
            $record->update([
                'status' => AttendanceRecord::STATUS_APPROVED,
                'approved_at' => Carbon::now(),
                'approved_by' => $user->id,
            ]);
        });

        return redirect()->route('admin.attendance.index', $request->only(['shop_id', 'user_id', 'from', 'to']))
            ->with('success', '承認しました。');
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

        $query = AttendanceRecord::with([
            'user:id,name,work_attribute_id',
            'shop:id,name',
            'breaks',
        ]);
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

        $payrollService = app(AttendancePayrollTimeService::class);
        $patterns = $payrollService->loadCalendarPatternsBetween($from, $to);
        $payrollSetting = AttendancePayrollSetting::query()->orderBy('id')->firstOrFail();

        $filename = 'attendance_' . $now->format('YmdHis') . '.csv';

        return new StreamedResponse(function () use ($records, $statusLabels, $payrollService, $patterns, $payrollSetting) {
            $stream = fopen('php://output', 'w');
            fprintf($stream, chr(0xEF) . chr(0xBB) . chr(0xBF));
            fputcsv($stream, [
                '日付',
                'ユーザー名',
                '店舗',
                'ステータス',
                '出勤_実打刻',
                '退勤_実打刻',
                '業務開始_給与用',
                '業務終了_給与用',
                'ベース出勤',
                'ベース退勤',
                '残業_分_丸め後',
                '休憩',
            ]);
            foreach ($records as $r) {
                $dateStr = $r->date ? $r->date->format('Y-m-d') : '';
                $clockIn = $r->clock_in_at ? $r->clock_in_at->format('H:i') : '';
                $clockOut = $r->clock_out_at ? $r->clock_out_at->format('H:i') : '';
                $breaksStr = $r->breaks->map(function ($b) {
                    $s = $b->start_at ? $b->start_at->format('H:i') : '';
                    $e = $b->end_at ? $b->end_at->format('H:i') : '';

                    return $s . '-' . $e;
                })->filter()->join(', ');

                $pay = $payrollService->payrollPayloadForRecord($r, $patterns, $payrollSetting);
                $fmt = function (?string $iso) {
                    if (!$iso) {
                        return '';
                    }
                    try {
                        return Carbon::parse($iso)->format('H:i');
                    } catch (\Throwable) {
                        return '';
                    }
                };

                fputcsv($stream, [
                    $dateStr,
                    $r->user->name ?? '',
                    $r->shop->name ?? '',
                    $statusLabels[$r->status] ?? $r->status,
                    $clockIn,
                    $clockOut,
                    $fmt($pay['payroll_clock_in_at'] ?? null),
                    $fmt($pay['payroll_clock_out_at'] ?? null),
                    $fmt($pay['base_start_at'] ?? null),
                    $fmt($pay['base_end_at'] ?? null),
                    $pay['overtime_minutes_rounded'] !== null ? (string) $pay['overtime_minutes_rounded'] : '',
                    $breaksStr,
                ]);
            }
            fclose($stream);
        }, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }

    /**
     * 月次集計CSV（1スタッフ=1行）。前月21日〜当月20日（from/to で上書き可）。
     */
    public function exportSummaryCsv(Request $request): StreamedResponse
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

        // 対象ユーザー（スコープ内）
        $shopIds = $user->shops()->pluck('shops.id');
        $userQuery = $user->isAttendanceManager()
            ? User::query()
            : User::whereHas('shops', fn ($q) => $q->whereIn('shops.id', $shopIds));
        if ($request->filled('shop_id')) {
            $userQuery->whereHas('shops', fn ($q) => $q->where('shops.id', $request->shop_id));
        }
        if ($request->filled('user_id')) {
            $userQuery->where('id', $request->user_id);
        }
        $targetUsers = $userQuery->orderBy('login_id')->orderBy('name')->get(['id', 'login_id', 'name']);
        $userIds = $targetUsers->pluck('id')->all();

        // 期間内の勤怠レコード（休憩込み）をユーザー別にグループ化
        $recordsByUser = AttendanceRecord::with(['breaks', 'user:id,name,work_attribute_id'])
            ->whereIn('user_id', $userIds)
            ->whereDate('date', '>=', $from)
            ->whereDate('date', '<=', $to)
            ->get()
            ->groupBy('user_id');

        // 期間内の休暇をユーザー別・区分別に集計
        $leaveCounts = AttendanceLeave::whereIn('user_id', $userIds)
            ->whereDate('date', '>=', $from)
            ->whereDate('date', '<=', $to)
            ->get(['user_id', 'leave_type'])
            ->groupBy('user_id');

        $payrollService = app(AttendancePayrollTimeService::class);
        $patterns = $payrollService->loadCalendarPatternsBetween($from, $to);
        $payrollSetting = AttendancePayrollSetting::query()->orderBy('id')->firstOrFail();

        $dayFmt = fn (float $n) => $n > 0 ? sprintf('%.2f', $n) : '';
        $timeFmt = fn (int $m) => $m > 0 ? sprintf('%d:%02d', intdiv($m, 60), $m % 60) : '';
        $intFmt = fn (int $n) => $n > 0 ? (string) $n : '';

        $filename = 'attendance_summary_' . $now->format('YmdHis') . '.csv';

        return new StreamedResponse(function () use ($targetUsers, $recordsByUser, $leaveCounts, $payrollService, $patterns, $payrollSetting, $dayFmt, $timeFmt, $intFmt) {
            $stream = fopen('php://output', 'w');
            fprintf($stream, chr(0xEF) . chr(0xBB) . chr(0xBF));
            fputcsv($stream, [
                '社員番号', '氏名', '出勤日数', '休日出勤日数', '有給日数', '特別休暇日数', '欠勤日数',
                '就労時間', '普通残業', '深夜残業', '遅早回数', '遅早時間',
            ]);

            foreach ($targetUsers as $u) {
                $records = $recordsByUser->get($u->id, collect());

                $attendDays = 0;
                $holidayWorkDays = 0;
                $workMin = 0;
                $normalOt = 0;
                $nightMin = 0;
                $lateEarlyCount = 0;
                $lateEarlyMin = 0;

                foreach ($records as $r) {
                    $m = $payrollService->monthlyMetricsForRecord($r, $patterns, $payrollSetting);
                    if ($m['worked']) {
                        $attendDays++;
                    }
                    if ($m['holiday_work']) {
                        $holidayWorkDays++;
                    }
                    $workMin += $m['work_minutes'];
                    $normalOt += $m['overtime_normal_minutes'];
                    $nightMin += $m['night_minutes'];
                    $lateEarlyCount += $m['late_early_count'];
                    $lateEarlyMin += $m['late_early_minutes'];
                }

                $leaves = $leaveCounts->get($u->id, collect());
                $paid = $leaves->where('leave_type', AttendanceLeave::TYPE_PAID)->count();
                $special = $leaves->where('leave_type', AttendanceLeave::TYPE_SPECIAL)->count();
                $absence = $leaves->where('leave_type', AttendanceLeave::TYPE_ABSENCE)->count();

                fputcsv($stream, [
                    $u->login_id ?? (string) $u->id,
                    $u->name,
                    $dayFmt($attendDays),
                    $dayFmt($holidayWorkDays),
                    $dayFmt($paid),
                    $dayFmt($special),
                    $dayFmt($absence),
                    $timeFmt($workMin),
                    $timeFmt($normalOt),
                    $timeFmt($nightMin),
                    $intFmt($lateEarlyCount),
                    $timeFmt($lateEarlyMin),
                ]);
            }
            fclose($stream);
        }, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }
}
