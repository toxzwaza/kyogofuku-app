<?php

namespace Database\Factories\Meibo;

use App\Models\Meibo\MeiboPerson;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * 名簿の人（架空）。名前・住所・電話はすべて作りもので、実在の人とは関係ない。
 * 電話は使われていない局番（-00-）・携帯は 090-0000- にしている。
 *
 * @extends Factory<MeiboPerson>
 */
class MeiboPersonFactory extends Factory
{
    protected $model = MeiboPerson::class;

    public const SURNAMES = [
        ['山田', 'ヤマダ'], ['佐藤', 'サトウ'], ['鈴木', 'スズキ'], ['高橋', 'タカハシ'], ['田中', 'タナカ'],
        ['伊藤', 'イトウ'], ['渡辺', 'ワタナベ'], ['中村', 'ナカムラ'], ['小林', 'コバヤシ'], ['加藤', 'カトウ'],
        ['吉田', 'ヨシダ'], ['山口', 'ヤマグチ'], ['松本', 'マツモト'], ['井上', 'イノウエ'], ['木村', 'キムラ'],
    ];

    public const GIVEN = [
        ['花子', 'ハナコ'], ['美咲', 'ミサキ'], ['結衣', 'ユイ'], ['陽菜', 'ヒナ'], ['葵', 'アオイ'],
        ['さくら', 'サクラ'], ['凛', 'リン'], ['芽依', 'メイ'], ['心春', 'コハル'], ['莉子', 'リコ'],
    ];

    public const GUARDIAN_GIVEN = ['一郎', '健二', '浩', '誠', '洋子', '恵子'];

    /** 地域（市区と郵便番号の頭3桁・座標の目安）。番地は架空 */
    public const AREAS = [
        ['pref' => '福井県', 'city' => '福井市', 'zip' => '910', 'lat' => 36.0641, 'lng' => 136.2196, 'tel' => '0776'],
        ['pref' => '福井県', 'city' => '坂井市', 'zip' => '919', 'lat' => 36.1669, 'lng' => 136.2316, 'tel' => '0776'],
        ['pref' => '福井県', 'city' => '鯖江市', 'zip' => '916', 'lat' => 35.9566, 'lng' => 136.1843, 'tel' => '0778'],
        ['pref' => '福井県', 'city' => '越前市', 'zip' => '915', 'lat' => 35.9035, 'lng' => 136.1688, 'tel' => '0778'],
        ['pref' => '岡山県', 'city' => '岡山市北区', 'zip' => '700', 'lat' => 34.6551, 'lng' => 133.9195, 'tel' => '086'],
        ['pref' => '岡山県', 'city' => '倉敷市', 'zip' => '710', 'lat' => 34.585, 'lng' => 133.772, 'tel' => '086'],
        ['pref' => '石川県', 'city' => '金沢市', 'zip' => '920', 'lat' => 36.5613, 'lng' => 136.6562, 'tel' => '076'],
    ];

    public const TOWNS = ['中央', '本町', '花園', '若葉町', '旭町', '緑ケ丘', '桜木町', '栄町'];

    public const SOURCES = ['G_R07', 'G_R08', 'H_2023', 'H_2024', 'N_2025'];

    private static int $seq = 0;

    public function definition()
    {
        $area = $this->faker->randomElement(self::AREAS);
        $town = $this->faker->numberBetween(0, count(self::TOWNS) - 1);
        [$sur, $surKana] = $this->faker->randomElement(self::SURNAMES);
        [$given, $givenKana] = $this->faker->randomElement(self::GIVEN);
        $hasTel = $this->faker->boolean(55);

        return [
            'master_code' => sprintf('M9%05d', ++self::$seq),
            'pref' => $area['pref'],
            'seijin_year' => $this->faker->numberBetween(2026, 2030),
            'name' => "{$sur}　{$given}",
            'kana' => "{$surKana}　{$givenKana}",
            'zip' => sprintf('%s-%04d', $area['zip'], $town * 11 + 1),
            'address' => sprintf('%s%s%d丁目%d-%d', $area['city'], self::TOWNS[$town], $this->faker->numberBetween(1, 5), $this->faker->numberBetween(1, 30), $this->faker->numberBetween(1, 20)),
            'tel' => $hasTel ? sprintf('%s-00-%04d', $area['tel'], $this->faker->numberBetween(0, 9999)) : null,
            'mobile' => $hasTel ? null : sprintf('090-0000-%04d', $this->faker->numberBetween(0, 9999)),
            'guardian_name' => $this->faker->boolean(80) ? "{$sur}　" . $this->faker->randomElement(self::GUARDIAN_GIVEN) : null,
            'source_versions' => $this->faker->randomElement(self::SOURCES),
            'merge_count' => 1,
            'dm_status' => '送付可',
            'dm_note' => '',
        ];
    }

    /** 出所に R11 成人名簿（G_R11）を足す */
    public function r11(): static
    {
        return $this->state(fn (array $a) => [
            'pref' => '福井県',
            'seijin_year' => 2029,
            'source_versions' => trim(($a['source_versions'] ?? '') . '/' . MeiboPerson::R11_SOURCE, '/'),
            'merge_count' => ($a['merge_count'] ?? 1) + 1,
        ]);
    }

    public function status(string $status, string $note = ''): static
    {
        return $this->state(fn () => ['dm_status' => $status, 'dm_note' => $note]);
    }
}
