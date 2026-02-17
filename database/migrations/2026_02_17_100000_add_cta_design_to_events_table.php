<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('events', function (Blueprint $table) {
            $table->string('cta_background_path')->nullable()->after('success_text');
            $table->string('cta_web_button_path')->nullable()->after('cta_background_path');
            $table->string('cta_phone_button_path')->nullable()->after('cta_web_button_path');
            $table->string('cta_storage_disk')->nullable()->after('cta_phone_button_path');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('events', function (Blueprint $table) {
            $table->dropColumn([
                'cta_background_path',
                'cta_web_button_path',
                'cta_phone_button_path',
                'cta_storage_disk',
            ]);
        });
    }
};
