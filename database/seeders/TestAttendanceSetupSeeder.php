<?php

namespace Database\Seeders;

use App\Models\CompanyCalendarDay;
use App\Models\WorkAttribute;
use App\Models\WorkAttributePatternTime;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

/**
 * 勤怠機能テストの一括セットアップ。
 *
 * 1. WorkAttribute「社員」を確保し、A/B/C × 平日/土日 の業務時間を設定
 * 2. テストユーザー3名を作成（TestAttendanceUserSeeder へ委譲）
 * 3. 当月の会社カレンダーパターンを投入（平日=A、土日=B、未設定の日のみ）
 * 4. 過去14日分の勤怠を全ユーザーに投入（AttendanceRecordSeeder へ委譲）
 *
 * 既存のレコードには上書きしない（updateOrCreate / firstOrCreate / 重複チェック）。
 *
 * 実行: php artisan db:seed --class=TestAttendanceSetupSeeder
 */
class TestAttendanceSetupSeeder extends Seeder
{
    public function run(): void
    {
        $this->command->info('=== 勤怠テストセットアップ開始 ===');

        // 1. 勤務属性「社員」と業務時間
        $this->setupShainWorkAttribute();

        // 2. テストユーザー
        $this->call(TestAttendanceUserSeeder::class);

        // 3. 当月の会社カレンダー
        $this->setupCurrentMonthCalendar();

        // 4. 過去14日の勤怠
        $this->call(AttendanceRecordSeeder::class);

        $this->command->info('=== 勤怠テストセットアップ完了 ===');
    }

    private function setupShainWorkAttribute(): void
    {
        $shain = WorkAttribute::query()->firstOrCreate(
            ['name' => '社員'],
            ['sort_order' => 1]
        );

        // 平日/土日 × A/B/C のパターン時間
        $patterns = [
            ['pattern' => 'A', 'day_type' => 'weekday', 'work_start_time' => '09:00:00', 'work_end_time' => '18:00:00'],
            ['pattern' => 'B', 'day_type' => 'weekday', 'work_start_time' => '10:00:00', 'work_end_time' => '19:00:00'],
            ['pattern' => 'C', 'day_type' => 'weekday', 'work_start_time' => '13:00:00', 'work_end_time' => '22:00:00'],
            ['pattern' => 'A', 'day_type' => 'weekend', 'work_start_time' => '09:00:00', 'work_end_time' => '18:00:00'],
            ['pattern' => 'B', 'day_type' => 'weekend', 'work_start_time' => '10:00:00', 'work_end_time' => '19:00:00'],
            ['pattern' => 'C', 'day_type' => 'weekend', 'work_start_time' => '13:00:00', 'work_end_time' => '22:00:00'],
        ];

        foreach ($patterns as $row) {
            WorkAttributePatternTime::query()->updateOrCreate(
                [
                    'work_attribute_id' => $shain->id,
                    'pattern' => $row['pattern'],
                    'day_type' => $row['day_type'],
                ],
                [
                    'work_start_time' => $row['work_start_time'],
                    'work_end_time' => $row['work_end_time'],
                ]
            );
        }

        $this->command->info("勤務属性「社員」(id={$shain->id}) と 6パターンの業務時間を整備");
    }

    private function setupCurrentMonthCalendar(): void
    {
        $start = Carbon::now()->startOfMonth();
        $end = Carbon::now()->endOfMonth();

        $created = 0;
        $skipped = 0;
        for ($d = $start->copy(); $d->lte($end); $d->addDay()) {
            $date = $d->format('Y-m-d');
            $existing = CompanyCalendarDay::query()->where('calendar_date', $date)->first();
            if ($existing) {
                $skipped++;
                continue;
            }
            $pattern = $d->isWeekend() ? 'B' : 'A';
            CompanyCalendarDay::query()->create([
                'calendar_date' => $date,
                'pattern' => $pattern,
            ]);
            $created++;
        }

        $this->command->info("会社カレンダー: 当月 新規作成 {$created} 日 / スキップ（既存）{$skipped} 日");
    }
}
