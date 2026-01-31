<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 詳細未決定フラグを追加し、会場・日時をnullableにする
     *
     * @return void
     */
    public function up()
    {
        // 外部キー・ユニーク制約を削除（存在する場合のみ）
        $foreignKeys = DB::select("SELECT CONSTRAINT_NAME FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'photo_slots' AND CONSTRAINT_NAME = 'photo_slots_photo_studio_id_foreign'");
        if (!empty($foreignKeys)) {
            Schema::table('photo_slots', function (Blueprint $table) {
                $table->dropForeign(['photo_studio_id']);
            });
        }
        $indexes = DB::select("SELECT INDEX_NAME FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'photo_slots' AND INDEX_NAME = 'uq_photo_slot'");
        if (!empty($indexes)) {
            Schema::table('photo_slots', function (Blueprint $table) {
                $table->dropUnique('uq_photo_slot');
            });
        }

        if (!Schema::hasColumn('photo_slots', 'details_undecided')) {
            Schema::table('photo_slots', function (Blueprint $table) {
                    $table->boolean('details_undecided')->default(false)->after('customer_id')
                    ->comment('詳細未決定フラグ');
            });
        }

        DB::statement('ALTER TABLE photo_slots MODIFY photo_studio_id BIGINT UNSIGNED NULL');
        DB::statement('ALTER TABLE photo_slots MODIFY shoot_date DATE NULL');
        DB::statement('ALTER TABLE photo_slots MODIFY shoot_time TIME NULL');

        $hasFk = DB::select("SELECT CONSTRAINT_NAME FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'photo_slots' AND CONSTRAINT_NAME = 'photo_slots_photo_studio_id_foreign'");
        if (empty($hasFk)) {
            Schema::table('photo_slots', function (Blueprint $table) {
                $table->foreign('photo_studio_id')->references('id')->on('photo_studios')->nullOnDelete();
            });
        }

        $hasUnique = DB::select("SELECT INDEX_NAME FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'photo_slots' AND INDEX_NAME = 'uq_photo_slot'");
        if (empty($hasUnique)) {
            Schema::table('photo_slots', function (Blueprint $table) {
                $table->unique(
                    ['photo_studio_id', 'shoot_date', 'shoot_time'],
                    'uq_photo_slot'
                );
            });
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('photo_slots', function (Blueprint $table) {
            $table->dropUnique('uq_photo_slot');
            $table->dropForeign(['photo_studio_id']);
        });

        Schema::table('photo_slots', function (Blueprint $table) {
            $table->dropColumn('details_undecided');
        });

        DB::statement('ALTER TABLE photo_slots MODIFY photo_studio_id BIGINT UNSIGNED NOT NULL');
        DB::statement('ALTER TABLE photo_slots MODIFY shoot_date DATE NOT NULL');
        DB::statement('ALTER TABLE photo_slots MODIFY shoot_time TIME NOT NULL');

        Schema::table('photo_slots', function (Blueprint $table) {
            $table->foreign('photo_studio_id')->references('id')->on('photo_studios');
        });

        Schema::table('photo_slots', function (Blueprint $table) {
            $table->unique(
                ['photo_studio_id', 'shoot_date', 'shoot_time'],
                'uq_photo_slot'
            );
        });
    }
};
