<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AttendancePayrollSetting;
use App\Models\WorkAttribute;
use App\Models\WorkAttributePatternTime;
use App\Services\AttendancePayrollTimeService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;

class AttendancePayrollSimulatorController extends Controller
{
    public function index(Request $request)
    {
        if (!$request->user()->isAttendanceManager()) {
            abort(403);
        }

        $workAttributes = WorkAttribute::query()
            ->with(['patternTimes' => fn ($q) => $q->orderBy('pattern')->orderBy('day_type')])
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        $matrix = $workAttributes->map(fn (WorkAttribute $attr) => [
            'id' => $attr->id,
            'name' => $attr->name,
            'rows' => $attr->patternTimes->map(fn (WorkAttributePatternTime $r) => [
                'pattern' => $r->pattern,
                'day_type' => $r->day_type,
                'day_type_label' => $r->day_type === WorkAttribute::DAY_TYPE_WEEKEND ? '土日' : '平日',
                'work_start_time' => $this->formatTimeColumn($r->work_start_time),
                'work_end_time' => $this->formatTimeColumn($r->work_end_time),
            ]),
        ]);

        $lastResult = $request->session()->pull('payroll_simulator_result');

        return Inertia::render('Admin/Attendance/PayrollSimulator', [
            'patternMatrix' => $matrix,
            'lastResult' => $lastResult,
        ]);
    }

    /** 平日判定用の参照月曜（実在日・UI 非表示） */
    private const REFERENCE_WEEKDAY = '2026-03-16';

    /** 土日判定用の参照土曜 */
    private const REFERENCE_WEEKEND = '2026-03-21';

    /** 1分刻み試算の上限（片方のスイープあたり） */
    private const MAX_SWEEP_MINUTES = 1440;

