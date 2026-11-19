<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 前撮りスタジオのマスタ項目（店舗グループ・有効／無効・既定の営業時間・既定の定員）を足す。
 * 既存のスタジオは営業時間が空のまま（各店舗に登録してもらう）。
 */
return new class extends Migration
{
    public function up()
    {
        Schema::table('photo_studios', function (Blueprint $table) {
            $table->string('shop_group', 20)->nullable()->after('remarks')
                ->comment('店舗グループ（shops.group_key と同じ値。okayama／fukui）');
            $table->boolean('is_active')->default(true)->after('shop_group')
                ->comment('有効フラグ（無効にすると選択肢・枠作成に出さない）');
            $table->time('default_start_time')->nullable()->after('is_active')
                ->comment('既定の開始時刻（最初の枠）');
            $table->time('default_end_time')->nullable()->after('default_start_time')
                ->comment('既定の終了時刻（最後の枠の終わり）');
            $table->unsignedTinyInteger('default_capacity')->default(1)->after('default_end_time')
                ->comment('枠を作るときの既定の定員（1〜10）');
        });
    }

    public function down()
    {
        Schema::table('photo_studios', function (Blueprint $table) {
            $table->dropColumn(['shop_group', 'is_active', 'default_start_time', 'default_end_time', 'default_capacity']);
        });
    }
};
