<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * LINE広告（キャンペーン一斉配信）
 *
 * line_broadcasts            : 広告本体（タイトル・本文・バナー画像・状態）
 * line_broadcast_recipients  : 配信先ごとの送信結果ログ。
 *                              unique(line_broadcast_id, line_user_id) により
 *                              同一広告の同一LINEユーザーへの重複送信をDB層でも防止する。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('line_broadcasts', function (Blueprint $table) {
            $table->id();
            $table->string('title')->comment('管理用タイトル（顧客には表示されない）');
            $table->text('text')->nullable()->comment('メッセージ本文');
            $table->foreignId('media_file_id')->nullable()->constrained('media_files')->nullOnDelete()->comment('バナー画像');
            $table->string('status', 20)->default('draft')->comment('draft / sending / sent');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('last_sent_at')->nullable();
            $table->timestamps();
        });

        Schema::create('line_broadcast_recipients', function (Blueprint $table) {
            $table->id();
            $table->foreignId('line_broadcast_id')->constrained('line_broadcasts')->cascadeOnDelete();
            $table->foreignId('customer_line_contact_id')->nullable()->constrained('customer_line_contacts')->nullOnDelete();
            $table->string('line_user_id')->comment('送信時点のLINEユーザーID（重複防止キー）');
            $table->string('recipient_name')->nullable()->comment('送信時点の宛先名スナップショット');
            $table->string('recipient_kind', 20)->nullable()->comment('customer / reservation / unbound');
            $table->string('status', 20)->default('pending')->comment('pending / sent / failed');
            $table->text('error_message')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();

            $table->unique(['line_broadcast_id', 'line_user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('line_broadcast_recipients');
        Schema::dropIfExists('line_broadcasts');
    }
};
