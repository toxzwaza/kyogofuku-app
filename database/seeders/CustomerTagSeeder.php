<?php

namespace Database\Seeders;

use App\Models\CustomerTag;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class CustomerTagSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        //
        CustomerTag::insert([
            ['name' => 'クレーム履歴あり', 'color' => '#ff4d4f'],
            ['name' => '返信忘れ', 'color' => '#faad14'],
            ['name' => '要フォロー', 'color' => '#1890ff'],
        ]);
    }
}
