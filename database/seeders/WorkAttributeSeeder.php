<?php

namespace Database\Seeders;

use App\Models\WorkAttribute;
use Illuminate\Database\Seeder;

class WorkAttributeSeeder extends Seeder
{
    public function run(): void
    {
        $names = ['社員', 'パート1', 'パート2', 'パート3'];
        foreach ($names as $i => $name) {
            WorkAttribute::query()->firstOrCreate(
                ['name' => $name],
                ['sort_order' => $i + 1]
            );
        }
    }
}
