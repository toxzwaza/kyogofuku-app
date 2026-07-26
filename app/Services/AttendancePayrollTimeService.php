<?php

namespace App\Services;

use App\Models\AttendanceBreak;
use App\Models\AttendancePayrollSetting;
use App\Models\AttendanceRecord;
use App\Models\CompanyCalendarDay;
use App\Models\User;
use App\Models\WorkAttribute;
use App\Models\WorkAttributePatternTime;
use Carbon\Carbon;

class AttendancePayrollTimeService
{
    /**
     * 月〜金 = weekday、土日 = weekend（Carbon 既定の週末）
     */
    public function dayTypeForDate(Carbon $date): string
    {
        return $date->isWeekend()
            ? WorkAttribute::DAY_TYPE_WEEKEND
            : WorkAttribute::DAY_TYPE_WEEKDAY;
    }

    /**
     * 会社カレンダー上のその日のパターン（未登録は null）
     *
     * @param  array<string, string|null>|null  $patternsByDate  Y-m-d => A|B|C|null（一括取得用）
     */
    public function calendarPatternForDate(Carbon $date, ?array $patternsByDate = null): ?string
    {
        $key = $date->format('Y-m-d');
        if ($patternsByDate !== null && array_key_exists($key, $patternsByDate)) {
            $p = $patternsByDate[$key];

            return $p === '' ? null : $p;
        }

        $p = CompanyCalendarDay::query()->where('calendar_date', $key)->value('pattern');

        return $p === '' ? null : $p;
    }

    /**
     * ベース業務開始・終了（解決不可時は null）
     *
     * @param  array<string, string|null>|null  $patternsByDate
     * @return array{start: Carbon, end: Carbon}|null
     */
    public function resolveBaseWindow(User $user, Carbon $date, ?array $patternsByDate = null, ?string $overridePattern = null): ?array
    {
        if (!$user->work_attribute_id) {
            return null;
        }

        // 日次パターン上書き（振替出勤など）があれば、会社カレンダーより優先する
        $pattern = ($overridePattern !== null && $overridePattern !== '')
            ? $overridePattern
            : $this->calendarPatternForDate($date, $patternsByDate);
        if ($pattern === null) {
            return null;
        }

        $dayType = $this->dayTypeForDate($date);

        $row = WorkAttributePatternTime::query()
            ->where('work_attribute_id', $user->work_attribute_id)
            ->where('pattern', $pattern)
            ->where('day_type', $dayType)
            ->first();

        if (!$row || !$row->work_start_time || !$row->work_end_time) {
            return null;
        }

        $start = $this->combineDateAndTime($date, $row->work_start_time);
        $end = $this->combineDateAndTime($date, $row->work_end_time);

        if ($end->lte($start)) {
            return null;
        }

        return ['start' => $start, 'end' => $end];
    }

    /**
     * シフト（ベース業務）時刻と、取得できない場合の対応策メッセージ（複数可）
     *
     * @param  array<string, string|null>|null  $patternsByDate
     * @return array{
     *     calendar_pattern: string|null,
     *     start_at: string|null,
     *     end_at: string|null,
     *     available: bool,
     *     help_reasons: list<string>
     * }
     */
    public function shiftDiagnostics(User $user, Carbon $date, ?array $patternsByDate = null): array
    {
        $reasons = [];

        if (!$user->work_attribute_id) {
            $reasons[] = 'スタッフの勤務属性が登録されていません。管理者に勤務属性の設定を依頼してください。';
        }

        $pattern = $this->calendarPatternForDate($date, $patternsByDate);
        $hasPattern = $pattern !== null && $pattern !== '';

        if (!$hasPattern) {
            $reasons[] = '会社カレンダーにこの日のパターン（A/B/C）が登録されていません。勤怠管理者に登録を依頼してください。';
        }

        $window = null;
        if ($user->work_attribute_id && $hasPattern) {
            $window = $this->resolveBaseWindow($user, $date, $patternsByDate);
            if ($window === null) {
                $reasons[] = '勤務属性マスタに、該当するパターン・平日/土日の業務開始・終了時刻が登録されていません。勤怠管理者に設定を依頼してください。';
            }
        }

        $calendarPattern = $hasPattern ? strtoupper((string) $pattern) : null;

        return [
            'calendar_pattern' => $calendarPattern,
            'start_at' => $window === null ? null : $window['start']->toIso8601String(),
            'end_at' => $window === null ? null : $window['end']->toIso8601String(),
            'available' => $window !== null,
            'help_reasons' => array_values(array_unique($reasons)),
        ];
    }

