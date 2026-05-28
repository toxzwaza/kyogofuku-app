<?php

namespace Database\Seeders;

use App\Models\Customer;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * マニュアル用サンプル顧客を 8 名作成する。
 *
 * Playwright スクリーンショット撮影時に、本番由来の個人情報を映さずに
 * 多様な状態（予約あり／前撮りあり／LINE連携／制約発行中など）を見せるための種データ。
 *
 * 名前は架空（典型的な名字+名）／電話は 090-0000-XXXX のパターンで
 * 一目で「サンプル顧客」と分かる体裁にしてある。
 *
 * 同名の既存サンプル顧客があれば updateOrCreate でID固定。
 * 本番DB（kyogofuku_db）には絶対に投入しないこと（管理画面のサンプルが本番顧客に紛れる事故防止）。
 */
class ManualSampleCustomerSeeder extends Seeder
{
    public function run(): void
    {
        // 安全装置: 本番DB（kyogofuku_db）への投入を禁止
        if (DB::connection()->getDatabaseName() === 'kyogofuku_db') {
            $this->command->error('本番DB(kyogofuku_db)へのサンプル顧客投入は禁止されています。');
            return;
        }

        $now = now();

        $samples = [
            // 1. 山田 花子 — フル機能サンプル（岡山店・成人式予定2027年）
            [
                'shop_id' => 1,
                'name' => 'サンプル 花子',
                'kana' => 'サンプル ハナコ',
                'guardian_name' => 'サンプル 太郎',
                'guardian_name_kana' => 'サンプル タロウ',
                'birth_date' => '2007-04-15',
                'coming_of_age_year' => 2027,
                'ceremony_area_id' => 2, // 岡山市
                'phone_number' => '090-0000-0001',
                'postal_code' => '700-0001',
                'address' => '岡山県岡山市北区サンプル町1-1-1',
                'email' => 'sample01@example.com',
                'school_name' => 'サンプル高校',
                'staff_name' => '担当者A',
                'remarks' => 'マニュアル用サンプル顧客（フル機能用）',
            ],
            // 2. 鈴木 美咲 — LINE連携サンプル（城東店）
            [
                'shop_id' => 2,
                'name' => 'サンプル 美咲',
                'kana' => 'サンプル ミサキ',
                'guardian_name' => 'サンプル 一郎',
                'guardian_name_kana' => 'サンプル イチロウ',
                'birth_date' => '2007-08-22',
                'coming_of_age_year' => 2027,
                'ceremony_area_id' => 2,
                'phone_number' => '090-0000-0002',
                'postal_code' => '700-0002',
                'address' => '岡山県岡山市北区サンプル町2-2-2',
                'email' => 'sample02@example.com',
                'school_name' => 'サンプル高校',
                'staff_name' => '担当者B',
                'remarks' => 'マニュアル用サンプル顧客（LINE連携サンプル）',
            ],
            // 3. 田中 結衣 — 制約発行中・未署名（福井店）
            [
                'shop_id' => 4,
                'name' => 'サンプル 結衣',
                'kana' => 'サンプル ユイ',
                'guardian_name' => 'サンプル 由美',
                'guardian_name_kana' => 'サンプル ユミ',
                'birth_date' => '2006-12-05',
                'coming_of_age_year' => 2026,
                'ceremony_area_id' => null,
                'phone_number' => '090-0000-0003',
                'postal_code' => '910-0001',
                'address' => '福井県福井市サンプル町3-3-3',
                'email' => 'sample03@example.com',
                'school_name' => 'サンプル女子高校',
                'staff_name' => '担当者C',
                'remarks' => 'マニュアル用サンプル顧客（制約発行中・未署名）',
            ],
            // 4. 佐藤 真央 — 新規登録直後（岡山店、ほぼ未入力）
            [
                'shop_id' => 1,
                'name' => 'サンプル 真央',
                'kana' => 'サンプル マオ',
                'guardian_name' => null,
                'guardian_name_kana' => null,
                'birth_date' => null,
                'coming_of_age_year' => 2028,
                'ceremony_area_id' => null,
                'phone_number' => '090-0000-0004',
                'postal_code' => null,
                'address' => null,
                'email' => null,
                'school_name' => null,
                'staff_name' => null,
                'remarks' => 'マニュアル用サンプル顧客（新規登録直後の最小情報サンプル）',
            ],
            // 5. 渡辺 葵 — タグ複数・メモあり（岡山店）
            [
                'shop_id' => 1,
                'name' => 'サンプル 葵',
                'kana' => 'サンプル アオイ',
                'guardian_name' => 'サンプル みどり',
                'guardian_name_kana' => 'サンプル ミドリ',
                'birth_date' => '2007-02-10',
                'coming_of_age_year' => 2027,
                'ceremony_area_id' => 2,
                'phone_number' => '090-0000-0005',
                'postal_code' => '706-0001',
                'address' => '岡山県玉野市サンプル町5-5-5',
                'email' => 'sample05@example.com',
                'school_name' => 'サンプル中等部',
                'staff_name' => '担当者A',
                'remarks' => 'マニュアル用サンプル顧客（タグ・メモ機能サンプル）',
            ],
            // 6. 加藤 莉子 — 前撮りキャンセル履歴（城東店）
            [
                'shop_id' => 2,
                'name' => 'サンプル 莉子',
                'kana' => 'サンプル リコ',
                'guardian_name' => 'サンプル 健太',
                'guardian_name_kana' => 'サンプル ケンタ',
                'birth_date' => '2006-07-18',
                'coming_of_age_year' => 2026,
                'ceremony_area_id' => 2,
                'phone_number' => '090-0000-0006',
                'postal_code' => '703-0001',
                'address' => '岡山県岡山市中区サンプル町6-6-6',
                'email' => 'sample06@example.com',
                'school_name' => 'サンプル高校',
                'staff_name' => '担当者B',
                'remarks' => 'マニュアル用サンプル顧客（前撮りキャンセル履歴サンプル）',
            ],
            // 7. 小林 結奈 — イベント予約あり（城東店）
            [
                'shop_id' => 2,
                'name' => 'サンプル 結奈',
                'kana' => 'サンプル ユイナ',
                'guardian_name' => 'サンプル 知子',
                'guardian_name_kana' => 'サンプル トモコ',
                'birth_date' => '2007-09-30',
                'coming_of_age_year' => 2027,
                'ceremony_area_id' => 5, // 倉敷市
                'phone_number' => '090-0000-0007',
                'postal_code' => '710-0001',
                'address' => '岡山県倉敷市サンプル町7-7-7',
                'email' => 'sample07@example.com',
                'school_name' => 'サンプル女子学院',
                'staff_name' => '担当者C',
                'remarks' => 'マニュアル用サンプル顧客（イベント予約サンプル）',
            ],
            // 8. 高橋 桜 — 予約・前撮り両方あり（福井店）
            [
                'shop_id' => 4,
                'name' => 'サンプル 桜',
                'kana' => 'サンプル サクラ',
                'guardian_name' => 'サンプル 直美',
                'guardian_name_kana' => 'サンプル ナオミ',
                'birth_date' => '2006-04-01',
                'coming_of_age_year' => 2026,
                'ceremony_area_id' => null,
                'phone_number' => '090-0000-0008',
                'postal_code' => '910-0002',
                'address' => '福井県福井市サンプル町8-8-8',
                'email' => 'sample08@example.com',
                'school_name' => 'サンプル福井高校',
                'staff_name' => '担当者A',
                'remarks' => 'マニュアル用サンプル顧客（予約＋前撮りフルセット）',
            ],
        ];

        $created = 0;
        $updated = 0;
        $byName = [];

        foreach ($samples as $data) {
            $existing = Customer::where('name', $data['name'])->first();

            $payload = array_merge($data, [
                'created_at' => $existing?->created_at ?? $now,
                'updated_at' => $now,
            ]);

            if ($existing) {
                $existing->update($payload);
                $updated++;
                $byName[$existing->name] = $existing->fresh();
                $this->command->line("更新: id={$existing->id} {$existing->name}");
            } else {
                $customer = Customer::create($payload);
                $created++;
                $byName[$customer->name] = $customer;
                $this->command->line("作成: id={$customer->id} {$customer->name}");
            }
        }

        $this->command->info("顧客: 作成 {$created} 件 / 更新 {$updated} 件");

        $this->seedRelatedData($byName);
    }

