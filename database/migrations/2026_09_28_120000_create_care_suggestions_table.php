<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * ケア提案（オーバービューの「今日のケアリスト」用）。
     * バッチで生成し、画面はこのテーブルを読むだけにする。
     */
    public function up(): void
    {
        Schema::create('care_suggestions', function (Blueprint $table) {
            $table->id();
            // ケア種別：acquisition（獲得＝成約前リード）/ retention（維持＝成約後キャンセル防止）
            $table->string('care_type', 16)->default('acquisition');
            // 対象：予約リード or 顧客（成約後フォロー）
            $table->string('subject_type', 16); // 'reservation' | 'customer'
            $table->foreignId('event_reservation_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();

            $table->unsignedBigInteger('shop_id')->nullable();
            $table->string('assignee')->nullable();       // 担当（表示・フィルタ用）
            $table->string('subject_name')->nullable();   // 顧客/予約者名（表示用スナップショット）

            $table->unsignedSmallInteger('care_score')->default(0); // 0-100
            $table->string('priority_band', 8)->default('低');       // 緊急/高/中/低
            $table->json('score_factors')->nullable();               // スコア内訳（透明性のため）

            // 表示用の構造化フィールド（オーバービューでラベル分割表示）
            $table->string('reservation_status', 32)->nullable(); // ステータス（未対応/確認中/返信待ち等）
            $table->integer('days_since_contact')->nullable();     // 経過日数（最終接触から）
            $table->string('prospect_label', 32)->nullable();      // 見込み（濃いリード/広告経由等）
            $table->unsignedSmallInteger('seijin_year')->nullable(); // 成人式年度

            $table->string('status_summary', 255)->nullable(); // 状態サマリー（一覧の補足・フォールバック）
            $table->string('next_action', 500)->nullable();    // 推奨対応（簡潔な提案文）
            $table->string('next_action_type', 64)->nullable();// reply/visit/photo/balance/followup 等

            $table->timestamp('last_contact_at')->nullable();  // 最終接触日時
            $table->boolean('ai_generated')->default(false);   // 第2段でAI提案に切替
            $table->timestamp('generated_at')->nullable();     // このバッチ生成時刻

            $table->timestamps();

            $table->index(['care_type', 'priority_band', 'care_score']);
            $table->index(['shop_id', 'care_score']);
            $table->index('assignee');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('care_suggestions');
    }
};
