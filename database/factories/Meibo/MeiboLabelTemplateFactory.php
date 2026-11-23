<?php

namespace Database\Factories\Meibo;

use App\Models\Meibo\MeiboLabelTemplate;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<MeiboLabelTemplate> */
class MeiboLabelTemplateFactory extends Factory
{
    protected $model = MeiboLabelTemplate::class;

    private static int $seq = 0;

    public function definition()
    {
        return [
            'name' => 'テンプレート' . (++self::$seq),
            'sender_zip' => '918-8015',
            'sender_address' => '福井県福井市花堂南1-2-1',
            'sender_company' => '(株)京呉服　平田',
            'addressee' => 'self',
            'with_family' => true,
            'tantou' => '',
        ];
    }
}
