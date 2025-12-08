<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Shop;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        // 管理者ユーザー
        $admin = User::create([
            'name' => '管理者',
            'email' => 'to-murakami@akioka-ltd.jp',
            'login_id' => 'to-murakami',
            'password' => Hash::make('Murakami0819'),
        ]);

        // スタッフユーザー
        $staff1 = User::create([
            'name' => 'スタッフ1',
            'email' => 'staff1@example.com',
            'login_id' => 'staff1',
            'password' => Hash::make('password'),
        ]);

        $staff2 = User::create([
            'name' => 'スタッフ2',
            'email' => 'staff2@example.com',
            'login_id' => 'staff2',
            'password' => Hash::make('password'),
        ]);

        // 店舗との関連付け
        $okayamaShop = Shop::where('name', '岡山店')->first();
        $kurashikiShop = Shop::where('name', '倉敷店')->first();

        if ($okayamaShop) {
            $admin->shops()->attach($okayamaShop->id);
            $staff1->shops()->attach($okayamaShop->id);
        }

        if ($kurashikiShop) {
            $admin->shops()->attach($kurashikiShop->id);
            $staff2->shops()->attach($kurashikiShop->id);
        }
    }
}

