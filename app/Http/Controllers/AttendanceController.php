<?php

namespace App\Http\Controllers;

use App\Models\AttendanceBreak;
use App\Models\AttendancePayrollSetting;
use App\Models\AttendanceRecord;
use App\Models\Shop;
use App\Services\AttendancePayrollTimeService;
use App\Services\AttendanceScopeService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class AttendanceController extends Controller
{
    /**
     * 出勤（サーバー時間、即時 approved）
     */
    public function clockIn(Request $request)
    {
        $validated = $request->validate([
            'shop_id' => 'required|exists:shops,id',
        ]);

        $user = $request->user();
        $shopId = (int) $validated['shop_id'];

        if (!$user->shops()->where('shops.id', $shopId)->exists()) {
            return response()->json(['message' => '所属していない店舗です。'], 403);
        }

        $today = Carbon::today();

        $existing = AttendanceRecord::where('user_id', $user->id)
            ->whereDate('date', $today)
            ->first();

        if ($existing) {
            return response()->json(['message' => '本日は既に勤怠登録があります。'], 409);
        }

        return DB::transaction(function () use ($user, $shopId, $today) {
            $record = AttendanceRecord::create([
                'user_id' => $user->id,
                'shop_id' => $shopId,
                'date' => $today,
                'clock_in_at' => Carbon::now(),
                'clock_out_at' => null,
                'status' => AttendanceRecord::STATUS_APPROVED,
                'is_manual' => false,
            ]);
            return response()->json([
                'success' => true,
                'record' => $this->formatRecord($record),
            ]);
        });
    }

    /**
     * 退勤（サーバー時間）
     */
    public function clockOut(Request $request)
    {
        $user = $request->user();
        $today = Carbon::today();

        $record = AttendanceRecord::where('user_id', $user->id)
            ->whereDate('date', $today)
            ->first();

        if (!$record) {
            return response()->json(['message' => '本日の出勤記録がありません。'], 404);
        }
        if ($record->clock_out_at) {
            return response()->json(['message' => '既に退勤済みです。'], 409);
        }
        if ($record->isOnBreak()) {
            return response()->json(['message' => '休憩を終了してから退勤してください。'], 409);
        }

        return DB::transaction(function () use ($record) {
            $record->update(['clock_out_at' => Carbon::now()]);
            return response()->json([
                'success' => true,
                'record' => $this->formatRecord($record->fresh()),
            ]);
        });
    }

    /**
     * 休憩開始
     */
    public function breakStart(Request $request)
    {
        $user = $request->user();
        $today = Carbon::today();

        $record = AttendanceRecord::where('user_id', $user->id)
            ->whereDate('date', $today)
            ->with('breaks')
            ->first();

        if (!$record) {
            return response()->json(['message' => '本日の出勤記録がありません。'], 404);
        }
        if ($record->clock_out_at) {
            return response()->json(['message' => '既に退勤済みです。'], 409);
        }
        if ($record->isOnBreak()) {
            return response()->json(['message' => '既に休憩中です。'], 409);
        }

        return DB::transaction(function () use ($record) {
            AttendanceBreak::create([
                'attendance_record_id' => $record->id,
                'start_at' => Carbon::now(),
                'end_at' => null,
            ]);
            return response()->json([
                'success' => true,
                'record' => $this->formatRecord($record->fresh(['breaks'])),
            ]);
        });
    }

    /**
     * 休憩終了
     */
    public function breakEnd(Request $request)
    {
        $user = $request->user();
        $today = Carbon::today();

        $record = AttendanceRecord::where('user_id', $user->id)
            ->whereDate('date', $today)
            ->with('breaks')
            ->first();

        if (!$record) {
            return response()->json(['message' => '本日の出勤記録がありません。'], 404);
        }

        $activeBreak = $record->breaks()->whereNull('end_at')->first();
        if (!$activeBreak) {
            return response()->json(['message' => '休憩中の記録がありません。'], 409);
        }

        return DB::transaction(function () use ($activeBreak, $record) {
            $activeBreak->update(['end_at' => Carbon::now()]);
            return response()->json([
                'success' => true,
                'record' => $this->formatRecord($record->fresh(['breaks'])),
            ]);
        });
    }

    /**
     * 一番新しいステータスのみを取り消す
     * 優先: 休憩中 → 退勤 → 休憩終了 → 出勤
     */
    public function cancelLastAction(Request $request)
    {
        $user = $request->user();
        $today = Carbon::today();

        $record = AttendanceRecord::where('user_id', $user->id)
            ->whereDate('date', $today)
            ->with('breaks')
            ->first();

        if (!$record) {
            if ($request->expectsJson()) {
                return response()->json(['message' => '本日の勤怠記録がありません。'], 404);
            }
            return redirect()->route('dashboard')->with('error', '本日の勤怠記録がありません。');
        }

        $action = $this->getCancellableActionForRecord($record);
        if (!$action) {
            if ($request->expectsJson()) {
                return response()->json(['message' => '取り消せるステータスがありません。'], 400);
            }
            return redirect()->route('dashboard')->with('error', '取り消せるステータスがありません。');
        }

        return DB::transaction(function () use ($record, $action, $request) {
            if ($action === 'break_start') {
                $activeBreak = $record->breaks()->whereNull('end_at')->first();
                if ($activeBreak) {
                    $activeBreak->delete();
                }
            } elseif ($action === 'clock_out') {
                $record->update(['clock_out_at' => null]);
            } elseif ($action === 'break_end') {
                $lastCompleted = $record->breaks()->whereNotNull('end_at')->orderByDesc('end_at')->first();
                if ($lastCompleted) {
                    $lastCompleted->update(['end_at' => null]);
                }
            } elseif ($action === 'clock_in') {
                $record->delete();
            }

            if ($request->expectsJson()) {
                return response()->json(['success' => true]);
            }
            return redirect()->route('dashboard')->with('success', '取り消しました。');
        });
    }

    private function getCancellableActionForRecord(AttendanceRecord $record): ?string
    {
        if ($record->isOnBreak()) {
            return 'break_start';
        }
        if ($record->clock_out_at !== null) {
            return 'clock_out';
        }
        if ($record->breaks()->whereNotNull('end_at')->exists()) {
            return 'break_end';
        }
        return 'clock_in';
    }

    /**
     * 仮登録フォーム表示
     */
    public function createProvisional(Request $request)
    {
        $user = $request->user();
        $userShops = $user->shops()->where('shops.is_active', true)->get(['shops.id', 'shops.name']);

        return Inertia::render('Attendance/ProvisionalForm', [
            'userShops' => $userShops,
        ]);
    }

    /**
     * 仮登録編集フォーム表示
     */
    public function editProvisional(Request $request, AttendanceRecord $record)
    {
        if ($record->user_id !== $request->user()->id) {
            abort(403);
        }
        if (!$record->isDraft()) {
            return back()->withErrors(['record' => '編集できるのは未申請の仮登録のみです。']);
        }

        $record->load(['shop:id,name', 'breaks']);
        $userShops = $request->user()->shops()->where('shops.is_active', true)->get(['shops.id', 'shops.name']);

        return Inertia::render('Attendance/ProvisionalForm', [
            'record' => [
                'id' => $record->id,
                'date' => $record->date->format('Y-m-d'),
                'shop_id' => $record->shop_id,
                'clock_in_at' => $record->clock_in_at?->format('Y-m-d\TH:i'),
                'clock_out_at' => $record->clock_out_at?->format('Y-m-d\TH:i'),
                'breaks' => $record->breaks->map(fn ($b) => [
                    'start_at' => $b->start_at->format('Y-m-d\TH:i'),
                    'end_at' => $b->end_at?->format('Y-m-d\TH:i'),
                ])->values()->all(),
            ],
            'userShops' => $userShops,
        ]);
    }

    /**
     * 仮登録作成（日付指定 or マニュアル時間）
     */
    public function storeProvisional(Request $request)
    {
        $validated = $request->validate([
            'date' => 'required|date',
            'shop_id' => 'required|exists:shops,id',
            'clock_in_at' => 'nullable|date',
            'clock_out_at' => ['nullable', 'date', function ($attr, $val, $fail) use ($request) {
                if ($val && $request->clock_in_at && \Carbon\Carbon::parse($val)->lt(\Carbon\Carbon::parse($request->clock_in_at))) {
                    $fail('退勤時刻は出勤時刻より後である必要があります。');
                }
            }],
            'is_manual' => 'boolean',
            'breaks' => 'nullable|array',
            'breaks.*.start_at' => 'required_with:breaks|date',
            'breaks.*.end_at' => 'nullable|date|after_or_equal:breaks.*.start_at',
        ]);

        $user = $request->user();
        $date = Carbon::parse($validated['date'])->startOfDay();
        $shopId = (int) $validated['shop_id'];

        if (!$user->shops()->where('shops.id', $shopId)->exists()) {
            return back()->withErrors(['shop_id' => '所属していない店舗です。']);
        }

        $existing = AttendanceRecord::where('user_id', $user->id)->whereDate('date', $date)->first();
        if ($existing) {
            if ($existing->status === AttendanceRecord::STATUS_APPROVED) {
                return back()->withErrors(['date' => '該当日は既に確定済みの勤怠があります。']);
            }
            return back()->withErrors(['date' => '該当日は既に勤怠が登録されています。勤怠履歴から編集してください。']);
        }

        $isManual = (bool) ($validated['is_manual'] ?? false);
        $status = $isManual ? AttendanceRecord::STATUS_APPLIED : AttendanceRecord::STATUS_DRAFT;

        $clockInAt = isset($validated['clock_in_at']) ? Carbon::parse($validated['clock_in_at']) : null;
        $clockOutAt = isset($validated['clock_out_at']) ? Carbon::parse($validated['clock_out_at']) : null;

        if ($clockInAt && $clockOutAt && $clockOutAt->lte($clockInAt)) {
            return back()->withErrors(['clock_out_at' => '退勤時刻は出勤時刻より後である必要があります。']);
        }

        return DB::transaction(function () use ($user, $date, $shopId, $status, $isManual, $clockInAt, $clockOutAt, $validated) {
            $record = AttendanceRecord::create([
                'user_id' => $user->id,
                'shop_id' => $shopId,
                'date' => $date,
                'clock_in_at' => $clockInAt,
                'clock_out_at' => $clockOutAt,
                'status' => $status,
                'is_manual' => $isManual,
            ]);

            $breaks = $validated['breaks'] ?? [];
            foreach ($breaks as $b) {
                if (!empty($b['start_at'])) {
                    $start = Carbon::parse($b['start_at']);
                    $end = !empty($b['end_at']) ? Carbon::parse($b['end_at']) : null;
                    if ($clockInAt && $start->lt($clockInAt)) {
                        continue;
                    }
                    if ($clockOutAt && $end && $end->gt($clockOutAt)) {
                        continue;
                    }
                    AttendanceBreak::create([
                        'attendance_record_id' => $record->id,
                        'start_at' => $start,
                        'end_at' => $end,
                    ]);
                }
            }

            return redirect()->route('attendance.history')->with('success', '仮登録しました。');
        });
    }

    /**
     * 仮登録編集（draft のみ）
     */
    public function updateProvisional(Request $request, AttendanceRecord $record)
    {
        if ($record->user_id !== $request->user()->id) {
            abort(403);
        }
        if (!$record->isDraft()) {
            return back()->withErrors(['record' => '編集できるのは未申請の仮登録のみです。']);
        }

        $validated = $request->validate([
            'clock_in_at' => 'nullable|date',
            'clock_out_at' => 'nullable|date|after_or_equal:clock_in_at',
            'breaks' => 'nullable|array',
            'breaks.*.start_at' => 'required_with:breaks|date',
            'breaks.*.end_at' => 'nullable|date|after_or_equal:breaks.*.start_at',
        ]);

        $clockInAt = isset($validated['clock_in_at']) ? Carbon::parse($validated['clock_in_at']) : null;
        $clockOutAt = isset($validated['clock_out_at']) ? Carbon::parse($validated['clock_out_at']) : null;

        return DB::transaction(function () use ($record, $clockInAt, $clockOutAt, $validated) {
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

            return redirect()->route('attendance.history')->with('success', '更新しました。');
        });
    }

    /**
     * 申請（draft → applied）
     */
    public function apply(Request $request, AttendanceRecord $record)
    {
        if ($record->user_id !== $request->user()->id) {
            abort(403);
        }
        if (!$record->isDraft()) {
            return back()->withErrors(['record' => '申請できるのは未申請の仮登録のみです。']);
        }

        $validated = $request->validate([
            'application_reason' => 'nullable|string|max:500',
        ]);

        $record->update([
            'status' => AttendanceRecord::STATUS_APPLIED,
            'application_reason' => $validated['application_reason'] ?? null,
        ]);

        return redirect()->route('attendance.history')->with('success', '申請しました。');
    }

    /**
     * 勤怠履歴一覧（自分のみ）
     *
     * 日付は新しい順。期間未指定時は「前月21日〜今月20日」（締め想定のデフォルト）。
     */
    public function history(Request $request)
    {
        $user = $request->user();

        $now = Carbon::now();
        $defaultFrom = $now->copy()->startOfMonth()->subMonth()->setDay(21)->format('Y-m-d');
        $defaultTo = $now->copy()->startOfMonth()->setDay(20)->format('Y-m-d');

        $from = $request->filled('from') ? $request->input('from') : $defaultFrom;
        $to = $request->filled('to') ? $request->input('to') : $defaultTo;

        $query = AttendanceRecord::where('user_id', $user->id)
            ->with(['shop:id,name', 'breaks', 'user:id,name,work_attribute_id'])
            ->whereDate('date', '>=', $from)
            ->whereDate('date', '<=', $to)
            ->orderByDesc('date')
            ->orderByDesc('id');

        $records = $query->paginate(20)->withQueryString();
        $this->enrichAttendancePaginatorWithShiftAndPayroll($records, true);

        return Inertia::render('Attendance/History', [
            'records' => $records,
            'filters' => [
                'from' => $from,
                'to' => $to,
            ],
        ]);
    }

    /**
     * 勤怠状態 API（今日の状態）
     */
    public function status(Request $request)
    {
        $user = $request->user();
        $today = Carbon::today();

        $record = AttendanceRecord::where('user_id', $user->id)
            ->whereDate('date', $today)
            ->with('breaks')
            ->first();

        $isWorking = false;
        $isOnBreak = false;
        $clockInAt = null;
        $breakStartAt = null;

        if ($record) {
            $isWorking = $record->isWorking();
            $isOnBreak = $record->isOnBreak();
            $clockInAt = $record->clock_in_at?->toIso8601String();
            $activeBreak = $record->breaks()->whereNull('end_at')->first();
            $breakStartAt = $activeBreak?->start_at?->toIso8601String();
        }

        return response()->json([
            'isWorking' => $isWorking,
            'isOnBreak' => $isOnBreak,
            'clockInAt' => $clockInAt,
            'breakStartAt' => $breakStartAt,
            'todayRecord' => $record ? $this->formatRecord($record) : null,
        ]);
    }

    /**
     * 承認依頼一覧（管理者・勤怠管理者）
     */
    public function approvalIndex(Request $request)
    {
        $user = $request->user();
        if (!AttendanceScopeService::canAccessApprovals($user)) {
            abort(403);
        }

        $query = AttendanceRecord::where('status', AttendanceRecord::STATUS_APPLIED)
            ->with(['user:id,name,work_attribute_id', 'shop:id,name', 'breaks']);
        $query = AttendanceScopeService::scopeForUser($query, $user);
        $records = $query->orderBy('date')->orderBy('created_at')->paginate(20)->withQueryString();
        $this->enrichAttendancePaginatorWithShiftAndPayroll($records, false);

        return Inertia::render('Attendance/ApprovalIndex', [
            'records' => $records,
        ]);
    }

    /**
     * 承認
     */
    public function approve(Request $request, AttendanceRecord $record)
    {
        $user = $request->user();
        if (!AttendanceScopeService::canApproveRecord($user, $record)) {
            abort(403);
        }
        if (!$record->isApplied()) {
            return back()->withErrors(['record' => '承認待ちのレコードのみ承認できます。']);
        }

        DB::transaction(function () use ($record, $user) {
            $record->update([
                'status' => AttendanceRecord::STATUS_APPROVED,
                'approved_at' => Carbon::now(),
                'approved_by' => $user->id,
            ]);
        });

        return redirect()->route('attendance.approvals')->with('success', '承認しました。');
    }

    /**
     * 差し戻し
     */
    public function reject(Request $request, AttendanceRecord $record)
    {
        $user = $request->user();
        if (!AttendanceScopeService::canApproveRecord($user, $record)) {
            abort(403);
        }
        if (!$record->isApplied()) {
            return back()->withErrors(['record' => '承認待ちのレコードのみ差し戻せます。']);
        }

        $record->update(['status' => AttendanceRecord::STATUS_DRAFT]);

        return redirect()->route('attendance.approvals')->with('success', '差し戻しました。');
    }

    /**
     * 一括承認
     */
    public function batchApprove(Request $request)
    {
        $user = $request->user();
        if (!AttendanceScopeService::canAccessApprovals($user)) {
            abort(403);
        }

        $ids = $request->input('ids', []);
        if (!is_array($ids)) {
            return redirect()->route('attendance.approvals')->withErrors(['ids' => '不正なリクエストです。']);
        }
        $ids = array_filter(array_map('intval', $ids));

        $count = 0;
        foreach ($ids as $id) {
            $record = AttendanceRecord::find($id);
            if (!$record || !$record->isApplied()) {
                continue;
            }
            if (!AttendanceScopeService::canApproveRecord($user, $record)) {
                continue;
            }
            DB::transaction(function () use ($record, $user) {
                $record->update([
                    'status' => AttendanceRecord::STATUS_APPROVED,
                    'approved_at' => Carbon::now(),
                    'approved_by' => $user->id,
                ]);
            });
            $count++;
        }

        $message = $count > 0 ? "{$count}件を承認しました。" : '承認できるレコードがありませんでした。';
        return redirect()->route('attendance.approvals')->with('success', $message);
    }

    /**
     * 一括差し戻し
     */
    public function batchReject(Request $request)
    {
        $user = $request->user();
        if (!AttendanceScopeService::canAccessApprovals($user)) {
            abort(403);
        }

        $ids = $request->input('ids', []);
        if (!is_array($ids)) {
            return redirect()->route('attendance.approvals')->withErrors(['ids' => '不正なリクエストです。']);
        }
        $ids = array_filter(array_map('intval', $ids));

        $count = 0;
        foreach ($ids as $id) {
            $record = AttendanceRecord::find($id);
            if (!$record || !$record->isApplied()) {
                continue;
            }
            if (!AttendanceScopeService::canApproveRecord($user, $record)) {
                continue;
            }
            $record->update(['status' => AttendanceRecord::STATUS_DRAFT]);
            $count++;
        }

        $message = $count > 0 ? "{$count}件を差し戻しました。" : '差し戻せるレコードがありませんでした。';
        return redirect()->route('attendance.approvals')->with('success', $message);
    }

    /**
     * ページネーション済み一覧にシフト診断・残業（payroll）を付与する。
     *
     * @param  bool  $omitUserFromRow  true のとき行配列から user を除く（勤怠履歴用）
     */
    private function enrichAttendancePaginatorWithShiftAndPayroll(LengthAwarePaginator $paginator, bool $omitUserFromRow = false): void
    {
        $collection = $paginator->getCollection();
        if ($collection->isEmpty()) {
            return;
        }

        $payrollTime = app(AttendancePayrollTimeService::class);
        $payrollSetting = AttendancePayrollSetting::query()->orderBy('id')->firstOrFail();

        $minStr = $collection->min(fn (AttendanceRecord $r) => $r->date->format('Y-m-d'));
        $maxStr = $collection->max(fn (AttendanceRecord $r) => $r->date->format('Y-m-d'));
        $patterns = $payrollTime->loadCalendarPatternsBetween($minStr, $maxStr);

        $enriched = $collection->map(function (AttendanceRecord $record) use ($payrollTime, $patterns, $payrollSetting, $omitUserFromRow) {
            $recordUser = $record->user;
            $date = $record->date instanceof Carbon ? $record->date->copy()->startOfDay() : Carbon::parse($record->date)->startOfDay();

            if (!$recordUser) {
                $shift = [
                    'calendar_pattern' => null,
                    'start_at' => null,
                    'end_at' => null,
                    'available' => false,
                    'help_reasons' => ['ユーザー情報がありません。'],
                ];
                $payrollPayload = [
                    'overtime_minutes_raw' => null,
                    'overtime_minutes_rounded' => null,
                ];
            } else {
                $shift = $payrollTime->shiftDiagnostics($recordUser, $date, $patterns);
                $payrollPayload = $payrollTime->payrollPayloadForRecord($record, $patterns, $payrollSetting);
            }

            $row = $record->toArray();

            if ($omitUserFromRow) {
                unset($row['user']);
            } elseif (isset($row['user']) && is_array($row['user'])) {
                unset($row['user']['work_attribute_id']);
            }

            return array_merge($row, [
                'shift' => $shift,
                'payroll' => [
                    'overtime_minutes_raw' => $payrollPayload['overtime_minutes_raw'],
                    'overtime_minutes_rounded' => $payrollPayload['overtime_minutes_rounded'],
                ],
            ]);
        });

        $paginator->setCollection($enriched);
    }

    private function formatRecord(AttendanceRecord $record): array
    {
        $record->load(['shop:id,name', 'breaks']);
        return [
            'id' => $record->id,
            'date' => $record->date->format('Y-m-d'),
            'shop' => $record->shop ? ['id' => $record->shop->id, 'name' => $record->shop->name] : null,
            'clock_in_at' => $record->clock_in_at?->toIso8601String(),
            'clock_out_at' => $record->clock_out_at?->toIso8601String(),
            'status' => $record->status,
            'breaks' => $record->breaks->map(fn ($b) => [
                'id' => $b->id,
                'start_at' => $b->start_at->toIso8601String(),
                'end_at' => $b->end_at?->toIso8601String(),
            ])->values()->all(),
        ];
    }
}
