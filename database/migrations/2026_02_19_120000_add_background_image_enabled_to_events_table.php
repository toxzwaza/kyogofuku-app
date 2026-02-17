<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('events', function (Blueprint $table) {
            $table->boolean('background_image_enabled')->default(false)->after('background_color');
        });
    }

    public function down()
    {
        Schema::table('events', function (Blueprint $table) {
            $table->dropColumn('background_image_enabled');
        });
    }
};
