<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * LINE友達紹介経由の予約を紹介レコードに紐付ける。
     * referred_by_name（お客様の自由入力）とは別に、実際のLINE紹介者を管理画面で確認するために使う。
     */
    public function up()
    {
        Schema::table('event_reservations', function (Blueprint $table) {
            $table->unsignedBigInteger('line_referral_id')->nullable()->after('referred_by_name');
            $table->foreign('line_referral_id')->references('id')->on('referrals')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('event_reservations', function (Blueprint $table) {
            $table->dropForeign(['line_referral_id']);
            $table->dropColumn('line_referral_id');
        });
    }
};