    /**
     * 給与・CSV 用の出勤時刻
     *
     * - シフト開始以降: 実打刻を採用し、start_rounding_unit_minutes で 0:00 からの経過分に対して最近接丸め（1 のとき無効）
     * - 早出: threshold 0 のときはシフト開始にそろえてから丸め。
     *   正のとき earlyBound = シフト開始 − threshold 分（分単位で比較）:
     *   ・実打刻の分が earlyBound の分より前 → 実打刻を最近接丸め（早出）
     *   ・実打刻の分が earlyBound と同じ → earlyBound 時刻を最近接丸め（例: 8:30 は 8:30 のまま）
     *   ・earlyBound より後かつシフト開始より前 → シフト開始にそろえて丸め（例: 8:31 → 9:00）
     */
    public function payrollClockInAt(?Carbon $actualIn, ?Carbon $baseStart, ?AttendancePayrollSetting $setting = null): ?Carbon
    {
        if ($actualIn === null) {
            return null;
        }

        $setting = $setting ?? AttendancePayrollSetting::current();
        $threshold = max(0, (int) $setting->start_early_threshold_minutes);

        if ($baseStart === null) {
            return $this->roundClockTimeToNearestMinuteUnit($actualIn->copy(), $setting);
        }

        // ベース開始と同じ「分」の打刻は定刻扱い（ベース開始にそろえる）。秒差では遅刻にしない。
        if ($actualIn->copy()->startOfMinute()->equalTo($baseStart->copy()->startOfMinute())) {
            return $this->roundClockTimeToNearestMinuteUnit($baseStart->copy(), $setting);
        }

        if ($actualIn->gte($baseStart)) {
            return $this->roundClockTimeToNearestMinuteUnit($actualIn->copy(), $setting);
        }

        if ($threshold <= 0) {
            return $this->roundClockTimeToNearestMinuteUnit($baseStart->copy(), $setting);
        }

        $earlyBound = $baseStart->copy()->subMinutes($threshold);
        $actualMin = $actualIn->copy()->startOfMinute();
        $earlyBoundMin = $earlyBound->copy()->startOfMinute();
        $baseStartMin = $baseStart->copy()->startOfMinute();

        if ($actualMin->lt($earlyBoundMin)) {
            return $this->roundClockTimeToNearestMinuteUnit($actualIn->copy(), $setting);
        }
        if ($actualMin->equalTo($earlyBoundMin)) {
            return $this->roundClockTimeToNearestMinuteUnit($earlyBound->copy(), $setting);
        }
        if ($actualMin->lt($baseStartMin)) {
            return $this->roundClockTimeToNearestMinuteUnit($baseStart->copy(), $setting);
        }

        return $this->roundClockTimeToNearestMinuteUnit($actualIn->copy(), $setting);
    }

