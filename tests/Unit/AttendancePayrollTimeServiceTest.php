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

    public function test_round_overtime_30_15(): void
    {
        $s = new AttendancePayrollSetting([
            'overtime_rounding_unit_minutes' => 30,
            'overtime_discard_remainder_upto_minutes' => 15,
        ]);

        $this->assertSame(0, $this->service->roundOvertimeMinutes(0, $s));
        $this->assertSame(30, $this->service->roundOvertimeMinutes(44, $s));
        $this->assertSame(30, $this->service->roundOvertimeMinutes(45, $s));
        $this->assertSame(60, $this->service->roundOvertimeMinutes(46, $s));
        $this->assertSame(60, $this->service->roundOvertimeMinutes(60, $s));
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
}
