<?php

namespace Database\Factories\Meibo;

use App\Models\Meibo\MeiboDmLog;
use App\Models\Meibo\MeiboPerson;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<MeiboDmLog> */
class MeiboDmLogFactory extends Factory
{
    protected $model = MeiboDmLog::class;

    public function definition()
    {
        return [
            'person_id' => MeiboPerson::factory(),
            'old_status' => '送付可',
            'new_status' => '未達',
            'old_note' => '',
            'note' => '宛先不明で返送',
            'changed_by_user_id' => null,
            'changed_at' => now(),
        ];
    }
}