    /**
     * 「業務開始(給与)」の決定区分（背景色などの表示用）
     *
     * - null      : 出勤打刻なし
     * - 'no_base' : ベース業務開始が解決できず、打刻時刻をそのまま採用（早出と同様にオレンジ表示）
     * - 'early'   : 早出として打刻時刻を採用（オレンジ表示）
     * - 'late'    : 遅刻（打刻の分がベース開始の分より後）で打刻時刻を採用（青表示）。同じ分は on_time
     * - 'on_time' : ベース開始にそろえて採用（通常表示）
     *
     * 区分は payrollClockInAt と同じ分岐に従う。
     */
    public function payrollClockInCategory(?Carbon $actualIn, ?Carbon $baseStart, ?AttendancePayrollSetting $setting = null): ?string
    {
        if ($actualIn === null) {
            return null;
        }

        if ($baseStart === null) {
            return 'no_base';
        }

        $setting = $setting ?? AttendancePayrollSetting::current();
        $threshold = max(0, (int) $setting->start_early_threshold_minutes);

        // ベース開始と同じ「分」は定刻（遅刻ではない）。それより後の分から遅刻扱い。
        if ($actualIn->copy()->startOfMinute()->equalTo($baseStart->copy()->startOfMinute())) {
            return 'on_time';
        }

        if ($actualIn->gte($baseStart)) {
            return 'late';
        }

        // ここから先は早出領域（actualIn < baseStart）
        if ($threshold <= 0) {
            return 'on_time';
        }

        $earlyBound = $baseStart->copy()->subMinutes($threshold);
        $actualMin = $actualIn->copy()->startOfMinute();
        $earlyBoundMin = $earlyBound->copy()->startOfMinute();
        $baseStartMin = $baseStart->copy()->startOfMinute();

        if ($actualMin->lt($earlyBoundMin)) {
            return 'early';
        }
        if ($actualMin->equalTo($earlyBoundMin)) {
            return 'early';
        }
        if ($actualMin->lt($baseStartMin)) {
            return 'on_time';
        }

        return 'early';
    }

    /**
     * 始業用: その日 0:00 からの経過「整数分」を unit 分刻みに最近接丸め（PHP の round 規則。1 のときはそのまま）
     */
    public function roundClockTimeToNearestMinuteUnit(Carbon $at, ?AttendancePayrollSetting $setting = null): Carbon
    {
        $setting = $setting ?? AttendancePayrollSetting::current();
        $unit = max(1, (int) $setting->start_rounding_unit_minutes);
        if ($unit <= 1) {
            return $at->copy();
        }

        $dayStart = $at->copy()->startOfDay();
        $elapsed = $at->getTimestamp() - $dayStart->getTimestamp();
        if ($elapsed < 0) {
            return $at->copy();
        }

        $m = intdiv($elapsed, 60);
        $roundedM = (int) round($m / $unit) * $unit;

        return $dayStart->copy()->addMinutes($roundedM);
    }

    /**
     * ベース終了以降の残業（休憩はベース終了以降にかかる分のみ控除）、分
     */
    public function rawOvertimeMinutesAfterBaseEnd(AttendanceRecord $record, ?Carbon $baseEnd): int
    {
        if ($baseEnd === null || $record->clock_out_at === null) {
            return 0;
        }

        $clockOut = $record->clock_out_at;
        if ($clockOut->lte($baseEnd)) {
            return 0;
        }

        $grossSeconds = $clockOut->getTimestamp() - $baseEnd->getTimestamp();
        $grossMinutes = intdiv($grossSeconds, 60);

        $breakAfter = 0;
        foreach ($record->breaks as $b) {
            if ($b->start_at === null || $b->end_at === null) {
                continue;
            }
            $bs = $b->start_at->max($baseEnd);
            $be = $b->end_at->min($clockOut);
            if ($be->gt($bs)) {
                $breakAfter += (int) (($be->getTimestamp() - $bs->getTimestamp()) / 60);
            }
        }

        return max(0, $grossMinutes - $breakAfter);
    }

    /**
     * 給与・CSV 用の退勤時刻（実際に適用される業務終了）
     *
     * - 退勤打刻なし: null
     * - ベース退勤が解決できない: 退勤打刻をそのまま採用
     * - ベース退勤あり: ベース退勤 ＋ 丸め後残業分（残業 0 ならベース退勤）
     */
    public function payrollClockOutAt(?Carbon $actualOut, ?Carbon $baseEnd, int $roundedOvertimeMinutes): ?Carbon
    {
        if ($actualOut === null) {
            return null;
        }

        if ($baseEnd === null) {
            return $actualOut->copy();
        }

        return $baseEnd->copy()->addMinutes(max(0, $roundedOvertimeMinutes));
    }

