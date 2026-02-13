<?php

namespace Database\Seeders;

use App\Models\CeremonyArea;
use Illuminate\Database\Seeder;

class CeremonyAreaFukuiSeeder extends Seeder
{
    /**
     * 成人式エリアに福井県の市を追加する
     */
    public function run(): void
    {
        $areas = [
            ['name' => '福井市', 'furi' => 'ふくいし', 'prefecture' => '福井県'],
            ['name' => '敦賀市', 'furi' => 'つるがし', 'prefecture' => '福井県'],
            ['name' => '小浜市', 'furi' => 'おばまし', 'prefecture' => '福井県'],
            ['name' => '大野市', 'furi' => 'おおのし', 'prefecture' => '福井県'],
            ['name' => '勝山市', 'furi' => 'かつやまし', 'prefecture' => '福井県'],
            ['name' => '鯖江市', 'furi' => 'さばえし', 'prefecture' => '福井県'],
            ['name' => 'あわら市', 'furi' => 'あわらし', 'prefecture' => '福井県'],
            ['name' => '越前市', 'furi' => 'えちぜんし', 'prefecture' => '福井県'],
            ['name' => '坂井市', 'furi' => 'さかいし', 'prefecture' => '福井県'],
        ];

        foreach ($areas as $area) {
            CeremonyArea::firstOrCreate(
                ['name' => $area['name']],
                [
                    'furi' => $area['furi'],
                    'prefecture' => $area['prefecture'],
                ]
            );
        }
    }
}
