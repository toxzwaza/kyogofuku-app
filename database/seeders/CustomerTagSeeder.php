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
            ['name' => 'クレーム履歴あり', 'slug' => 'complaint', 'color' => '#ff4d4f'],
            ['name' => '返信忘れ', 'slug' => 'forgot_reply', 'color' => '#faad14'],
            ['name' => '要フォロー', 'slug' => 'need_follow', 'color' => '#1890ff'],
        ]);
    }
}