    /**
     * 終業（残業）分の丸め（overtime_rounding_unit_minutes 刻みで切り捨て）
     *
     * 残業は常に「短い方（切り捨て）」へ丸める。単位未満の端数は常に切り捨てる。
     * 例: 単位15分なら 38分 → 30分。
     */
    public function roundOvertimeMinutes(int $minutes, ?AttendancePayrollSetting $setting = null): int
    {
        if ($minutes <= 0) {
            return 0;
        }

        $setting = $setting ?? AttendancePayrollSetting::current();
        $unit = max(1, (int) $setting->overtime_rounding_unit_minutes);

        return intdiv($minutes, $unit) * $unit;
    }

    /**
     * 一覧・CSV 用の付加情報
     *
     * @param  array<string, string|null>|null  $patternsByDate
     * @return array{
     *   base_start_at: string|null,
     *   base_end_at: string|null,
     *   payroll_clock_in_at: string|null,
     *   payroll_clock_out_at: string|null,
     *   clock_in_category: string|null,
     *   overtime_minutes_raw: int|null,
     *   overtime_minutes_rounded: int|null,
     *   break_minutes: int,
     *   break_is_fixed: bool
     * }
     */
    public function payrollPayloadForRecord(AttendanceRecord $record, ?array $patternsByDate = null, ?AttendancePayrollSetting $setting = null): array
    {
        $user = $record->user;
        $date = $record->date instanceof Carbon ? $record->date->copy()->startOfDay() : Carbon::parse($record->date)->startOfDay();

        $setting = $setting ?? AttendancePayrollSetting::current();
        $overridePattern = $record->pattern_override ?: null;
        $window = $this->resolveBaseWindow($user, $date, $patternsByDate, $overridePattern);
        $baseStart = $window['start'] ?? null;
        $baseEnd = $window['end'] ?? null;

        $thresholdMinutes = $this->overtimeThresholdForRecord($user, $date, $setting);

        $payrollIn = $this->payrollClockInAt($record->clock_in_at, $baseStart, $setting);
        $category = $this->payrollClockInCategory($record->clock_in_at, $baseStart, $setting);

        if ($thresholdMinutes !== null) {
            // 閾値方式：実働 − 閾値。給与用退勤は実打刻をそのまま採用。
            $rawOt = $this->thresholdRawOvertimeMinutes($record, $user, $thresholdMinutes);
            $roundedOt = $this->roundOvertimeMinutes($rawOt, $setting);
            $payrollOut = $record->clock_out_at?->copy();
        } else {
            // ベース終業方式（従来）
            $rawOt = $this->rawOvertimeMinutesAfterBaseEnd($record, $baseEnd);
            $roundedOt = $baseEnd !== null
                ? $this->roundOvertimeMinutes($rawOt, $setting)
                : null;
            $payrollOut = $this->payrollClockOutAt($record->clock_out_at, $baseEnd, (int) ($roundedOt ?? 0));
        }

        // 実適用パターン（上書き優先、無ければ会社カレンダー）
        $appliedPattern = $overridePattern ?: $this->calendarPatternForDate($date, $patternsByDate);
        // 「要パターン設定」＝パターンを選べば解決するケースに限定する。
        // 具体的には「打刻あり・勤務属性は登録済み・その日のパターンが未確定（上書きも会社カレンダーも無い）」のとき。
        // 勤務属性未登録やパターン別時刻マスタ未登録は、パターン選択では解決しないため対象外。
        $hasWorkAttribute = $user && $user->work_attribute_id;
        // 閾値方式はパターンに依存しないため「要パターン設定」の対象外。
        $needsPattern = $thresholdMinutes === null
            && $record->clock_in_at !== null
            && $hasWorkAttribute
            && ($appliedPattern === null || $appliedPattern === '')
            && $baseStart === null;
        // 打刻ありなのに勤務属性が未登録＝勤務属性の設定が必要（パターン選択では解決しない）
        $needsWorkAttribute = $record->clock_in_at !== null && !$hasWorkAttribute;

        return [
            'base_start_at' => $baseStart?->toIso8601String(),
            'base_end_at' => $baseEnd?->toIso8601String(),
            'payroll_clock_in_at' => $payrollIn?->toIso8601String(),
            'payroll_clock_out_at' => $payrollOut?->toIso8601String(),
            'clock_in_category' => $category,
            'overtime_minutes_raw' => ($thresholdMinutes !== null || $baseEnd !== null) ? $rawOt : null,
            'overtime_minutes_rounded' => $roundedOt,
            // 休憩控除に使う分（fixed=所定固定 / manual=休憩打刻の合計）
            'break_minutes' => $this->effectiveBreakMinutes($record, $user),
            'break_is_fixed' => (bool) ($user && $user->usesFixedBreak()),
            'applied_pattern' => $appliedPattern !== null && $appliedPattern !== '' ? strtoupper((string) $appliedPattern) : null,
            'needs_pattern' => $needsPattern,
            'needs_work_attribute' => $needsWorkAttribute,
        ];
    }

