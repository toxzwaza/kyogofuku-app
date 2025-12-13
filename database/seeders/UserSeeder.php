<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Shop;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

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
            'name' => '村上 飛羽',
            'furigana' => 'ムラカミ トワ',
            'email' => 'bistowa0819@gmail.com',
            'login_id' => 'to-murakami',
            'password' => Hash::make('Murakami0819'),
        ]);

        // 管理者を岡山店に紐付け
        $okayamaShop = Shop::where('name', '岡山店')->first();
        if ($okayamaShop) {
            $admin->shops()->attach($okayamaShop->id);
        }

        // staff.csvからスタッフデータを読み込む
        $csvPath = database_path('seeders/staff.csv');
        
        if (!file_exists($csvPath)) {
            $this->command->warn('staff.csvが見つかりません。');
            return;
        }

        $file = fopen($csvPath, 'r');
        if ($file === false) {
            $this->command->error('staff.csvを開けませんでした。');
            return;
        }

        // ヘッダー行をスキップ
        fgetcsv($file);

        $users = [];

        // CSVの各行を処理
        while (($row = fgetcsv($file)) !== false) {
            if (count($row) < 5) {
                continue;
            }

            $name = trim($row[0]);
            $furigana = trim($row[1]);
            $loginId = trim($row[2]);
            $password = trim($row[3]);
            $shopName = trim($row[4]);

            // ログインIDが空の場合はスキップ
            if (empty($loginId)) {
                continue;
            }

            // 既存ユーザーを検索、存在しない場合は新規作成
            $user = User::firstOrNew(['login_id' => $loginId]);

            // ユーザーが新規作成の場合のみ、基本情報を設定
            if (!$user->exists) {
                $user->name = $name;
                $user->furigana = $furigana;
                $user->email = $loginId . '@example.com'; // メールアドレスを生成
                $user->password = Hash::make($password);
                $user->save();
            }

            // 店舗との関連付け
            $shop = Shop::where('name', $shopName)->first();
            if ($shop && !$user->shops()->where('shop_id', $shop->id)->exists()) {
                $user->shops()->attach($shop->id);
            }
        }

        fclose($file);
    }
}

