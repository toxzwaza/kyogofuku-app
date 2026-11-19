<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 前撮り枠に定員を持たせるため、席番号を足す。
 * 1行＝枠の席1つ＝お客様1人まで、は変えない。定員3の枠は同じ時刻の行が3行（席1〜3）になる。
 * 既存の行はすべて席1になる。
 */
return new class extends Migration
{
    public function up()
    {
        Schema::table('photo_slots', function (Blueprint $table) {
            $table->unsignedTinyInteger('seat_no')->default(1)->after('shoot_time')
                ->comment('席番号（同じスタジオ・日・時刻の中での番号。1〜定員）');
        });

        // 新しい一意制約を先に作ってから古い制約を外す（photo_studio_id の外部キーが使う索引を切らさないため）
        Schema::table('photo_slots', function (Blueprint $table) {
            $table->unique(['photo_studio_id', 'shoot_date', 'shoot_time', 'seat_no'], 'uq_photo_slot_seat');
        });
        Schema::table('photo_slots', function (Blueprint $table) {
            $table->dropUnique('uq_photo_slot');
        });
    }

    /**
     * 戻すと同じ時刻に2席以上ある枠は古い一意制約にかかるため、
     * 戻す前に席2以降の行を整理しておくこと。
     */
    public function down()
    {
        Schema::table('photo_slots', function (Blueprint $table) {
            $table->unique(['photo_studio_id', 'shoot_date', 'shoot_time'], 'uq_photo_slot');
        });
        Schema::table('photo_slots', function (Blueprint $table) {
            $table->dropUnique('uq_photo_slot_seat');
            $table->dropColumn('seat_no');
        });
    }
};
