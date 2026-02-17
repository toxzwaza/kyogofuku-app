<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('events', function (Blueprint $table) {
            $table->string('background_image_path', 500)->nullable()->after('background_image_enabled');
            $table->string('background_image_storage_disk', 20)->nullable()->after('background_image_path');
        });
    }

    public function down()
    {
        Schema::table('events', function (Blueprint $table) {
            $table->dropColumn(['background_image_path', 'background_image_storage_disk']);
        });
    }
};
