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
        // slideshowsテーブルに設定カラムを追加
        Schema::table('slideshows', function (Blueprint $table) {
            $table->string('type')->default('fade')->after('name'); // 'fade', 'slide', 'cube', 'coverflow'など
            $table->integer('autoplay_interval')->default(5000)->after('type'); // ミリ秒
            $table->boolean('autoplay_enabled')->default(true)->after('autoplay_interval');
            $table->boolean('fullscreen')->default(true)->after('autoplay_enabled');
        });

        // event_slideshow_positionsテーブルにsort_orderを追加
        Schema::table('event_slideshow_positions', function (Blueprint $table) {
            $table->integer('sort_order')->default(0)->after('position');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('slideshows', function (Blueprint $table) {
            $table->dropColumn(['type', 'autoplay_interval', 'autoplay_enabled', 'fullscreen']);
        });

        Schema::table('event_slideshow_positions', function (Blueprint $table) {
            $table->dropColumn('sort_order');
        });
    }
};
