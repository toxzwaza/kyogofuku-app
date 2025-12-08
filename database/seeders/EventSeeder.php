<?php

namespace Database\Seeders;

use App\Models\Event;
use App\Models\Shop;
use Illuminate\Database\Seeder;
use Carbon\Carbon;

class EventSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $okayamaShop = Shop::where('name', '岡山店')->first();
        $kurashikiShop = Shop::where('name', '倉敷店')->first();

        // 予約フォームのイベント1
        $reservationEvent1 = Event::create([
            'slug' => 'furi-winter-festival-2025',
            'title' => '冬の振袖大祭典',
            'description' => '冬の特別な振袖をご試着・ご予約いただける大祭典です。最新のトレンド振袖から伝統的なデザインまで豊富に取り揃え、専門スタッフが丁寧にご相談を承ります。成人式や卒業式に向けた振袖選びをお手伝いいたしますので、ぜひこの機会にご参加ください。',
            'form_type' => 'reservation',
            'start_at' => Carbon::now()->subDays(7)->format('Y-m-d'),
            'end_at' => Carbon::now()->addMonths(3)->format('Y-m-d'),
            'is_public' => true,
        ]);

        // 予約フォームのイベント2
        $reservationEvent2 = Event::create([
            'slug' => 'hakama-rental-2025',
            'title' => '卒業袴・二尺袖のレンタルは「京呉服 好一」へ',
            'description' => '卒業袴を選ぶなら「京呉服 好一」へお任せ下さい。「京呉服 好一」ではお客様のご要望に応じて卒業袴のレンタル・お手入れなどサポートいたします。',
            'form_type' => 'reservation',
            'start_at' => Carbon::now()->subDays(7)->format('Y-m-d'),
            'end_at' => Carbon::now()->addMonths(3)->format('Y-m-d'),
            'is_public' => true,
        ]);

        // 資料請求フォームのイベント
        $documentEvent = Event::create([
            'slug' => 'document-request-2025',
            'title' => '資料請求イベント',
            'description' => '資料請求フォームのサンプルイベントです。',
            'form_type' => 'document',
            'start_at' => Carbon::now()->subDays(7)->format('Y-m-d'),
            'end_at' => Carbon::now()->addMonths(3)->format('Y-m-d'),
            'is_public' => true,
        ]);

        // 問い合わせフォームのイベント
        $contactEvent = Event::create([
            'slug' => 'contact-2025',
            'title' => 'お問い合わせイベント',
            'description' => 'お問い合わせフォームのサンプルイベントです。',
            'form_type' => 'contact',
            'start_at' => Carbon::now()->subDays(7)->format('Y-m-d'),
            'end_at' => Carbon::now()->addMonths(3)->format('Y-m-d'),
            'is_public' => true,
        ]);

        // 店舗との関連付け
        if ($okayamaShop) {
            $reservationEvent1->shops()->attach($okayamaShop->id);
            $documentEvent->shops()->attach($okayamaShop->id);
            $contactEvent->shops()->attach($okayamaShop->id);
        }

        if ($kurashikiShop) {
            $reservationEvent1->shops()->attach($kurashikiShop->id);
            $reservationEvent2->shops()->attach($kurashikiShop->id);
        }
    }
}

