<?php

namespace Database\Seeders;

use App\Models\Shop;
use Illuminate\Database\Seeder;

class ShopSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $shops = [
            [
                'name' => '岡山店',
                'address' => '岡山県岡山市北区辰巳2-106 トーナン北ビル1',
                'phone' => '086-242-1529',
                'is_active' => true,
            ],
            [
                'name' => '城東店',
                'address' => '岡山県岡山市東区沼1310-1',
                'phone' => '086-297-0529',
                'is_active' => true,
            ],
            [
                'name' => '浜店',
                'address' => '岡山県岡山市中区浜１丁目１２−７',
                'phone' => '0120-391-529',
                'is_active' => true,
            ],
            [
                'name' => '福井店',
                'address' => '福井県福井市花堂南1丁目2-1',
                'phone' => '0776-34-1529',
                'is_active' => true,
            ],
        ];

        foreach ($shops as $shop) {
            Shop::create($shop);
        }
    }
}