    /**
     * 月次集計（1レコード分）の各指標を返す。
     *
     * @return array{
     *   worked: bool,
     *   holiday_work: bool,
     *   work_minutes: int,
     *   overtime_normal_minutes: int,
     *   night_minutes: int,
     *   late_early_count: int,
     *   late_early_minutes: int
     * }
     */
    public function monthlyMetricsForRecord(AttendanceRecord $record, ?array $patternsByDate = null, ?AttendancePayrollSetting $setting = null): array
    {
        $user = $record->user;
        $date = $record->date instanceof Carbon ? $record->date->copy()->startOfDay() : Carbon::parse($record->date)->startOfDay();
        $override = $record->pattern_override ?: null;

        $window = $this->resolveBaseWindow($user, $date, $patternsByDate, $override);
        $baseStart = $window['start'] ?? null;
        $baseEnd = $window['end'] ?? null;

        $clockIn = $record->clock_in_at;
        $clockOut = $record->clock_out_at;

        $worked = $clockIn !== null;

        // 休日出勤：会社カレンダー（上書きでなく）にパターンが無い日への出勤で、振替対象日が未登録のもの
        $calPattern = $this->calendarPatternForDate($date, $patternsByDate);
        $holidayWork = $worked
            && ($calPattern === null || $calPattern === '')
            && empty($record->substitute_for_date);

        $setting = $setting ?? AttendancePayrollSetting::current();
        $thresholdMinutes = $this->overtimeThresholdForRecord($user, $date, $setting);
        $breakMinutes = $this->effectiveBreakMinutes($record, $user);

        // 就労時間：給与用（丸め後）在社時間 − 休憩
        $payIn = $this->payrollClockInAt($clockIn, $baseStart, $setting);

        if ($thresholdMinutes !== null) {
            // 閾値方式：残業＝実働 − 閾値。給与用退勤は実打刻。深夜残業は末尾区間で判定。
            $payOut = $clockOut?->copy();
            $rawOt = $this->thresholdRawOvertimeMinutes($record, $user, $thresholdMinutes);
            $roundedOt = (int) $this->roundOvertimeMinutes($rawOt, $setting);
            $overtimeNight = $this->thresholdOvertimeNightMinutes($record, $roundedOt);
        } else {
            // ベース終業方式（従来）
            $rawOt = $this->rawOvertimeMinutesAfterBaseEnd($record, $baseEnd);
            $roundedOt = $baseEnd !== null ? (int) $this->roundOvertimeMinutes($rawOt, $setting) : 0;
            $payOut = $this->payrollClockOutAt($clockOut, $baseEnd, $roundedOt);
            $overtimeNight = ($baseEnd && $clockOut && $clockOut->gt($baseEnd))
                ? $this->nightWorkMinutesBetween($record, $baseEnd, $clockOut)
                : 0;
        }

        $workMinutes = 0;
        if ($payIn && $payOut && $payOut->gt($payIn)) {
            $workMinutes = max(0, intdiv($payOut->diffInSeconds($payIn), 60) - $breakMinutes);
        }

        // 深夜勤務：全勤務のうち 22:00〜翌5:00 に重なる時間（休憩控除）
        $nightMinutes = ($clockIn && $clockOut) ? $this->nightWorkMinutesBetween($record, $clockIn, $clockOut) : 0;

        // 普通残業：残業（丸め後）− 残業のうち深夜帯に重なる分
        $overtimeNormal = max(0, $roundedOt - $overtimeNight);

        // 遅早：ベース時刻が解決できた日のみ。1日あたり最大1回。
        // 遅刻は「分」で比較し、ベース開始と同じ分の打刻は遅刻にしない（それより後の分から遅刻）。
        $lateMin = 0;
        $earlyMin = 0;
        $lateEarlyCount = 0;
        if ($baseStart && $baseEnd) {
            if ($clockIn) {
                $clockInMin = $clockIn->copy()->startOfMinute();
                $baseStartMin = $baseStart->copy()->startOfMinute();
                if ($clockInMin->gt($baseStartMin)) {
                    $lateMin = intdiv($clockInMin->diffInSeconds($baseStartMin), 60);
                }
            }
            if ($clockOut && $clockOut->lt($baseEnd)) {
                $earlyMin = intdiv($baseEnd->diffInSeconds($clockOut), 60);
            }
            if ($lateMin > 0 || $earlyMin > 0) {
                $lateEarlyCount = 1;
            }
        }

        return [
            'worked' => $worked,
            'holiday_work' => $holidayWork,
            'work_minutes' => $workMinutes,
            'overtime_normal_minutes' => $overtimeNormal,
            'night_minutes' => $nightMinutes,
            'late_early_count' => $lateEarlyCount,
            'late_early_minutes' => $lateMin + $earlyMin,
        ];
    }

