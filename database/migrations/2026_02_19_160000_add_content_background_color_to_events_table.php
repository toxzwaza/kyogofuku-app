<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('events', function (Blueprint $table) {
            $table->string('content_background_color', 50)->nullable()->after('background_color');
        });
    }

    public function down()
    {
        Schema::table('events', function (Blueprint $table) {
            $table->dropColumn('content_background_color');
        });
    }
};
