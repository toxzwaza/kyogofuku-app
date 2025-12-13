<?php

namespace Database\Seeders;

use App\Models\Plan;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class PlanSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        //
        Plan::insert([
            ['name' => '袴R', 'code' => 'hakama_r', 'base_price' => 124100],
            ['name' => '振袖フルセット', 'code' => 'furisode_full', 'base_price' => 198000],

            // 追加プラン（仮金額）
            ['name' => '購入', 'code' => 'purchase', 'base_price' => 350000],
            ['name' => 'レンタル', 'code' => 'rental', 'base_price' => 180000],
            ['name' => 'オーダーレンタル', 'code' => 'order_rental', 'base_price' => 280000],
            ['name' => 'プレタレンタル', 'code' => 'pret_a_rental', 'base_price' => 230000],

            ['name' => 'ママ振(小物レンタル)', 'code' => 'mamafuri_accessory_rental', 'base_price' => 50000],
            ['name' => 'ママ振(小物購入)', 'code' => 'mamafuri_accessory_purchase', 'base_price' => 80000],

            ['name' => 'フォトプラン', 'code' => 'photo_plan', 'base_price' => 60000],
            ['name' => 'お手持ち', 'code' => 'bring_own', 'base_price' => 30000],
            ['name' => '姉振り', 'code' => 'sister_furisode', 'base_price' => 40000],
        ]);
    }
}
