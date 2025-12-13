<?php

namespace Database\Seeders;

use App\Models\CeremonyArea;
use Illuminate\Database\Seeder;

class CeremonyAreaSeeder extends Seeder
{
    public function run(): void
    {
        $areas = [
            ['name' => '赤磐市', 'furi' => 'あかいわし', 'prefecture' => '岡山県'],
            ['name' => '岡山市', 'furi' => 'おかやまし', 'prefecture' => '岡山県'],
            ['name' => '備前市', 'furi' => 'びぜんし', 'prefecture' => '岡山県'],
            ['name' => '井原市', 'furi' => 'いばらし', 'prefecture' => '岡山県'],
            ['name' => '倉敷市', 'furi' => 'くらしきし', 'prefecture' => '岡山県'],
            ['name' => '新見市', 'furi' => 'にいみし', 'prefecture' => '岡山県'],
            ['name' => '津山市', 'furi' => 'つやまし', 'prefecture' => '岡山県'],
            ['name' => '浅口市', 'furi' => 'あさくちし', 'prefecture' => '岡山県'],
            ['name' => '瀬戸内市', 'furi' => 'せとうちし', 'prefecture' => '岡山県'],
            ['name' => '玉野市', 'furi' => 'たまのし', 'prefecture' => '岡山県'],
            ['name' => '真庭市', 'furi' => 'まにわし', 'prefecture' => '岡山県'],
            ['name' => '笠岡市', 'furi' => 'かさおかし', 'prefecture' => '岡山県'],
            ['name' => '総社市', 'furi' => 'そうじゃし', 'prefecture' => '岡山県'],
            ['name' => '美作市', 'furi' => 'みまさかし', 'prefecture' => '岡山県'],
            ['name' => '高梁市', 'furi' => 'たかはしし', 'prefecture' => '岡山県'],
            ['name' => '岡山県外', 'furi' => 'おかやまけんがい', 'prefecture' => '岡山県'],
            ['name' => 'その他', 'furi' => 'ん', 'prefecture' => '岡山県'],
        ];

        foreach ($areas as $area) {
            CeremonyArea::firstOrCreate(
                ['name' => $area['name']],
                [
                    'furi' => $area['furi'],
                    'prefecture' => $area['prefecture']
                ]
            );
        }
    }
}
