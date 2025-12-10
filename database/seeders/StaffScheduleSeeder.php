<?php

namespace Database\Seeders;

use App\Models\StaffSchedule;
use App\Models\User;
use Illuminate\Database\Seeder;
use Carbon\Carbon;

class StaffScheduleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        // 既存のユーザーを取得（店舗情報も一緒に取得）
        $users = User::with('shops')->get();

        if ($users->isEmpty()) {
            $this->command->warn('ユーザーが存在しないため、スケジュールのシードをスキップします。');
            return;
        }

        $colors = ['#3788d8', '#d73737', '#37d737', '#d7d737', '#d737d7', '#37d7d7'];

        // 各ユーザーに対してサンプルスケジュールを作成
        foreach ($users as $index => $user) {
            $color = $colors[$index % count($colors)];
            
            // ユーザーの所属店舗を取得（最初の店舗を使用、なければ「未所属」）
            $shop = $user->shops->first();
            $shopName = $shop ? $shop->name : '未所属';
            $staffName = $user->name;

            // 今週のスケジュール
            for ($i = 0; $i < 3; $i++) {
                $day = Carbon::now()->startOfWeek()->addDays($i);
                
                // 午前の予定
                $schedule1 = StaffSchedule::create([
                    'user_id' => $user->id,
                    'title' => "[{$shopName}] {$staffName}の店舗業務",
                    'description' => "{$shopName}での{$staffName}の業務対応",
                    'start_at' => $day->copy()->setTime(9, 0),
                    'end_at' => $day->copy()->setTime(12, 0),
                    'all_day' => false,
                    'color' => $color,
                ]);
                // 作成者を参加者として追加
                $schedule1->participantUsers()->sync([$user->id]);

                // 午後の予定
                $schedule2 = StaffSchedule::create([
                    'user_id' => $user->id,
                    'title' => "[{$shopName}] {$staffName}の予約対応",
                    'description' => "{$shopName}での{$staffName}による予約のお客様対応",
                    'start_at' => $day->copy()->setTime(13, 0),
                    'end_at' => $day->copy()->setTime(17, 0),
                    'all_day' => false,
                    'color' => $color,
                ]);
                // 作成者を参加者として追加
                $schedule2->participantUsers()->sync([$user->id]);
            }

            // 来週のスケジュール
            for ($i = 0; $i < 2; $i++) {
                $day = Carbon::now()->addWeek()->startOfWeek()->addDays($i);
                
                // 終日の予定
                $schedule = StaffSchedule::create([
                    'user_id' => $user->id,
                    'title' => "[{$shopName}] {$staffName}の研修・会議",
                    'description' => "{$shopName}での{$staffName}のスタッフ研修・会議",
                    'start_at' => $day->copy()->setTime(0, 0),
                    'end_at' => $day->copy()->setTime(23, 59),
                    'all_day' => true,
                    'color' => $color,
                ]);
                // 作成者を参加者として追加
                $schedule->participantUsers()->sync([$user->id]);
            }

            // 今月の他の日付にもランダムに予定を追加
            $taskTypes = ['顧客対応', '商品管理', '在庫確認', '清掃・整理', 'スタッフミーティング'];
            for ($i = 0; $i < 5; $i++) {
                $randomDay = Carbon::now()->addDays(rand(7, 30));
                $startHour = rand(9, 15);
                $endHour = $startHour + rand(2, 4);
                $taskType = $taskTypes[rand(0, count($taskTypes) - 1)];

                $schedule = StaffSchedule::create([
                    'user_id' => $user->id,
                    'title' => "[{$shopName}] {$staffName}の{$taskType}",
                    'description' => "{$shopName}での{$staffName}による{$taskType}",
                    'start_at' => $randomDay->copy()->setTime($startHour, 0),
                    'end_at' => $randomDay->copy()->setTime($endHour, 0),
                    'all_day' => false,
                    'color' => $color,
                ]);
                // 作成者を参加者として追加
                $schedule->participantUsers()->sync([$user->id]);
            }
        }

        $this->command->info('サンプルスケジュールを登録しました。');
    }
}
