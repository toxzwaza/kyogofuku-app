<?php

namespace Database\Seeders;

use App\Models\EventReservation;
use App\Models\ReservationNote;
use App\Models\User;
use Illuminate\Database\Seeder;

class ReservationNoteSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        // 全ユーザーを取得
        $users = User::all();
        if ($users->isEmpty()) {
            return;
        }
        $userIds = $users->pluck('id')->toArray();

        // 全予約を取得
        $reservations = EventReservation::all();

        foreach ($reservations as $reservation) {
            // 各予約に対して0-3件のメモを追加
            $noteCount = rand(0, 3);
            
            for ($i = 0; $i < $noteCount; $i++) {
                ReservationNote::create([
                    'user_id' => $userIds[array_rand($userIds)],
                    'event_reservation_id' => $reservation->id,
                    'content' => $this->getRandomNoteContent($reservation),
                    'created_at' => now()->subDays(rand(0, 30))->subHours(rand(0, 23))->subMinutes(rand(0, 59)),
                    'updated_at' => now()->subDays(rand(0, 30))->subHours(rand(0, 23))->subMinutes(rand(0, 59)),
                ]);
            }
        }
    }

    /**
     * 予約情報に基づいたランダムなメモ内容を生成
     */
    private function getRandomNoteContent($reservation)
    {
        $contents = [
            'お客様から電話がありました。',
            '来店希望の日時を確認しました。',
            'サイズについて相談がありました。',
            '料金について質問がありました。',
            '追加のご要望がありました。',
            '確認事項をメールで送信しました。',
            '来店予定日を調整しました。',
            'お客様の希望を確認しました。',
            'フォローアップが必要です。',
            '詳細を確認中です。',
            'お客様と連絡を取りました。',
            '予約内容を確認しました。',
            '追加情報を確認しました。',
            'お客様の要望を記録しました。',
            '次回の連絡予定日を設定しました。',
        ];

        // 予約の種類に応じたメモ内容を追加
        if ($reservation->reservation_datetime) {
            $contents[] = '予約日時: ' . $reservation->reservation_datetime . ' で確定しました。';
        }

        if ($reservation->venue_id) {
            $contents[] = '会場について確認しました。';
        }

        if ($reservation->considering_plans && count($reservation->considering_plans) > 0) {
            $contents[] = '検討中のプラン: ' . implode(', ', $reservation->considering_plans) . ' について説明しました。';
        }

        if ($reservation->inquiry_message) {
            $contents[] = 'お問い合わせ内容を確認しました。';
        }

        return $contents[array_rand($contents)];
    }
}

