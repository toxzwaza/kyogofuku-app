<?php

namespace Database\Factories\Meibo;

use App\Models\Meibo\MeiboZipGeo;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<MeiboZipGeo> */
class MeiboZipGeoFactory extends Factory
{
    protected $model = MeiboZipGeo::class;

    public function definition()
    {
        return [
            'zip' => sprintf('910-%04d', $this->faker->unique()->numberBetween(0, 9999)),
            'pref' => '福井県',
            'lat' => 36.06,
            'lng' => 136.22,
            'src' => 'dummy',
        ];
    }
}