    /**
     * このレコードで閾値方式（実働 − 残業閾値）を適用する場合の残業閾値（分）。
     * 適用しない場合は null（＝ベース終業方式にフォールバック）。
     *
     * - 勤務属性が threshold モードでない → null
     * - 閾値が未設定 → null
     * - 切替日（threshold_effective_date）より前の勤務 → null（過去分は従来挙動で据え置き）
     */
    public function overtimeThresholdForRecord(?User $user, Carbon $date, ?AttendancePayrollSetting $setting = null): ?int
    {
        if ($user === null || !$user->work_attribute_id) {
            return null;
        }

        $attr = $user->workAttribute;
        if (!$attr || !$attr->usesThresholdOvertime()) {
            return null;
        }

        $threshold = $attr->overtime_threshold_minutes;
        if ($threshold === null) {
            return null;
        }

        $setting = $setting ?? AttendancePayrollSetting::current();
        $effective = $setting->threshold_effective_date;
        if ($effective !== null) {
            $effStart = $effective instanceof Carbon
                ? $effective->copy()->startOfDay()
                : Carbon::parse((string) $effective)->startOfDay();
            if ($date->copy()->startOfDay()->lt($effStart)) {
                return null;
            }
        }

        return (int) $threshold;
    }

    /**
     * 実働算出に使う休憩控除（分）
     * - break_mode=fixed: 所定固定休憩（scheduled_break_minutes）を控除。休憩打刻は使わない。
     * - それ以外(manual): 休憩打刻の合計を控除。
     */
    public function effectiveBreakMinutes(AttendanceRecord $record, ?User $user): int
    {
        if ($user && $user->usesFixedBreak()) {
            return max(0, (int) $user->scheduled_break_minutes);
        }

        return $this->completedBreakMinutes($record);
    }

    /**
     * 閾値方式の残業（分・丸め前）: 実働（在社 − 休憩控除） − 残業閾値
     */
    public function thresholdRawOvertimeMinutes(AttendanceRecord $record, ?User $user, int $thresholdMinutes): int
    {
        $clockIn = $record->clock_in_at;
        $clockOut = $record->clock_out_at;
        if ($clockIn === null || $clockOut === null || $clockOut->lte($clockIn)) {
            return 0;
        }

        $grossMinutes = intdiv($clockOut->diffInSeconds($clockIn), 60);
        $actualMinutes = max(0, $grossMinutes - $this->effectiveBreakMinutes($record, $user));

        return max(0, $actualMinutes - max(0, $thresholdMinutes));
    }

