<?php

namespace Tests\Unit;

use App\Models\AttendanceBreak;
use App\Models\AttendancePayrollSetting;
use App\Models\AttendanceRecord;
use App\Models\Shop;
use App\Models\User;
use App\Models\WorkAttribute;
use App\Models\WorkAttributePatternTime;
use App\Services\AttendancePayrollTimeService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AttendancePayrollTimeServiceTest extends TestCase
{
    use RefreshDatabase;

    private AttendancePayrollTimeService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new AttendancePayrollTimeService;
    }

    public function test_round_overtime_floors_to_unit(): void
    {
        // 残業は常に短い方（切り捨て）へ丸める
        $s30 = new AttendancePayrollSetting([
            'overtime_rounding_unit_minutes' => 30,
        ]);
        $this->assertSame(0, $this->service->roundOvertimeMinutes(0, $s30));
        $this->assertSame(30, $this->service->roundOvertimeMinutes(44, $s30));
        $this->assertSame(30, $this->service->roundOvertimeMinutes(45, $s30));
        $this->assertSame(30, $this->service->roundOvertimeMinutes(46, $s30));
        $this->assertSame(30, $this->service->roundOvertimeMinutes(59, $s30));
        $this->assertSame(60, $this->service->roundOvertimeMinutes(60, $s30));

        // 単位15分: 38分 → 30分（20:38 打刻者のケース）
        $s15 = new AttendancePayrollSetting([
            'overtime_rounding_unit_minutes' => 15,
        ]);
        $this->assertSame(30, $this->service->roundOvertimeMinutes(38, $s15));
        $this->assertSame(30, $this->service->roundOvertimeMinutes(44, $s15));
        $this->assertSame(45, $this->service->roundOvertimeMinutes(45, $s15));
        $this->assertSame(0, $this->service->roundOvertimeMinutes(14, $s15));
    }

    public function test_payroll_clock_in_uses_base_when_early(): void
    {
        $s = new AttendancePayrollSetting([
            'start_early_threshold_minutes' => 0,
            'start_rounding_unit_minutes' => 1,
        ]);
        $base = Carbon::parse('2026-03-16 09:00:00');
        $actual = Carbon::parse('2026-03-16 08:30:00');
        $out = $this->service->payrollClockInAt($actual, $base, $s);
        $this->assertTrue($out->equalTo($base));

        $late = Carbon::parse('2026-03-16 09:15:00');
        $this->assertTrue($this->service->payrollClockInAt($late, $base, $s)->equalTo($late));
    }

    public function test_payroll_clock_in_early_before_threshold_uses_actual(): void
    {
        $s = new AttendancePayrollSetting([
            'start_early_threshold_minutes' => 30,
            'start_rounding_unit_minutes' => 1,
        ]);
        $base = Carbon::parse('2026-03-16 09:00:00');

        $this->assertTrue(
            $this->service->payrollClockInAt(Carbon::parse('2026-03-16 08:29:59'), $base, $s)
                ->equalTo(Carbon::parse('2026-03-16 08:29:59'))
        );
        $this->assertTrue(
            $this->service->payrollClockInAt(Carbon::parse('2026-03-16 08:30:00'), $base, $s)->equalTo(Carbon::parse('2026-03-16 08:30:00'))
        );
        $this->assertTrue(
            $this->service->payrollClockInAt(Carbon::parse('2026-03-16 08:45:00'), $base, $s)->equalTo($base)
        );
    }

    public function test_payroll_clock_in_category_matches_branches(): void
    {
        $s = new AttendancePayrollSetting([
            'start_early_threshold_minutes' => 30,
            'start_rounding_unit_minutes' => 1,
        ]);
        $base = Carbon::parse('2026-03-16 09:00:00');

        // 出勤打刻なし
        $this->assertNull($this->service->payrollClockInCategory(null, $base, $s));

        // ベース未解決 → 打刻採用（オレンジ相当）
        $this->assertSame(
            'no_base',
            $this->service->payrollClockInCategory(Carbon::parse('2026-03-16 08:43:00'), null, $s)
        );

        // 早出（しきい値より前）→ 打刻採用（オレンジ相当）
        $this->assertSame(
            'early',
            $this->service->payrollClockInCategory(Carbon::parse('2026-03-16 08:29:59'), $base, $s)
        );
        // 早出境界ちょうど → 打刻採用（オレンジ相当）
        $this->assertSame(
            'early',
            $this->service->payrollClockInCategory(Carbon::parse('2026-03-16 08:30:00'), $base, $s)
        );
        // 早出だがしきい値内 → ベースにそろえる（通常表示）
        $this->assertSame(
            'on_time',
            $this->service->payrollClockInCategory(Carbon::parse('2026-03-16 08:45:00'), $base, $s)
        );
        // ベース開始と同じ「分」→ 定刻扱い（遅刻にしない）
        $this->assertSame(
            'on_time',
            $this->service->payrollClockInCategory(Carbon::parse('2026-03-16 09:00:00'), $base, $s)
        );
        $this->assertSame(
            'on_time',
            $this->service->payrollClockInCategory(Carbon::parse('2026-03-16 09:00:59'), $base, $s)
        );
        // ベース開始より後の「分」→ 遅刻扱いで打刻採用（青相当）
        $this->assertSame(
            'late',
            $this->service->payrollClockInCategory(Carbon::parse('2026-03-16 09:01:00'), $base, $s)
        );
        $this->assertSame(
            'late',
            $this->service->payrollClockInCategory(Carbon::parse('2026-03-16 09:15:00'), $base, $s)
        );

        // しきい値0 の早出 → ベースにそろえる（通常表示）
        $s0 = new AttendancePayrollSetting([
            'start_early_threshold_minutes' => 0,
            'start_rounding_unit_minutes' => 1,
        ]);
        $this->assertSame(
            'on_time',
            $this->service->payrollClockInCategory(Carbon::parse('2026-03-16 08:30:00'), $base, $s0)
        );
    }

    public function test_payroll_clock_out_at_uses_base_end_plus_rounded_overtime(): void
    {
        $baseEnd = Carbon::parse('2026-03-16 18:00:00');

        // 退勤打刻なし → null
        $this->assertNull($this->service->payrollClockOutAt(null, $baseEnd, 30));

        // ベース退勤未解決 → 退勤打刻をそのまま
        $this->assertTrue(
            $this->service->payrollClockOutAt(Carbon::parse('2026-03-16 18:17:00'), null, 0)
                ->equalTo(Carbon::parse('2026-03-16 18:17:00'))
        );

        // 残業あり → ベース退勤 ＋ 丸め後残業
        $this->assertTrue(
            $this->service->payrollClockOutAt(Carbon::parse('2026-03-16 18:40:00'), $baseEnd, 30)
                ->equalTo(Carbon::parse('2026-03-16 18:30:00'))
        );

        // 残業なし → ベース退勤
        $this->assertTrue(
            $this->service->payrollClockOutAt(Carbon::parse('2026-03-16 18:05:00'), $baseEnd, 0)
                ->equalTo($baseEnd)
        );
    }

    public function test_payroll_clock_in_start_rounding_nearest_minute_unit(): void
    {
        $s = new AttendancePayrollSetting([
            'start_early_threshold_minutes' => 30,
            'start_rounding_unit_minutes' => 15,
        ]);
        $base = Carbon::parse('2026-03-16 09:00:00');
        // 8:29 は 8:30 より前（早出）→ 最近接15分 → 8:30
        $out = $this->service->payrollClockInAt(Carbon::parse('2026-03-16 08:29:00'), $base, $s);
        $this->assertSame('08:30:00', $out->format('H:i:s'));
    }

    public function test_payroll_clock_in_threshold_30_round_15_matches_spec_examples(): void
    {
        $s = new AttendancePayrollSetting([
            'start_early_threshold_minutes' => 30,
            'start_rounding_unit_minutes' => 15,
        ]);
        $base = Carbon::parse('2026-03-16 09:00:00');
        $day = '2026-03-16 ';

        $cases = [
            ['08:31:00', '09:00:00'],
            ['08:30:00', '08:30:00'],
            ['08:16:00', '08:15:00'],
            ['08:20:00', '08:15:00'],
            ['08:25:00', '08:30:00'],
        ];
        foreach ($cases as [$in, $expected]) {
            $out = $this->service->payrollClockInAt(Carbon::parse($day.$in), $base, $s);
            $this->assertSame($expected, $out->format('H:i:s'), "in={$in}");
        }
    }

    public function test_payroll_clock_in_without_base_still_applies_start_rounding(): void
    {
        $s = new AttendancePayrollSetting([
            'start_early_threshold_minutes' => 0,
            'start_rounding_unit_minutes' => 30,
        ]);
        $out = $this->service->payrollClockInAt(Carbon::parse('2026-03-16 08:43:00'), null, $s);
        $this->assertSame('08:30:00', $out->format('H:i:s'));
    }

    public function test_resolve_base_differs_weekday_and_weekend(): void
    {
        $attr = WorkAttribute::query()->create(['name' => 'テスト', 'sort_order' => 1]);
        WorkAttributePatternTime::query()->create([
            'work_attribute_id' => $attr->id,
            'pattern' => 'A',
            'day_type' => WorkAttribute::DAY_TYPE_WEEKDAY,
            'work_start_time' => '09:00:00',
            'work_end_time' => '18:00:00',
        ]);
        WorkAttributePatternTime::query()->create([
            'work_attribute_id' => $attr->id,
            'pattern' => 'A',
            'day_type' => WorkAttribute::DAY_TYPE_WEEKEND,
            'work_start_time' => '10:00:00',
            'work_end_time' => '16:00:00',
        ]);

        $user = User::factory()->create(['work_attribute_id' => $attr->id]);

        $patterns = [
            '2026-03-16' => 'A',
            '2026-03-21' => 'A',
        ];

        $mon = Carbon::parse('2026-03-16');
        $sat = Carbon::parse('2026-03-21');

        $wMon = $this->service->resolveBaseWindow($user, $mon, $patterns);
        $this->assertNotNull($wMon);
        $this->assertSame('09:00:00', $wMon['start']->format('H:i:s'));
        $this->assertSame('18:00:00', $wMon['end']->format('H:i:s'));

        $wSat = $this->service->resolveBaseWindow($user, $sat, $patterns);
        $this->assertNotNull($wSat);
        $this->assertSame('10:00:00', $wSat['start']->format('H:i:s'));
        $this->assertSame('16:00:00', $wSat['end']->format('H:i:s'));
    }

    public function test_raw_overtime_deducts_break_after_base_end(): void
    {
        $shop = Shop::query()->create([
            'name' => 'S',
            'is_active' => true,
        ]);
        $user = User::factory()->create();

        $record = AttendanceRecord::query()->create([
            'user_id' => $user->id,
            'shop_id' => $shop->id,
            'date' => '2026-03-16',
            'clock_in_at' => Carbon::parse('2026-03-16 09:00:00'),
            'clock_out_at' => Carbon::parse('2026-03-16 20:00:00'),
            'status' => AttendanceRecord::STATUS_APPROVED,
        ]);

        $baseEnd = Carbon::parse('2026-03-16 18:00:00');

        AttendanceBreak::query()->create([
            'attendance_record_id' => $record->id,
            'start_at' => Carbon::parse('2026-03-16 18:30:00'),
            'end_at' => Carbon::parse('2026-03-16 19:00:00'),
        ]);

        $record->load('breaks');
        $raw = $this->service->rawOvertimeMinutesAfterBaseEnd($record, $baseEnd);
        $this->assertSame(90, $raw);
    }

    public function test_shift_diagnostics_collects_multiple_reasons(): void
    {
        $user = User::factory()->create(['work_attribute_id' => null]);
        $date = Carbon::parse('2026-03-16');

        $diag = $this->service->shiftDiagnostics($user, $date, null);

        $this->assertFalse($diag['available']);
        $this->assertNull($diag['calendar_pattern']);
        $this->assertNull($diag['start_at']);
        $this->assertNull($diag['end_at']);
        $this->assertGreaterThanOrEqual(2, count($diag['help_reasons']));
        $this->assertStringContainsString('勤務属性', implode(' ', $diag['help_reasons']));
        $this->assertStringContainsString('会社カレンダー', implode(' ', $diag['help_reasons']));
    }

    public function test_shift_diagnostics_available_when_configured(): void
    {
        $attr = WorkAttribute::query()->create(['name' => '診断用', 'sort_order' => 1]);
        WorkAttributePatternTime::query()->create([
            'work_attribute_id' => $attr->id,
            'pattern' => 'A',
            'day_type' => WorkAttribute::DAY_TYPE_WEEKDAY,
            'work_start_time' => '09:00:00',
            'work_end_time' => '18:00:00',
        ]);

        $user = User::factory()->create(['work_attribute_id' => $attr->id]);
        $date = Carbon::parse('2026-03-16');
        $patterns = ['2026-03-16' => 'A'];

        $diag = $this->service->shiftDiagnostics($user, $date, $patterns);

        $this->assertTrue($diag['available']);
        $this->assertSame('A', $diag['calendar_pattern']);
        $this->assertSame([], $diag['help_reasons']);
        $this->assertStringContainsString('09:00', (string) $diag['start_at']);
        $this->assertStringContainsString('18:00', (string) $diag['end_at']);
    }

    public function test_shift_diagnostics_reason_when_pattern_times_missing(): void
    {
        $attr = WorkAttribute::query()->create(['name' => '行なし', 'sort_order' => 2]);
        $user = User::factory()->create(['work_attribute_id' => $attr->id]);
        $date = Carbon::parse('2026-03-16');
        $patterns = ['2026-03-16' => 'A'];

        $diag = $this->service->shiftDiagnostics($user, $date, $patterns);

        $this->assertFalse($diag['available']);
        $this->assertSame('A', $diag['calendar_pattern']);
        $this->assertCount(1, $diag['help_reasons']);
        $this->assertStringContainsString('勤務属性マスタ', $diag['help_reasons'][0]);
    }

    public function test_simulate_payroll_resolved_with_overtime(): void
    {
        $attr = WorkAttribute::query()->create(['name' => 'Sim用', 'sort_order' => 90]);
        WorkAttributePatternTime::query()->create([
            'work_attribute_id' => $attr->id,
            'pattern' => 'A',
            'day_type' => WorkAttribute::DAY_TYPE_WEEKDAY,
            'work_start_time' => '09:00:00',
            'work_end_time' => '18:00:00',
        ]);

        $setting = AttendancePayrollSetting::query()->orderBy('id')->firstOrFail();
        $date = Carbon::parse('2026-03-16');
        $in = Carbon::parse('2026-03-16 09:00:00');
        $out = Carbon::parse('2026-03-16 19:00:00');

        $r = $this->service->simulatePayroll($attr->id, $date, 'A', $in, $out, [], $setting);

        $this->assertTrue($r['resolved']);
        $this->assertSame(WorkAttribute::DAY_TYPE_WEEKDAY, $r['day_type']);
        $this->assertSame(60, $r['overtime_minutes_raw']);
        $this->assertNotNull($r['payroll_clock_in_at']);
    }

    public function test_simulate_payroll_unresolved_without_pattern_row(): void
    {
        $attr = WorkAttribute::query()->create(['name' => '行ゼロ', 'sort_order' => 91]);
        $setting = AttendancePayrollSetting::query()->orderBy('id')->firstOrFail();

        $r = $this->service->simulatePayroll(
            $attr->id,
            Carbon::parse('2026-03-16'),
            'A',
            Carbon::parse('2026-03-16 09:00:00'),
            null,
            [],
            $setting,
        );

        $this->assertFalse($r['resolved']);
        $this->assertArrayHasKey('error', $r);
    }

    // ==== 閾値方式（新残業判定）====

    private function makeShop(): Shop
    {
        return Shop::query()->create(['name' => 'S', 'is_active' => true]);
    }

    public function test_effective_break_uses_scheduled_for_fixed_user(): void
    {
        $shop = $this->makeShop();

        // fixed: 所定固定休憩（打刻があっても所定を採用）
        $fixed = User::factory()->create(['break_mode' => 'fixed', 'scheduled_break_minutes' => 85]);
        $rec = AttendanceRecord::query()->create([
            'user_id' => $fixed->id,
            'shop_id' => $shop->id,
            'date' => '2026-03-16',
            'clock_in_at' => Carbon::parse('2026-03-16 09:45:00'),
            'clock_out_at' => Carbon::parse('2026-03-16 19:00:00'),
            'status' => AttendanceRecord::STATUS_APPROVED,
        ]);
        AttendanceBreak::query()->create([
            'attendance_record_id' => $rec->id,
            'start_at' => Carbon::parse('2026-03-16 12:00:00'),
            'end_at' => Carbon::parse('2026-03-16 12:30:00'), // 打刻30分だが無視される
        ]);
        $rec->load(['user', 'breaks']);
        $this->assertSame(85, $this->service->effectiveBreakMinutes($rec, $rec->user));

        // manual: 休憩打刻の合計
        $manual = User::factory()->create(['break_mode' => 'manual', 'scheduled_break_minutes' => null]);
        $rec2 = AttendanceRecord::query()->create([
            'user_id' => $manual->id,
            'shop_id' => $shop->id,
            'date' => '2026-03-16',
            'clock_in_at' => Carbon::parse('2026-03-16 09:45:00'),
            'clock_out_at' => Carbon::parse('2026-03-16 19:00:00'),
            'status' => AttendanceRecord::STATUS_APPROVED,
        ]);
        AttendanceBreak::query()->create([
            'attendance_record_id' => $rec2->id,
            'start_at' => Carbon::parse('2026-03-16 12:00:00'),
            'end_at' => Carbon::parse('2026-03-16 13:00:00'), // 60分
        ]);
        $rec2->load(['user', 'breaks']);
        $this->assertSame(60, $this->service->effectiveBreakMinutes($rec2, $rec2->user));
    }

    public function test_threshold_raw_overtime_is_actual_minus_threshold(): void
    {
        $shop = $this->makeShop();
        $user = User::factory()->create(['break_mode' => 'fixed', 'scheduled_break_minutes' => 60]);
        // 9:00-19:00 = 600分、休憩60分 → 実働540分、閾値480 → 残業60分
        $rec = AttendanceRecord::query()->create([
            'user_id' => $user->id,
            'shop_id' => $shop->id,
            'date' => '2026-03-16',
            'clock_in_at' => Carbon::parse('2026-03-16 09:00:00'),
            'clock_out_at' => Carbon::parse('2026-03-16 19:00:00'),
            'status' => AttendanceRecord::STATUS_APPROVED,
        ]);
        $rec->load(['user', 'breaks']);
        $this->assertSame(60, $this->service->thresholdRawOvertimeMinutes($rec, $rec->user, 480));
        // 閾値ちょうどなら残業0
        $this->assertSame(0, $this->service->thresholdRawOvertimeMinutes($rec, $rec->user, 540));
    }

    public function test_overtime_threshold_for_record_respects_mode_and_effective_date(): void
    {
        $baseEndAttr = WorkAttribute::query()->create([
            'name' => '正社員', 'sort_order' => 1, 'overtime_mode' => 'base_end',
        ]);
        $thAttr = WorkAttribute::query()->create([
            'name' => 'パート8h', 'sort_order' => 2, 'overtime_mode' => 'threshold', 'overtime_threshold_minutes' => 480,
        ]);
        $date = Carbon::parse('2026-08-15');

        $baseEndUser = User::factory()->create(['work_attribute_id' => $baseEndAttr->id]);
        $baseEndUser->load('workAttribute');
        $thUser = User::factory()->create(['work_attribute_id' => $thAttr->id]);
        $thUser->load('workAttribute');

        $noEff = new AttendancePayrollSetting(['threshold_effective_date' => null]);
        $future = new AttendancePayrollSetting(['threshold_effective_date' => '2026-09-01']);
        $past = new AttendancePayrollSetting(['threshold_effective_date' => '2026-08-01']);

        // base_end 属性は常に null
        $this->assertNull($this->service->overtimeThresholdForRecord($baseEndUser, $date, $noEff));
        // threshold 属性・切替日なし → 閾値
        $this->assertSame(480, $this->service->overtimeThresholdForRecord($thUser, $date, $noEff));
        // 切替日以降 → 閾値
        $this->assertSame(480, $this->service->overtimeThresholdForRecord($thUser, $date, $past));
        // 切替日より前 → null（従来挙動へフォールバック）
        $this->assertNull($this->service->overtimeThresholdForRecord($thUser, $date, $future));
        // 勤務属性なし → null
        $none = User::factory()->create(['work_attribute_id' => null]);
        $this->assertNull($this->service->overtimeThresholdForRecord($none, $date, $noEff));
    }

    public function test_monthly_metrics_threshold_mode_overtime(): void
    {
        $shop = $this->makeShop();
        $attr = WorkAttribute::query()->create([
            'name' => 'パート8h', 'sort_order' => 3, 'overtime_mode' => 'threshold', 'overtime_threshold_minutes' => 480,
        ]);
        $user = User::factory()->create([
            'work_attribute_id' => $attr->id, 'break_mode' => 'fixed', 'scheduled_break_minutes' => 60,
        ]);
        // 9:00-19:00、休憩60分 → 実働540分、閾値480 → 残業60分
        $rec = AttendanceRecord::query()->create([
            'user_id' => $user->id,
            'shop_id' => $shop->id,
            'date' => '2026-08-17',
            'clock_in_at' => Carbon::parse('2026-08-17 09:00:00'),
            'clock_out_at' => Carbon::parse('2026-08-17 19:00:00'),
            'status' => AttendanceRecord::STATUS_APPROVED,
        ]);
        $rec->load(['user.workAttribute', 'breaks']);

        $setting = new AttendancePayrollSetting([
            'start_early_threshold_minutes' => 0,
            'start_rounding_unit_minutes' => 1,
            'overtime_rounding_unit_minutes' => 1,
            'threshold_effective_date' => '2026-08-01',
        ]);

        $m = $this->service->monthlyMetricsForRecord($rec, ['2026-08-17' => 'A'], $setting);
        $this->assertTrue($m['worked']);
        $this->assertSame(540, $m['work_minutes']);           // 実働
        $this->assertSame(60, $m['overtime_normal_minutes']); // 閾値超過
        $this->assertSame(0, $m['late_early_count']);         // パートは遅早判定なし
    }

    public function test_monthly_metrics_threshold_before_effective_date_no_overtime(): void
    {
        $shop = $this->makeShop();
        $attr = WorkAttribute::query()->create([
            'name' => 'パート8h', 'sort_order' => 4, 'overtime_mode' => 'threshold', 'overtime_threshold_minutes' => 480,
        ]);
        $user = User::factory()->create([
            'work_attribute_id' => $attr->id, 'break_mode' => 'fixed', 'scheduled_break_minutes' => 60,
        ]);
        // 切替日(9/1)より前(8/17)の勤務 → 閾値方式は適用されず、パターン無しなので残業0
        $rec = AttendanceRecord::query()->create([
            'user_id' => $user->id,
            'shop_id' => $shop->id,
            'date' => '2026-08-17',
            'clock_in_at' => Carbon::parse('2026-08-17 09:00:00'),
            'clock_out_at' => Carbon::parse('2026-08-17 19:00:00'),
            'status' => AttendanceRecord::STATUS_APPROVED,
        ]);
        $rec->load(['user.workAttribute', 'breaks']);

        $setting = new AttendancePayrollSetting([
            'start_early_threshold_minutes' => 0,
            'start_rounding_unit_minutes' => 1,
            'overtime_rounding_unit_minutes' => 1,
            'threshold_effective_date' => '2026-09-01',
        ]);

        $m = $this->service->monthlyMetricsForRecord($rec, ['2026-08-17' => 'A'], $setting);
        $this->assertSame(0, $m['overtime_normal_minutes']);
    }

    public function test_threshold_overtime_night_split(): void
    {
        $shop = $this->makeShop();
        $attr = WorkAttribute::query()->create([
            'name' => 'パート8h', 'sort_order' => 5, 'overtime_mode' => 'threshold', 'overtime_threshold_minutes' => 480,
        ]);
        $user = User::factory()->create([
            'work_attribute_id' => $attr->id, 'break_mode' => 'manual', 'scheduled_break_minutes' => null,
        ]);
        // 14:00-翌0:00 = 600分、休憩0 → 残業120分。末尾120分(22:00-24:00)は全て深夜。
        $rec = AttendanceRecord::query()->create([
            'user_id' => $user->id,
            'shop_id' => $shop->id,
            'date' => '2026-08-17',
            'clock_in_at' => Carbon::parse('2026-08-17 14:00:00'),
            'clock_out_at' => Carbon::parse('2026-08-18 00:00:00'),
            'status' => AttendanceRecord::STATUS_APPROVED,
        ]);
        $rec->load(['user.workAttribute', 'breaks']);

        $setting = new AttendancePayrollSetting([
            'start_early_threshold_minutes' => 0,
            'start_rounding_unit_minutes' => 1,
            'overtime_rounding_unit_minutes' => 1,
            'threshold_effective_date' => '2026-08-01',
        ]);

        $m = $this->service->monthlyMetricsForRecord($rec, ['2026-08-17' => 'A'], $setting);
        $this->assertSame(120, $m['night_minutes']);           // 22-24時の勤務
        $this->assertSame(0, $m['overtime_normal_minutes']);   // 残業120分は全て深夜へ
    }

    // ==== 遅刻判定（ベース開始と同じ分は遅刻にしない）====

    public function test_payroll_clock_in_same_minute_as_base_aligns_to_base(): void
    {
        $s = new AttendancePayrollSetting([
            'start_early_threshold_minutes' => 0,
            'start_rounding_unit_minutes' => 1,
        ]);
        $base = Carbon::parse('2026-03-16 09:00:00');

        // 同じ分（秒差のみ）→ ベース開始にそろえる
        $out = $this->service->payrollClockInAt(Carbon::parse('2026-03-16 09:00:30'), $base, $s);
        $this->assertSame('09:00:00', $out->format('H:i:s'));

        // 次の分 → 実打刻を採用（遅刻）
        $out2 = $this->service->payrollClockInAt(Carbon::parse('2026-03-16 09:01:10'), $base, $s);
        $this->assertSame('09:01:10', $out2->format('H:i:s'));
    }

    public function test_monthly_metrics_late_only_after_base_start_minute(): void
    {
        $shop = $this->makeShop();
        $attr = WorkAttribute::query()->create([
            'name' => '正社員T', 'sort_order' => 6, 'overtime_mode' => 'base_end',
        ]);
        WorkAttributePatternTime::query()->create([
            'work_attribute_id' => $attr->id,
            'pattern' => 'A',
            'day_type' => WorkAttribute::DAY_TYPE_WEEKDAY,
            'work_start_time' => '09:00:00',
            'work_end_time' => '18:00:00',
        ]);
        $user = User::factory()->create([
            'work_attribute_id' => $attr->id, 'break_mode' => 'manual', 'scheduled_break_minutes' => null,
        ]);
        $setting = new AttendancePayrollSetting([
            'start_early_threshold_minutes' => 0,
            'start_rounding_unit_minutes' => 1,
            'overtime_rounding_unit_minutes' => 1,
        ]);
        $patterns = ['2026-08-17' => 'A'];

        // ベース開始と同じ分（09:00:45）→ 遅刻にならない
        $recSame = AttendanceRecord::query()->create([
            'user_id' => $user->id, 'shop_id' => $shop->id, 'date' => '2026-08-17',
            'clock_in_at' => Carbon::parse('2026-08-17 09:00:45'),
            'clock_out_at' => Carbon::parse('2026-08-17 18:00:00'),
            'status' => AttendanceRecord::STATUS_APPROVED,
        ]);
        $recSame->load(['user.workAttribute', 'breaks']);
        $m = $this->service->monthlyMetricsForRecord($recSame, $patterns, $setting);
        $this->assertSame(0, $m['late_early_count']);
        $this->assertSame(0, $m['late_early_minutes']);

        // ベース開始より後の分（09:03:10）→ 遅刻3分
        $recLate = AttendanceRecord::query()->create([
            'user_id' => $user->id, 'shop_id' => $shop->id, 'date' => '2026-08-18',
            'clock_in_at' => Carbon::parse('2026-08-18 09:03:10'),
            'clock_out_at' => Carbon::parse('2026-08-18 18:00:00'),
            'status' => AttendanceRecord::STATUS_APPROVED,
        ]);
        $recLate->load(['user.workAttribute', 'breaks']);
        $m2 = $this->service->monthlyMetricsForRecord($recLate, ['2026-08-18' => 'A'], $setting);
        $this->assertSame(1, $m2['late_early_count']);
        $this->assertSame(3, $m2['late_early_minutes']);
    }
}
