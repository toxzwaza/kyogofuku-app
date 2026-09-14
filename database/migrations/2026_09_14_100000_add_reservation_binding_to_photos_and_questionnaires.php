<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * 顧客写真・振袖アンケートを「顧客 or イベント予約」のどちらかに紐づけられるようにする
 * （customer_line_contacts と同じパターン。予約が顧客に紐付いたら顧客側へ移行する）。
 *
 * あわせて振袖アンケート1ページ目の写真配置・合成に対応するカラムを追加する。
 *
 * ※ customer_id の nullable 化は ->change() が doctrine/dbal のバージョン非互換で
 *    使えないため生SQL（MODIFY）で行う。
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE customer_photos MODIFY customer_id BIGINT UNSIGNED NULL');
        DB::statement('ALTER TABLE customer_questionnaires MODIFY customer_id BIGINT UNSIGNED NULL');

        Schema::table('customer_photos', function (Blueprint $table) {
            $table->foreignId('event_reservation_id')
                ->nullable()
                ->after('customer_id')
                ->constrained('event_reservations')
                ->cascadeOnDelete();
        });

        Schema::table('customer_questionnaires', function (Blueprint $table) {
            $table->foreignId('event_reservation_id')
                ->nullable()
                ->after('customer_id')
                ->unique()
                ->constrained('event_reservations')
                ->cascadeOnDelete();
            // 1ページ目の写真配置・合成（2ページ目は既存の placements / composed_page2_path）
            $table->json('page1_placements')->nullable()->after('placements');
            $table->string('composed_page1_path')->nullable()->after('composed_page2_path');
        });
    }

    public function down(): void
    {
        Schema::table('customer_questionnaires', function (Blueprint $table) {
            $table->dropConstrainedForeignId('event_reservation_id');
            $table->dropColumn(['page1_placements', 'composed_page1_path']);
        });

        Schema::table('customer_photos', function (Blueprint $table) {
            $table->dropConstrainedForeignId('event_reservation_id');
        });

        DB::statement('ALTER TABLE customer_photos MODIFY customer_id BIGINT UNSIGNED NOT NULL');
        DB::statement('ALTER TABLE customer_questionnaires MODIFY customer_id BIGINT UNSIGNED NOT NULL');
    }
};