    /**
     * 閾値方式の残業のうち深夜帯（22:00〜翌5:00）に重なる分。
     * 残業は「実働の末尾」とみなし、退勤から残業分だけ遡った区間で深夜分を数える（休憩控除）。
     */
    private function thresholdOvertimeNightMinutes(AttendanceRecord $record, int $roundedOvertime): int
    {
        $clockIn = $record->clock_in_at;
        $clockOut = $record->clock_out_at;
        if ($roundedOvertime <= 0 || $clockIn === null || $clockOut === null) {
            return 0;
        }

        $otStart = $clockOut->copy()->subMinutes($roundedOvertime);
        if ($otStart->lt($clockIn)) {
            $otStart = $clockIn->copy();
        }

        $night = $this->nightWorkMinutesBetween($record, $otStart, $clockOut);

        return min($night, $roundedOvertime);
    }

    /**
     * 完了した休憩の合計分
     */
    private function completedBreakMinutes(AttendanceRecord $record): int
    {
        return (int) $record->breaks
            ->filter(fn ($b) => $b->start_at !== null && $b->end_at !== null)
            ->sum(fn ($b) => intdiv($b->end_at->diffInSeconds($b->start_at), 60));
    }

    /**
     * [from, to] に挟まれた勤務時間のうち、深夜帯（22:00〜翌5:00）に重なる分（休憩控除）。
     */
    private function nightWorkMinutesBetween(AttendanceRecord $record, Carbon $from, Carbon $to): int
    {
        $clockIn = $record->clock_in_at;
        $clockOut = $record->clock_out_at;
        if (!$clockIn || !$clockOut) {
            return 0;
        }
        $workFrom = $clockIn->gt($from) ? $clockIn->copy() : $from->copy();
        $workTo = $clockOut->lt($to) ? $clockOut->copy() : $to->copy();
        if ($workTo->lte($workFrom)) {
            return 0;
        }

        $night = $this->nightBandMinutes($workFrom, $workTo);

        // 区間内にかかる休憩の深夜分を控除
        foreach ($record->breaks as $b) {
            if ($b->start_at === null || $b->end_at === null) {
                continue;
            }
            $bf = $b->start_at->gt($workFrom) ? $b->start_at : $workFrom;
            $bt = $b->end_at->lt($workTo) ? $b->end_at : $workTo;
            if ($bt->gt($bf)) {
                $night -= $this->nightBandMinutes($bf, $bt);
            }
        }

        return max(0, $night);
    }

    /**
     * [start, end] のうち深夜帯（各日 00:00〜05:00 と 22:00〜24:00）に重なる分（分）。
     */
    private function nightBandMinutes(Carbon $start, Carbon $end): int
    {
        if ($end->lte($start)) {
            return 0;
        }
        $minutes = 0;
        $dayCursor = $start->copy()->startOfDay();
        $lastDay = $end->copy()->startOfDay();
        while ($dayCursor->lte($lastDay)) {
            $windows = [
                [$dayCursor->copy(), $dayCursor->copy()->addHours(5)],       // 00:00〜05:00
                [$dayCursor->copy()->addHours(22), $dayCursor->copy()->addDay()], // 22:00〜24:00
            ];
            foreach ($windows as [$ws, $we]) {
                $s = $start->gt($ws) ? $start : $ws;
                $e = $end->lt($we) ? $end : $we;
                if ($e->gt($s)) {
                    $minutes += intdiv($e->diffInSeconds($s), 60);
                }
            }
            $dayCursor->addDay();
        }

        return $minutes;
    }

