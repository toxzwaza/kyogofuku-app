<?php

namespace Database\Seeders;

use App\Models\Event;
use App\Models\EventReservation;
use App\Models\EventTimeslot;
use App\Models\Venue;
use Illuminate\Database\Seeder;
use Carbon\Carbon;

class EventReservationSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        // 予約フォームのイベント
        $reservationEvent = Event::where('form_type', 'reservation')->first();
        if ($reservationEvent) {
            $timeslots = EventTimeslot::where('event_id', $reservationEvent->id)
                ->where('is_active', true)
                ->get();

            $venues = $reservationEvent->venues()->where('is_active', true)->get();
            $venueIds = $venues->pluck('id')->toArray();

            // 各予約枠に対して1-2件の予約を作成
            foreach ($timeslots as $index => $timeslot) {
                $reservationCount = rand(1, 2); // 各枠に1-2件の予約
                
                for ($i = 0; $i < $reservationCount; $i++) {
                    $currentYear = Carbon::now()->year;
                    $birthYear = $currentYear - rand(18, 25);
                    $birthMonth = rand(1, 12);
                    $birthDay = rand(1, 28);
                    $parkingUsage = rand(0, 1) ? 'あり' : 'なし';
                    
                    EventReservation::create([
                        'event_id' => $reservationEvent->id,
                        'name' => $this->getRandomName(),
                        'email' => $this->getRandomEmail(),
                        'phone' => $this->getRandomPhone(),
                        // 予約フォーム専用フィールド
                        'venue_id' => !empty($venueIds) ? $venueIds[array_rand($venueIds)] : null,
                        'has_visited_before' => rand(0, 1) === 1,
                        'address' => $this->getRandomAddress(),
                        'birth_date' => Carbon::create($birthYear, $birthMonth, $birthDay)->format('Y-m-d'),
                        'seijin_year' => $currentYear + rand(0, 2),
                        'referred_by_name' => rand(0, 1) ? $this->getRandomName() : null,
                        'reservation_datetime' => $timeslot->start_at->format('Y-m-d H:i:s'),
                        'furigana' => $this->getRandomFurigana(),
                        'school_name' => $this->getRandomSchoolName(),
                        'parking_usage' => $parkingUsage,
                        'parking_car_count' => $parkingUsage === 'あり' ? rand(1, 2) : null,
                        'considering_plans' => $this->getRandomPlans(),
                        'inquiry_message' => rand(0, 1) ? $this->getRandomMessage() : null,
                        // 予約フォームでは使用しないフィールドはnull
                        'request_method' => null,
                        'postal_code' => null,
                        'privacy_agreed' => false,
                        'heard_from' => null,
                    ]);
                }
            }
        }

        // 資料請求フォームのイベント
        $documentEvent = Event::where('form_type', 'document')->first();
        if ($documentEvent) {
            // 資料請求のダミーデータを5件作成
            for ($i = 0; $i < 5; $i++) {
                $currentYear = Carbon::now()->year;
                $birthYear = $currentYear - rand(18, 25);
                $birthMonth = rand(1, 12);
                $birthDay = rand(1, 28);
                
                EventReservation::create([
                    'event_id' => $documentEvent->id,
                    'name' => $this->getRandomName(),
                    'email' => $this->getRandomEmail(),
                    'phone' => $this->getRandomPhone(),
                    // 資料請求フォーム専用フィールド
                    'request_method' => rand(0, 1) ? '郵送' : 'デジタルカタログ',
                    'postal_code' => $this->getRandomPostalCode(),
                    'address' => $this->getRandomAddress(),
                    'birth_date' => Carbon::create($birthYear, $birthMonth, $birthDay)->format('Y-m-d'),
                    'furigana' => $this->getRandomFurigana(),
                    'privacy_agreed' => true,
                    'inquiry_message' => rand(0, 1) ? $this->getRandomMessage() : null,
                    // 資料請求フォームでは使用しないフィールドはnull
                    'venue_id' => null,
                    'has_visited_before' => false,
                    'seijin_year' => null,
                    'referred_by_name' => null,
                    'reservation_datetime' => null,
                    'school_name' => null,
                    'parking_usage' => null,
                    'parking_car_count' => null,
                    'considering_plans' => null,
                    'heard_from' => null,
                ]);
            }
        }

        // お問い合わせフォームのイベント
        $contactEvent = Event::where('form_type', 'contact')->first();
        if ($contactEvent) {
            // 問い合わせのダミーデータを5件作成
            for ($i = 0; $i < 5; $i++) {
                EventReservation::create([
                    'event_id' => $contactEvent->id,
                    'name' => $this->getRandomName(),
                    'email' => $this->getRandomEmail(),
                    'phone' => $this->getRandomPhone(),
                    // お問い合わせフォーム専用フィールド
                    'heard_from' => rand(0, 1) ? 'メール' : '電話',
                    'inquiry_message' => $this->getRandomMessage(),
                    // お問い合わせフォームでは使用しないフィールドはnull
                    'request_method' => null,
                    'postal_code' => null,
                    'venue_id' => null,
                    'has_visited_before' => false,
                    'address' => null,
                    'birth_date' => null,
                    'seijin_year' => null,
                    'referred_by_name' => null,
                    'reservation_datetime' => null,
                    'furigana' => null,
                    'school_name' => null,
                    'parking_usage' => null,
                    'parking_car_count' => null,
                    'considering_plans' => null,
                    'privacy_agreed' => false,
                ]);
            }
        }
    }

    private function getRandomName()
    {
        $firstNames = ['花子', '美咲', 'さくら', 'あかり', 'みお', 'ゆい', 'あや', 'なな', 'みゆき', 'えみ'];
        $lastNames = ['山田', '佐藤', '鈴木', '田中', '渡辺', '伊藤', '中村', '小林', '加藤', '吉田'];
        return $lastNames[array_rand($lastNames)] . $firstNames[array_rand($firstNames)];
    }

    private function getRandomEmail()
    {
        $domains = ['example.com', 'test.com', 'sample.jp', 'demo.co.jp'];
        $name = strtolower(str_replace([' ', '　'], '', $this->getRandomName()));
        return $name . rand(1, 999) . '@' . $domains[array_rand($domains)];
    }

    private function getRandomPhone()
    {
        return '0' . rand(700, 999) . '-' . rand(1000, 9999) . '-' . rand(1000, 9999);
    }

    private function getRandomAddress()
    {
        $addresses = [
            '岡山県岡山市北区',
            '岡山県倉敷市',
            '岡山県岡山市中区',
            '岡山県岡山市南区',
            '岡山県総社市',
        ];
        return $addresses[array_rand($addresses)] . rand(1, 999) . '-' . rand(1, 99);
    }

    private function getRandomFurigana()
    {
        $furiganas = ['ヤマダハナコ', 'サトウミサキ', 'スズキサクラ', 'タナカアカリ', 'ワタナベミオ'];
        return $furiganas[array_rand($furiganas)];
    }

    private function getRandomSchoolName()
    {
        $schools = ['岡山県立高等学校', '倉敷市立中学校', '私立○○高等学校', '県立○○中学校', null];
        return $schools[array_rand($schools)];
    }

    private function getRandomPlans()
    {
        $allPlans = [
            '振袖レンタルプラン',
            '振袖購入プラン',
            'ママ振りフォトプラン',
            'フォトレンタルプラン',
        ];
        
        // 0-3個のプランをランダムに選択
        $count = rand(0, 3);
        if ($count === 0) {
            return null;
        }
        
        shuffle($allPlans);
        return array_slice($allPlans, 0, $count);
    }

    private function getRandomMessage()
    {
        $messages = [
            'よろしくお願いします。',
            '詳細を教えてください。',
            '早めに予約したいです。',
            '質問があります。',
            'お時間をいただきたいです。',
            'よろしくお願いいたします。',
            '料金について知りたいです。',
            'サイズについて相談したいです。',
        ];
        return $messages[array_rand($messages)];
    }

    private function getRandomPostalCode()
    {
        // 岡山県の郵便番号の例（700-0000形式）
        $prefixes = ['700', '701', '702', '710', '711', '712', '713', '714', '715', '716', '717', '718', '719'];
        $prefix = $prefixes[array_rand($prefixes)];
        $suffix = str_pad(rand(0, 9999), 4, '0', STR_PAD_LEFT);
        return $prefix . '-' . $suffix;
    }
}
