<?php

namespace Database\Seeders;

use App\Models\Shop;
use App\Models\User;
use App\Models\WorkAttribute;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * 勤怠機能テスト用の3アカウントを作成する。
 *
 * - test-general          : 一般スタッフ
 * - test-manager          : 管理者（shop_manager 自店舗のみ承認可）
 * - test-attendance       : 勤怠管理者（attendance_manager 全店舗承認可）
 *
 * 既存ユーザー（login_id 一致）は更新せずスキップ。
 * SECURITY_LOGIN=false の環境ではパスワード入力不要のため、
 * パスワードは固定の `testpass` を入れておくのみ。
 */
class TestAttendanceUserSeeder extends Seeder
{
    public function run(): void
    {
        $shainAttr = WorkAttribute::query()->where('name', '社員')->first();
        if (!$shainAttr) {
            $this->command->warn('WorkAttribute「社員」が見つかりません。WorkAttributeSeeder を先に実行してください。');
            return;
        }

        $shopByName = Shop::query()
            ->whereIn('name', ['岡山店', '城東店', '浜店', '福井店'])
            ->get()
            ->keyBy('name');

        $definitions = [
            [
                'login_id' => 'test-general',
                'name' => 'テスト一般 太郎',
                'furigana' => 'テストイッパン タロウ',
                'attendance_role' => null,
                'shops' => [
                    ['name' => '岡山店', 'main' => true],
                ],
            ],
            [
                'login_id' => 'test-manager',
                'name' => 'テスト管理者 花子',
                'furigana' => 'テストカンリシャ ハナコ',
                'attendance_role' => 'shop_manager',
                'shops' => [
                    ['name' => '岡山店', 'main' => true],
                    ['name' => '城東店', 'main' => false],
                ],
            ],
            [
                'login_id' => 'test-attendance',
                'name' => 'テスト勤怠管理者 次郎',
                'furigana' => 'テストキンタイカンリシャ ジロウ',
                'attendance_role' => 'attendance_manager',
                'shops' => [
                    ['name' => '浜店', 'main' => true],
                ],
            ],
        ];

        foreach ($definitions as $def) {
            $user = User::firstOrNew(['login_id' => $def['login_id']]);
            if ($user->exists) {
                $this->command->info("既存: {$def['login_id']} (id={$user->id}) — 更新せずスキップ");
                continue;
            }

            $user->name = $def['name'];
            $user->furigana = $def['furigana'];
            $user->email = $def['login_id'].'@example.test';
            $user->password = Hash::make('testpass');
            $user->attendance_role = $def['attendance_role'];
            $user->work_attribute_id = $shainAttr->id;
            $user->save();

            foreach ($def['shops'] as $shopRef) {
                $shop = $shopByName->get($shopRef['name']);
                if (!$shop) {
                    $this->command->warn("  店舗「{$shopRef['name']}」が見つかりません。スキップ");
                    continue;
                }
                $user->shops()->attach($shop->id, ['main' => $shopRef['main']]);
            }

            $shopList = collect($def['shops'])->pluck('name')->join(' / ');
            $this->command->info("作成: {$def['login_id']} (id={$user->id}) role=".($def['attendance_role'] ?? 'general')." 店舗=[{$shopList}]");
        }
    }
}