    /**
     * 期間内の会社カレンダー patter を Y-m-d => pattern で取得
     *
     * @return array<string, string|null>
     */
    public function loadCalendarPatternsBetween(string $fromYmd, string $toYmd): array
    {
        $rows = CompanyCalendarDay::query()
            ->whereBetween('calendar_date', [$fromYmd, $toYmd])
            ->get(['calendar_date', 'pattern']);

        $map = [];
        foreach ($rows as $row) {
            $key = $row->calendar_date instanceof Carbon
                ? $row->calendar_date->format('Y-m-d')
                : (string) $row->calendar_date;
            $map[$key] = $row->pattern;
        }

        return $map;
    }

    /**
     * 保存しない勤怠レコードで給与用出勤・残業を試算（管理画面シミュレーター用）
     *
     * @param  list<array{start: string, end: string}>  $breakIntervals  同一日付の 'H:i' または 'H:i:s'
     * @return array{
     *   resolved: bool,
     *   error?: string,
     *   base_start_at: string|null,
     *   base_end_at: string|null,
     *   payroll_clock_in_at: string|null,
     *   overtime_minutes_raw: int|null,
     *   overtime_minutes_rounded: int|null,
     *   day_type: string,
     *   pattern: string
     * }
     */
    public function simulatePayroll(
        int $workAttributeId,
        Carbon $date,
        string $pattern,
        ?Carbon $clockIn,
        ?Carbon $clockOut,
        array $breakIntervals = [],
        ?AttendancePayrollSetting $setting = null,
    ): array {
        $pattern = strtoupper(trim($pattern));
        $dateStart = $date->copy()->startOfDay();
        $dayType = $this->dayTypeForDate($dateStart);

        $user = new User(['work_attribute_id' => $workAttributeId]);
        $user->setRelation('workAttribute', WorkAttribute::find($workAttributeId));
        $patternsByDate = [$dateStart->format('Y-m-d') => $pattern];

        $window = $this->resolveBaseWindow($user, $dateStart, $patternsByDate);
        if ($window === null) {
            return [
                'resolved' => false,
                'error' => 'シフト（勤務属性×パターン×平日/土日）を解決できません。マスタを確認してください。',
                'base_start_at' => null,
                'base_end_at' => null,
                'payroll_clock_in_at' => null,
                'overtime_minutes_raw' => null,
                'overtime_minutes_rounded' => null,
                'day_type' => $dayType,
                'pattern' => $pattern,
            ];
        }

        $record = new AttendanceRecord([
            'date' => $dateStart->format('Y-m-d'),
            'clock_in_at' => $clockIn,
            'clock_out_at' => $clockOut,
        ]);
        $record->setRelation('user', $user);

        $breakModels = collect();
        $dayStr = $dateStart->format('Y-m-d');
        foreach ($breakIntervals as $iv) {
            $start = $iv['start'] ?? '';
            $end = $iv['end'] ?? '';
            if ($start === '' || $end === '') {
                continue;
            }
            $breakModels->push(new AttendanceBreak([
                'start_at' => Carbon::parse($dayStr.' '.$start),
                'end_at' => Carbon::parse($dayStr.' '.$end),
            ]));
        }
        $record->setRelation('breaks', $breakModels);

        $setting = $setting ?? AttendancePayrollSetting::current();
        $payload = $this->payrollPayloadForRecord($record, $patternsByDate, $setting);

        return [
            'resolved' => true,
            'base_start_at' => $payload['base_start_at'],
            'base_end_at' => $payload['base_end_at'],
            'payroll_clock_in_at' => $payload['payroll_clock_in_at'],
            'overtime_minutes_raw' => $payload['overtime_minutes_raw'],
            'overtime_minutes_rounded' => $payload['overtime_minutes_rounded'],
            'day_type' => $dayType,
            'pattern' => $pattern,
        ];
    }

    private function combineDateAndTime(Carbon $date, mixed $timeValue): Carbon
    {
        if ($timeValue instanceof Carbon) {
            return $date->copy()->setTime(
                (int) $timeValue->format('G'),
                (int) $timeValue->format('i'),
                (int) $timeValue->format('s')
            );
        }

        $t = (string) $timeValue;

        return Carbon::parse($date->format('Y-m-d').' '.$t);
    }
}
