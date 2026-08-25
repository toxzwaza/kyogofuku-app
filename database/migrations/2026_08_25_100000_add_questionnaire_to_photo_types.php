<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (! DB::table('photo_types')->where('code', 'questionnaire')->exists()) {
            DB::table('photo_types')->insert([
                'name' => 'アンケート用紙',
                'code' => 'questionnaire',
                'description' => '振袖アンケート用紙のスキャン画像',
                'sort_order' => 5,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        DB::table('photo_types')->where('code', 'questionnaire')->delete();
    }
};
