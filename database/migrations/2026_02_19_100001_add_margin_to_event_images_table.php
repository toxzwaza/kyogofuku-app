<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('event_images', function (Blueprint $table) {
            $table->unsignedInteger('margin_top_px')->nullable()->after('sort_order');
            $table->unsignedInteger('margin_bottom_px')->nullable()->after('margin_top_px');
        });
    }

    public function down()
    {
        Schema::table('event_images', function (Blueprint $table) {
            $table->dropColumn(['margin_top_px', 'margin_bottom_px']);
        });
    }
};
