<?php

namespace Database\Seeders;

use App\Models\Meibo\MeiboDmLog;
use App\Models\Meibo\MeiboLabelTemplate;
use App\Models\Meibo\MeiboPerson;
use App\Models\Meibo\MeiboZipGeo;
use Database\Factories\Meibo\MeiboPersonFactory;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * 名簿の架空データ（画面の確認・開発用）。実データは入れない。
 *   php artisan db:seed --class=MeiboDummySeeder
 * 本番では動かない。名簿の表（meibo_*）を空にしてから入れ直す。
 */
class MeiboDummySeeder extends Seeder
{
    public const COUNT = 1200;

    public function run()
    {
        if (app()->environment('production')) {
            $this->command?->error('本番では名簿のダミーを入れません。');

            return;
        }

        // 決まった種で作る（入れ直しても同じ並び）
        fake()->seed(20261123);

        DB::transaction(function () {
            MeiboDmLog::query()->delete();
            MeiboPerson::query()->delete();
            MeiboZipGeo::query()->delete();
            MeiboLabelTemplate::query()->delete();

            $now = now();
            $rows = [];
            for ($i = 1; $i <= self::COUNT; $i++) {
                $f = MeiboPerson::factory();
                $a = $f->raw();
                $a['master_code'] = sprintf('M9%05d', $i);
                // 福井の令和11年（2029）成人は R11 成人名簿にも載っている人が多い
                if ($a['pref'] === '福井県' && $a['seijin_year'] === 2029 && fake()->boolean(70)) {
                    $a['source_versions'] .= '/' . MeiboPerson::R11_SOURCE;
                    $a['merge_count'] = 2;
                }
                $r = fake()->randomFloat(3, 0, 1);
                if ($r >= 0.86) {
                    $a['dm_status'] = $r < 0.94 ? '未達' : '停止';
                    $a['dm_note'] = $a['dm_status'] === '未達'
                        ? fake()->randomElement(['宛先不明で返送', '転居先不明で返送'])
                        : fake()->randomElement(['ご家族から送付停止の依頼', '電話で停止の依頼']);
                }
                $rows[] = $a + ['created_at' => $now, 'updated_at' => $now];
            }
            foreach (array_chunk($rows, 500) as $chunk) {
                MeiboPerson::insert($chunk);
            }

            // 変更履歴（未達・停止の人に 1 件。取り込んだ昔の履歴の形で、誰が＝空）
            $logs = MeiboPerson::where('dm_status', '!=', '送付可')->get(['id', 'dm_status', 'dm_note'])
                ->map(fn ($p) => [
                    'person_id' => $p->id,
                    'old_status' => '送付可',
                    'new_status' => $p->dm_status,
                    'old_note' => '',
                    'note' => $p->dm_note,
                    'changed_by_user_id' => null,
                    'changed_at' => '2026-02-10 10:15:00',
                ])->all();
            foreach (array_chunk($logs, 500) as $chunk) {
                MeiboDmLog::insert($chunk);
            }

            // 郵便番号の座標（地図用）。町ごとに少しずらす。1 割ほどは座標なし（地図に出ない人の確認用）
            $geo = [];
            foreach (MeiboPersonFactory::AREAS as $area) {
                foreach (MeiboPersonFactory::TOWNS as $t => $town) {
                    if ($t === 7) {
                        continue;
                    }
                    $zip = sprintf('%s-%04d', $area['zip'], $t * 11 + 1);
                    $geo[$zip] = [
                        'zip' => $zip,
                        'pref' => $area['pref'],
                        'lat' => $area['lat'] + (($t % 4) - 1.5) * 0.012,
                        'lng' => $area['lng'] + (intdiv($t, 4) - 0.5) * 0.016,
                        'src' => 'dummy',
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }
            }
            MeiboZipGeo::insert(array_values($geo));

            MeiboLabelTemplate::create(['name' => '福井発送', 'sender_zip' => '918-8015', 'sender_address' => '福井県福井市花堂南1-2-1', 'sender_company' => '(株)京呉服　平田']);
            MeiboLabelTemplate::create(['name' => '岡山発送', 'sender_zip' => '', 'sender_address' => '', 'sender_company' => '(株)京呉服　平田']);
        });

        $this->command?->info('名簿の架空データを ' . self::COUNT . ' 件入れました。');
    }
}
