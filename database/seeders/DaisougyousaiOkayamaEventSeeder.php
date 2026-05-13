<?php

namespace Database\Seeders;

use App\Models\Event;
use App\Models\EventTimeslot;
use App\Models\Shop;
use App\Models\Venue;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

/**
 * 大創業祭 岡山版（京呉服 好一・岡山店/城東店）
 *
 * 1イベント・2会場予約。
 *   岡山店 (venue=1)：2026-05-28 (木) 〜 2026-05-30 (土)
 *   城東店 (venue=9)：2026-05-31 (日) 〜 2026-06-01 (月)
 *
 * timeslot は各日 10:00〜17:00、1時間ごと（10/11/12/13/14/15/16時の7枠）。
 *   岡山店：3日 × 7枠 = 21枠
 *   城東店：2日 × 7枠 = 14枠
 *   合計：35枠（capacity=5 / 枠）
 *
 * 既存 shops / venues を流用：
 *   shops:  岡山店=1, 城東店=2
 *   venues: 京呉服好一 岡山店=1, 京呉服好一 城東店=9
 *
 * 何度実行しても安全（slug + (event,venue,start_at) で upsert）。
 */
class DaisougyousaiOkayamaEventSeeder extends Seeder
{
    /** 各 timeslot のデフォルト容量 */
    private const TIMESLOT_CAPACITY = 5;

    /** 各日に生成する開始時刻（10:00〜16:00 の枠で 10〜17 時を表現） */
    private const SLOT_START_HOURS = [10, 11, 12, 13, 14, 15, 16];

    public function run(): void
    {
        $formSchema = [
            ['key' => 'name', 'type' => 'text', 'label' => 'お名前', 'required' => true, 'placeholder' => '山田 花子'],
            ['key' => 'furigana', 'type' => 'text', 'label' => 'フリガナ', 'required' => true, 'placeholder' => 'ヤマダ ハナコ'],
            ['key' => 'phone', 'type' => 'tel', 'label' => '電話番号', 'required' => true, 'placeholder' => '090-1234-5678'],
            [
                'key' => 'venue_id',
                'type' => 'select',
                'label' => 'ご来場店舗',
                'required' => true,
                'placeholder' => 'ご希望の店舗をお選びください',
                'options_from' => 'event_venues',
                'help' => '岡山店：5/28(木)〜5/30(土) ／ 城東店：5/31(日)〜6/1(月)。',
            ],
            [
                'key' => 'visit_slot',
                'help' => 'ご来場店舗を選ぶと、来場可能な日時が表示されます。10:00〜17:00、1時間ごとの枠からお選びください。',
                'type' => 'timeslot',
                'label' => 'ご来場希望日時',
                'required' => true,
                'placeholder' => 'ご希望の日時をお選びください',
                'filter_by_venue_field' => 'venue_id',
            ],
            [
                'key' => 'bring_date',
                'help' => '3点以上の事前持込で『トリマス』プレゼントなど、お得な特典がございます。可能な限り、事前のお持ち込みをおすすめいたします。',
                'type' => 'select',
                'label' => '事前持込日',
                'required' => false,
                'placeholder' => 'ご希望の日時をお選びください（任意）',
                'auto_options' => 'business_hours_until_event',
            ],
            [
                'key' => 'bring_count',
                'max' => 50,
                'min' => 1,
                'help' => '事前持込日を選択された方のみご記入ください。',
                'type' => 'number',
                'label' => '持込予定枚数',
                'default' => 1,
                'show_if' => ['op' => 'not_empty', 'key' => 'bring_date'],
                'required' => false,
            ],
        ];

        $event = Event::updateOrCreate(
            ['slug' => 'souden_2026_05_okayama'],
            [
                'title' => '大創業祭 in 岡山 【5月28日 〜 6月1日】',
                'description' => 'タンスのきものとジュエリー、まるごと整理。京呉服 好一 大創業祭。岡山店：5/28(木)〜5/30(土)／城東店：5/31(日)〜6/1(月)。',
                'form_type' => 'reservation',
                'start_at' => '2026-05-28',
                'end_at' => '2026-06-01',
                'is_public' => true,
                'lp_design_slug' => 'daisougyousai_okayama',
                'success_text' => 'oteire2026',
                'form_schema' => $formSchema,
            ]
        );

        // 店舗関連付け（岡山店, 城東店）
        $shopIds = Shop::whereIn('name', ['岡山店', '城東店'])->pluck('id')->all();
        if (! empty($shopIds)) {
            $event->shops()->syncWithoutDetaching($shopIds);
        }

        // 会場関連付け（京呉服好一 岡山店, 京呉服好一 城東店）
        $venues = Venue::whereIn('name', ['京呉服好一 岡山店', '京呉服好一 城東店'])
            ->get()
            ->keyBy('name');
        $okayamaVenueId = $venues->get('京呉服好一 岡山店')?->id;
        $jotoVenueId    = $venues->get('京呉服好一 城東店')?->id;

        if ($okayamaVenueId && $jotoVenueId) {
            $event->venues()->syncWithoutDetaching([$okayamaVenueId, $jotoVenueId]);
        }

        // ===== timeslot 生成 =====
        $slotPlans = [
            $okayamaVenueId => [
                Carbon::create(2026, 5, 28),
                Carbon::create(2026, 5, 29),
                Carbon::create(2026, 5, 30),
            ],
            $jotoVenueId => [
                Carbon::create(2026, 5, 31),
                Carbon::create(2026, 6, 1),
            ],
        ];

        $created = 0;
        $updated = 0;
        foreach ($slotPlans as $venueId => $dates) {
            if (! $venueId) {
                continue;
            }
            foreach ($dates as $date) {
                foreach (self::SLOT_START_HOURS as $hour) {
                    $startAt = $date->copy()->setTime($hour, 0, 0);

                    $slot = EventTimeslot::firstOrNew([
                        'event_id' => $event->id,
                        'venue_id' => $venueId,
                        'start_at' => $startAt,
                    ]);
                    $isNew = ! $slot->exists;
                    $slot->capacity = self::TIMESLOT_CAPACITY;
                    $slot->is_active = true;
                    $slot->save();
                    $isNew ? $created++ : $updated++;
                }
            }
        }

        $this->command?->info(sprintf(
            '✔ 大創業祭 岡山版を登録: event_id=%d slug=%s shops=%s venues=%s timeslots(new=%d, kept=%d)',
            $event->id,
            $event->slug,
            implode(',', $shopIds),
            implode(',', [$okayamaVenueId, $jotoVenueId]),
            $created,
            $updated
        ));
    }
}
