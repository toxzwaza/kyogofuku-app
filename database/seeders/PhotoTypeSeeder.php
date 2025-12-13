<?php

namespace Database\Seeders;

use App\Models\PhotoType;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class PhotoTypeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        //
        PhotoType::insert([
            ['name' => '全身', 'code' => 'full_body', 'sort_order' => 1],
            ['name' => '半身アップ', 'code' => 'half_body', 'sort_order' => 2],
            ['name' => '商品一覧', 'code' => 'product_list', 'sort_order' => 3],
            ['name' => '成約伝票', 'code' => 'contract_document', 'sort_order' => 4],
            ['name' => 'その他', 'code' => 'other', 'sort_order' => 99],
        ]);
    }
}