    public function simulate(Request $request)
    {
        if (!$request->user()->isAttendanceManager()) {
            abort(403);
        }

        $validated = $request->validate([
            'work_attribute_id' => 'required|exists:work_attributes,id',
            'day_type' => 'required|string|in:weekday,weekend',
            'pattern' => 'required|string|in:A,B,C',
            'clock_in_from' => 'required|date_format:H:i',
            'clock_in_to' => 'required|date_format:H:i',
            'clock_out_fixed' => 'required|date_format:H:i',
            'clock_out_from' => 'required|date_format:H:i',
            'clock_out_to' => 'required|date_format:H:i',
            'clock_in_fixed' => 'required|date_format:H:i',
            'breaks' => 'nullable|array|max:10',
            'breaks.*.start' => 'nullable|date_format:H:i',
            'breaks.*.end' => 'nullable|date_format:H:i',
        ]);

        $dayStr = $validated['day_type'] === 'weekend'
            ? self::REFERENCE_WEEKEND
            : self::REFERENCE_WEEKDAY;
        $date = Carbon::parse($dayStr, config('app.timezone'))->startOfDay();

        $breakIntervals = [];
        foreach ($validated['breaks'] ?? [] as $b) {
            if (empty($b['start']) || empty($b['end'])) {
                continue;
            }
            $breakIntervals[] = ['start' => $b['start'], 'end' => $b['end']];
        }

        $payroll = app(AttendancePayrollTimeService::class);
        $setting = AttendancePayrollSetting::query()->orderBy('id')->firstOrFail();

        $workAttributeId = (int) $validated['work_attribute_id'];
        $pattern = strtoupper($validated['pattern']);

        $inFrom = $validated['clock_in_from'];
        $inTo = $validated['clock_in_to'];
        $outFrom = $validated['clock_out_from'];
        $outTo = $validated['clock_out_to'];

        if ($this->timeToMinutes($inFrom) > $this->timeToMinutes($inTo)) {
            [$inFrom, $inTo] = [$inTo, $inFrom];
        }
        if ($this->timeToMinutes($outFrom) > $this->timeToMinutes($outTo)) {
            [$outFrom, $outTo] = [$outTo, $outFrom];
        }

        $inSweepCount = $this->countMinutesInclusive($inFrom, $inTo);
        $outSweepCount = $this->countMinutesInclusive($outFrom, $outTo);
        if ($inSweepCount > self::MAX_SWEEP_MINUTES || $outSweepCount > self::MAX_SWEEP_MINUTES) {
            return back()
                ->withErrors([
                    'clock_in_from' => '出勤または退勤の範囲が長すぎます（1分刻みは最大 '.self::MAX_SWEEP_MINUTES.' 分まで）。範囲を狭めてください。',
                ])
                ->withInput();
        }

        $sweepClockIn = $this->buildSweepRows(
            $payroll,
            $setting,
            $workAttributeId,
            $date,
            $pattern,
            $breakIntervals,
            'in',
            $inFrom,
            $inTo,
            $validated['clock_out_fixed'],
        );

        $sweepClockOut = $this->buildSweepRows(
            $payroll,
            $setting,
            $workAttributeId,
            $date,
            $pattern,
            $breakIntervals,
            'out',
            $outFrom,
            $outTo,
            $validated['clock_in_fixed'],
        );

        $probe = $payroll->simulatePayroll(
            $workAttributeId,
            $date,
            $pattern,
            Carbon::parse($dayStr.' '.$validated['clock_in_fixed'], config('app.timezone')),
            Carbon::parse($dayStr.' '.$validated['clock_out_fixed'], config('app.timezone')),
            $breakIntervals,
            $setting,
        );

        return redirect()->route('admin.attendance.payroll-simulator.index')
            ->with('payroll_simulator_result', [
                'input' => [
                    'work_attribute_id' => $workAttributeId,
                    'day_type' => $validated['day_type'],
                    'pattern' => $pattern,
                    'clock_in_from' => $inFrom,
                    'clock_in_to' => $inTo,
                    'clock_out_fixed' => $validated['clock_out_fixed'],
                    'clock_out_from' => $outFrom,
                    'clock_out_to' => $outTo,
                    'clock_in_fixed' => $validated['clock_in_fixed'],
                    'breaks' => $breakIntervals,
                ],
                'reference_date' => $dayStr,
                'computed_summary' => $probe,
                'sweep_clock_in' => $sweepClockIn,
                'sweep_clock_out' => $sweepClockOut,
            ]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function buildSweepRows(
        AttendancePayrollTimeService $payroll,
        AttendancePayrollSetting $setting,
        int $workAttributeId,
        Carbon $date,
        string $pattern,
        array $breakIntervals,
        string $vary,
        string $rangeFrom,
        string $rangeTo,
        string $fixedOther,
    ): array {
        $dayStr = $date->format('Y-m-d');
        $rows = [];
        $prevSig = null;

        foreach ($this->eachMinuteTime($rangeFrom, $rangeTo) as $t) {
            if ($vary === 'in') {
                $clockIn = Carbon::parse($dayStr.' '.$t, config('app.timezone'));
                $clockOut = Carbon::parse($dayStr.' '.$fixedOther, config('app.timezone'));
            } else {
                $clockIn = Carbon::parse($dayStr.' '.$fixedOther, config('app.timezone'));
                $clockOut = Carbon::parse($dayStr.' '.$t, config('app.timezone'));
            }

            $result = $payroll->simulatePayroll(
                $workAttributeId,
                $date,
                $pattern,
                $clockIn,
                $clockOut,
                $breakIntervals,
                $setting,
            );

            $sig = $this->resultSignature($result);
            $transition = $prevSig !== null && $sig !== $prevSig;
            $prevSig = $sig;

            $rows[] = [
                'vary_time' => $t,
                'resolved' => $result['resolved'] ?? false,
                'payroll_clock_in_at' => $result['payroll_clock_in_at'] ?? null,
                'overtime_minutes_raw' => $result['overtime_minutes_raw'] ?? null,
                'overtime_minutes_rounded' => $result['overtime_minutes_rounded'] ?? null,
                'transition' => $transition,
            ];
        }

        return $rows;
    }

    /**
     * @return \Generator<string>
     */
    private function eachMinuteTime(string $from, string $to): \Generator
    {
        $a = $this->timeToMinutes($from);
        $b = $this->timeToMinutes($to);
        if ($a > $b) {
            [$a, $b] = [$b, $a];
        }
        for ($m = $a; $m <= $b; $m++) {
            yield $this->minutesToTime($m);
        }
    }

    private function countMinutesInclusive(string $from, string $to): int
    {
        $a = $this->timeToMinutes($from);
        $b = $this->timeToMinutes($to);
        if ($a > $b) {
            [$a, $b] = [$b, $a];
        }

        return $b - $a + 1;
    }

    private function timeToMinutes(string $hms): int
    {
        $parts = explode(':', $hms);

        return ((int) $parts[0]) * 60 + ((int) ($parts[1] ?? 0));
    }

    private function minutesToTime(int $totalMinutes): string
    {
        $h = intdiv($totalMinutes, 60) % 24;
        $m = $totalMinutes % 60;

        return sprintf('%02d:%02d', $h, $m);
    }

    /**
     * @param  array<string, mixed>  $result
     */
    private function resultSignature(array $result): string
    {
        if (empty($result['resolved'])) {
            return 'unresolved';
        }

        $raw = $result['overtime_minutes_raw'];
        $rounded = $result['overtime_minutes_rounded'];

        return ($result['payroll_clock_in_at'] ?? '')
            .'|'.(is_numeric($raw) ? (string) $raw : '')
            .'|'.(is_numeric($rounded) ? (string) $rounded : '');
    }

    private function formatTimeColumn(mixed $v): ?string
    {
        if ($v === null || $v === '') {
            return null;
        }
        try {
            return Carbon::parse((string) $v)->format('H:i');
        } catch (\Throwable) {
            return (string) $v;
        }
    }
}
