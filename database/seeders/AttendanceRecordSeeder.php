<?php

namespace Database\Seeders;

use App\Models\AttendanceBreak;
use App\Models\AttendanceRecord;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class AttendanceRecordSeeder extends Seeder
{
    /**
     * 勤怠テストデータを全ユーザー（店舗紐付き）に投入する。
     * 過去14日分（本日は含めない）。既存の (user_id, date) はスキップ。
     */
    public function run(): void
    {
        $users = User::with('shops')->get();
        $days = 14;
        $today = Carbon::today();

        foreach ($users as $user) {
            $shops = $user->shops;
            if ($shops->isEmpty()) {
                continue;
            }
            $shop = $shops->firstWhere('pivot.main', true) ?? $shops->first();
            $shopId = $shop->id;

            for ($i = 1; $i <= $days; $i++) {
                $date = $today->copy()->subDays($i);
                $exists = AttendanceRecord::where('user_id', $user->id)->whereDate('date', $date)->exists();
                if ($exists) {
                    continue;
                }

                $clockIn = $date->copy()->setTime(9, 0, 0);
                $clockOut = $date->copy()->setTime(18, 0, 0);

                // 古い日付は承認済、直近2日は申請済/未申請を混在
                if ($i > 2) {
                    $status = AttendanceRecord::STATUS_APPROVED;
                } else {
                    $status = $i === 1 ? AttendanceRecord::STATUS_APPLIED : AttendanceRecord::STATUS_DRAFT;
                }

                $record = AttendanceRecord::create([
                    'user_id' => $user->id,
                    'shop_id' => $shopId,
                    'date' => $date,
                    'clock_in_at' => $clockIn,
                    'clock_out_at' => $clockOut,
                    'status' => $status,
                    'is_manual' => false,
                    'version' => 0,
                ]);

                // 約半数に休憩 12:00-13:00 を1件追加
                if (($user->id + $i) % 2 === 0) {
                    AttendanceBreak::create([
                        'attendance_record_id' => $record->id,
                        'start_at' => $date->copy()->setTime(12, 0, 0),
                        'end_at' => $date->copy()->setTime(13, 0, 0),
                    ]);
                }
            }
        }
    }
}
