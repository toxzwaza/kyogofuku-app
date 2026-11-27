<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * GTMタグのマスタに管理項目を足す（どの会社の管理か・担当・用途・確度）。
 * 既存の行は kind = event（イベントで選べるタグ）になる。LP出力（events.gtm_id）は変えない。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('gtm_tags', function (Blueprint $table) {
            $table->string('kind', 16)->default('event')->after('gtm_id')->comment('event=イベント用 / company=社内共通 / site=HP用（参照のみ）');
            $table->string('contact_person')->nullable()->after('company_name')->comment('担当者');
            $table->string('contact')->nullable()->after('contact_person')->comment('連絡先');
            $table->text('purpose')->nullable()->after('kind')->comment('用途');
            $table->text('used_on')->nullable()->after('purpose')->comment('イベント以外の使用先（HPのURLなど）');
            $table->string('confidence', 8)->nullable()->after('used_on')->comment('確度：確認済・高・中・低');
            $table->date('confirmed_at')->nullable()->after('confidence')->comment('担当者に確認した日');
        });
    }

    public function down(): void
    {
        Schema::table('gtm_tags', function (Blueprint $table) {
            $table->dropColumn(['kind', 'contact_person', 'contact', 'purpose', 'used_on', 'confidence', 'confirmed_at']);
        });
    }
};
