<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 共有ノートにステータス（未対応→議題→対応済み）と、記録の種類（対応記録・決定事項・状態の変更）を足す。
 *
 * - 既存の記録はすべて「未対応」から始める（2026-11-19 村上さん決定）
 * - 既存の経過（incident_comments）は「対応記録」として扱う（type の既定値）
 * - 記録の直しは本人が当日だけ。直す前の本文は incident_comment_revisions に残す
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('incidents', function (Blueprint $table) {
            $table->string('status', 20)->default('open')->after('consult');   // open=未対応／agenda=議題／resolved=対応済み
            $table->foreignId('status_changed_by')->nullable()->after('status')->constrained('users');
            $table->timestamp('status_changed_at')->nullable()->after('status_changed_by');

            $table->index(['group_key', 'status']);
        });

        Schema::table('incident_comments', function (Blueprint $table) {
            $table->string('type', 20)->default('record')->after('user_id');   // record=対応記録／decision=決定事項／status=状態の変更
            $table->string('from_status', 20)->nullable()->after('type');
            $table->string('to_status', 20)->nullable()->after('from_status');
            $table->timestamp('edited_at')->nullable()->after('body');
        });

        Schema::create('incident_comment_revisions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('incident_comment_id')->constrained('incident_comments')->cascadeOnDelete();
            $table->text('body');                                   // 直す前の本文
            $table->foreignId('edited_by')->constrained('users');
            $table->timestamp('created_at')->nullable();            // 直した日時
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('incident_comment_revisions');

        Schema::table('incident_comments', function (Blueprint $table) {
            $table->dropColumn(['type', 'from_status', 'to_status', 'edited_at']);
        });

        Schema::table('incidents', function (Blueprint $table) {
            $table->dropIndex(['group_key', 'status']);
            $table->dropConstrainedForeignId('status_changed_by');
            $table->dropColumn(['status', 'status_changed_at']);
        });
    }
};