    /**
     * サンプル顧客に紐づく多様な関連データを投入する。
     * メモ／タグ／制約／LINE連携／前撮り枠／イベント予約 を網羅。
     */
    protected function seedRelatedData(array $byName): void
    {
        $now = now();

        // -----------------------------------
        // メモ（連絡履歴） — 葵に2件
        // -----------------------------------
        if ($aoi = $byName['サンプル 葵'] ?? null) {
            DB::table('customer_notes')->where('customer_id', $aoi->id)->where('content', 'like', '【サンプル】%')->delete();
            DB::table('customer_notes')->insert([
                [
                    'customer_id' => $aoi->id,
                    'user_id' => 1,
                    'content' => "【サンプル】 初回ご来店時に振袖の好みをヒアリング。\nオレンジ系・パステル系がお好み。",
                    'created_at' => $now->copy()->subDays(14),
                    'updated_at' => $now->copy()->subDays(14),
                ],
                [
                    'customer_id' => $aoi->id,
                    'user_id' => 1,
                    'content' => "【サンプル】 来店候補日：来週土曜午後。保護者様も同行予定。",
                    'created_at' => $now->copy()->subDays(3),
                    'updated_at' => $now->copy()->subDays(3),
                ],
            ]);
            $this->command->line("メモ: 葵に2件");
        }

        // -----------------------------------
        // タグ — 葵に「要フォロー」、莉子に「クレーム履歴あり」
        // -----------------------------------
        if ($aoi = $byName['サンプル 葵'] ?? null) {
            DB::table('customer_tag_assignments')->where('customer_id', $aoi->id)->delete();
            DB::table('customer_tag_assignments')->insert([
                'customer_id' => $aoi->id,
                'customer_tag_id' => 3, // 要フォロー
                'note' => 'サンプル: 来店フォロー対象',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
        if ($riko = $byName['サンプル 莉子'] ?? null) {
            DB::table('customer_tag_assignments')->where('customer_id', $riko->id)->delete();
            DB::table('customer_tag_assignments')->insert([
                'customer_id' => $riko->id,
                'customer_tag_id' => 1, // クレーム履歴あり
                'note' => 'サンプル: 前撮り日変更時のやりとりで意見あり',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
        $this->command->line("タグ: 2件付与");

        // -----------------------------------
        // 制約（同意書） — 花子=署名済み、結衣=未署名
        // -----------------------------------
        if ($hanako = $byName['サンプル 花子'] ?? null) {
            DB::table('customer_constraints')->where('customer_id', $hanako->id)->delete();
            DB::table('customer_constraints')->insert([
                'customer_id' => $hanako->id,
                'constraint_template_id' => 5, // 振袖持ち込み規約
                'signed_at' => $now->copy()->subDays(7),
                'signature_image' => null,
                'explainer_user_id' => 1,
                'check_values' => json_encode([], JSON_UNESCAPED_UNICODE),
                'created_at' => $now->copy()->subDays(8),
                'updated_at' => $now->copy()->subDays(7),
            ]);
        }
        if ($yui = $byName['サンプル 結衣'] ?? null) {
            DB::table('customer_constraints')->where('customer_id', $yui->id)->delete();
            DB::table('customer_constraints')->insert([
                'customer_id' => $yui->id,
                'constraint_template_id' => 5,
                'signed_at' => null, // 未署名
                'signature_image' => null,
                'explainer_user_id' => 1,
                'check_values' => json_encode([], JSON_UNESCAPED_UNICODE),
                'created_at' => $now->copy()->subDays(2),
                'updated_at' => $now->copy()->subDays(2),
            ]);
        }
        $this->command->line("制約: 花子(署名済み)・結衣(未署名)");

        // -----------------------------------
        // LINE連携 — 美咲
        // -----------------------------------
        if ($misaki = $byName['サンプル 美咲'] ?? null) {
            DB::table('customer_line_contacts')->where('customer_id', $misaki->id)->delete();
            DB::table('customer_line_contacts')->insert([
                'customer_id' => $misaki->id,
                'event_reservation_id' => null,
                'shop_id' => $misaki->shop_id,
                'line_user_id' => 'U00000000000000000000000000sample02',
                'label' => 'サンプル: お母様LINE',
                'created_at' => $now->copy()->subDays(20),
                'updated_at' => $now->copy()->subDays(20),
            ]);
            $this->command->line("LINE連携: 美咲に1件");
        }

        // -----------------------------------
        // 前撮り枠 — 花子(岡山ガーデン)・桜(いかしの舎)
        // -----------------------------------
        if ($hanako = $byName['サンプル 花子'] ?? null) {
            $existing = DB::table('photo_slots')
                ->where('shoot_date', '2026-12-12')
                ->where('shoot_time', '14:00:00')
                ->where('photo_studio_id', 1)
                ->first();
            if ($existing) {
                DB::table('photo_slots')->where('id', $existing->id)->update([
                    'customer_id' => $hanako->id,
                    'plan_id' => 2,
                    'user_id' => 1,
                    'assignment_label' => 'サンプル割当',
                    'remarks' => 'マニュアル用サンプル枠',
                    'updated_at' => $now,
                ]);
                $this->command->line("前撮り: 花子の既存枠に紐付け (slot_id={$existing->id})");
            } else {
                $slotId = DB::table('photo_slots')->insertGetId([
                    'photo_studio_id' => 1,
                    'shoot_date' => '2026-12-12',
                    'shoot_time' => '14:00:00',
                    'customer_id' => $hanako->id,
                    'details_undecided' => false,
                    'remarks' => 'マニュアル用サンプル枠',
                    'assignment_label' => 'サンプル割当',
                    'user_id' => 1,
                    'plan_id' => 2,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
                DB::table('photo_slot_shop')->insert([
                    'photo_slot_id' => $slotId,
                    'shop_id' => $hanako->shop_id,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
                $this->command->line("前撮り: 花子用に枠を新規作成 (slot_id={$slotId})");
            }
        }

        if ($sakura = $byName['サンプル 桜'] ?? null) {
            $existing = DB::table('photo_slots')
                ->where('shoot_date', '2026-11-23')
                ->where('shoot_time', '10:00:00')
                ->where('photo_studio_id', 2)
                ->first();
            if ($existing) {
                DB::table('photo_slots')->where('id', $existing->id)->update([
                    'customer_id' => $sakura->id,
                    'plan_id' => 2,
                    'user_id' => 1,
                    'assignment_label' => 'サンプル割当',
                    'remarks' => 'マニュアル用サンプル枠',
                    'updated_at' => $now,
                ]);
                $this->command->line("前撮り: 桜の既存枠に紐付け (slot_id={$existing->id})");
            } else {
                $slotId = DB::table('photo_slots')->insertGetId([
                    'photo_studio_id' => 2,
                    'shoot_date' => '2026-11-23',
                    'shoot_time' => '10:00:00',
                    'customer_id' => $sakura->id,
                    'details_undecided' => false,
                    'remarks' => 'マニュアル用サンプル枠',
                    'assignment_label' => 'サンプル割当',
                    'user_id' => 1,
                    'plan_id' => 2,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
                DB::table('photo_slot_shop')->insert([
                    'photo_slot_id' => $slotId,
                    'shop_id' => $sakura->shop_id,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
                $this->command->line("前撮り: 桜用に枠を新規作成 (slot_id={$slotId})");
            }
        }

        // -----------------------------------
        // イベント予約 — 結奈・桜（イベントは既存最新の振袖系を1つ拾う）
        // -----------------------------------
        $event = DB::table('events')
            ->where('is_public', true)
            ->orderByDesc('id')
            ->first()
            ?? DB::table('events')->orderByDesc('id')->first();

        if ($event) {
            foreach (['サンプル 結奈', 'サンプル 桜'] as $name) {
                $c = $byName[$name] ?? null;
                if (!$c) continue;
                DB::table('event_reservations')->where('customer_id', $c->id)->where('name', 'like', 'サンプル%')->delete();
                DB::table('event_reservations')->insert([
                    'event_id' => $event->id,
                    'name' => $c->name,
                    'furigana' => $c->kana,
                    'email' => $c->email,
                    'phone' => $c->phone_number,
                    'postal_code' => $c->postal_code,
                    'address' => $c->address,
                    'birth_date' => $c->birth_date,
                    'seijin_year' => $c->coming_of_age_year,
                    'school_name' => $c->school_name,
                    'staff_name' => $c->staff_name,
                    'reservation_datetime' => $now->copy()->addDays(7)->setTime(13, 0),
                    'status' => 'pending',
                    'request_method' => 'web',
                    'privacy_agreed' => true,
                    'cancel_flg' => false,
                    'visitor_count' => 2,
                    'customer_id' => $c->id,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
            $this->command->line("イベント予約: 結奈・桜 を event_id={$event->id} に登録");
        } else {
            $this->command->warn('イベントが見つからないため予約スキップ');
        }

        $this->command->info('関連データ投入 完了');
    }
}
