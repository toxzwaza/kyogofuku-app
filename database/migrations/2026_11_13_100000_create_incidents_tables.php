<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 共有ノート（旧称 事案記録）。
 * 現場で起きたこと（クレーム・問い合わせ・ミス）と、ヒヤッとしたこと・気づき・提案を
 * 店舗グループ（okayama／fukui）内で共有する。
 * 要件書：CC_会社/_Company/02_京呉服平田/10_プロジェクト/20261026_業務マニュアル導入/成果物/20261113_要件書_事案記録・対応マニュアル.md
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('incidents', function (Blueprint $table) {
            $table->id();
            $table->string('group_key', 32)->index();          // 店舗グループ（記録した人のグループ）
            $table->date('occurred_on');                        // 発生日
            $table->string('shop_name', 100);                   // どの店舗で起きたか（手入力可）
            $table->string('category', 50);                     // 区分（振袖／袴／一般呉服／前撮り／その他の自由入力）
            $table->string('kind', 50);                         // 種類（クレーム…気づき・提案／その他の自由入力）
            $table->string('title', 100);
            $table->text('what');
            $table->text('action')->nullable();
            $table->text('consult')->nullable();
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();

            $table->index(['group_key', 'occurred_on']);
        });

        Schema::create('incident_comments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('incident_id')->constrained('incidents')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users');
            $table->text('body');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('incident_comments');
        Schema::dropIfExists('incidents');
    }
};
