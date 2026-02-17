<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('events', function (Blueprint $table) {
            $table->string('background_color', 50)->nullable()->after('cta_storage_disk');
            $table->string('cta_color_type', 20)->nullable()->after('background_color');
        });
    }

    public function down()
    {
        Schema::table('events', function (Blueprint $table) {
            $table->dropColumn(['background_color', 'cta_color_type']);
        });
    }
};
