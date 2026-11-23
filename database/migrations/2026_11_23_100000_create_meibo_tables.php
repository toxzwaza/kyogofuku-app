<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 名簿（成人式 DM 名簿）の表。設計は taskboard page-28「名簿のメインシステム統合：設計」。
 *
 * 列は名簿管理システム（Mac の FastAPI 版）の persons / dm_logs / label_templates / zip_geo にそろえ、
 * 後の段階の取り込み（meibo:import）で列をそのまま移せるようにしている。
 * 既存の表は変えない（meibo_* を足すだけ）。
 */
return new class extends Migration
{
    public function up()
    {
        Schema::create('meibo_persons', function (Blueprint $table) {
            $table->id();
            // 統合ID（M〇〇〇〇〇〇）。印刷済みの QR に入っているので振り直さない。取り込みはこれで突き合わせる
            $table->string('master_code', 12)->unique()->comment('統合ID');
            $table->string('pref', 10)->nullable()->comment('都道府県');
            $table->unsignedSmallInteger('seijin_year')->nullable()->comment('成人式年（西暦）');
            $table->unsignedSmallInteger('birth_fy')->nullable()->comment('生年度');
            $table->string('name', 100)->comment('本人氏名');
            $table->string('kana', 100)->nullable()->comment('本人フリガナ');
            $table->date('birth_date')->nullable();
            $table->string('gender', 4)->nullable();
            $table->string('zip', 10)->nullable()->index();
            $table->string('address')->nullable();
            $table->string('tel', 20)->nullable();
            $table->string('mobile', 20)->nullable();
            $table->string('guardian_name', 100)->nullable();
            $table->string('guardian_kana', 100)->nullable();
            // 出所（"G_R07/G_R11/H_2024" のような「/」区切り）。R11 の絞り込みは LIKE で見る
            $table->string('source_versions', 255)->nullable()->comment('統合元版');
            $table->unsignedSmallInteger('merge_count')->default(1)->comment('統合件数');
            $table->string('dm_status', 10)->default('送付可')->comment('送付可／未達／停止');
            $table->string('dm_note', 255)->default('')->comment('DM 備考');
            $table->timestamps();

            $table->index(['pref', 'seijin_year']);
            $table->index('dm_status');
            $table->index('name');
            $table->index('kana');
        });

        Schema::create('meibo_dm_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('person_id')->constrained('meibo_persons')->cascadeOnDelete();
            $table->string('old_status', 10)->nullable();
            $table->string('new_status', 10)->nullable();
            $table->string('old_note', 255)->nullable();
            $table->string('note', 255)->nullable()->comment('変更後の備考');
            $table->foreignId('changed_by_user_id')->nullable()->constrained('users')->nullOnDelete()->comment('誰が変えたか（取り込んだ昔の履歴は空）');
            $table->dateTime('changed_at')->useCurrent();

            $table->index(['person_id', 'changed_at']);
        });

        Schema::create('meibo_label_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name', 60)->unique();
            $table->string('sender_zip', 10)->default('');
            $table->string('sender_address')->default('');
            $table->string('sender_company', 100)->default('');
            $table->string('addressee', 10)->default('self')->comment('self=本人宛／guardian=保護者宛');
            $table->boolean('with_family')->default(true)->comment('氏名の下に「ご家族様」');
            $table->string('tantou', 60)->default('');
            $table->timestamps();
        });

        Schema::create('meibo_zip_geo', function (Blueprint $table) {
            $table->string('zip', 10)->primary();
            $table->string('pref', 10)->nullable();
            $table->double('lat');
            $table->double('lng');
            $table->string('src')->nullable()->comment('座標をどこから取ったか');
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('meibo_zip_geo');
        Schema::dropIfExists('meibo_label_templates');
        Schema::dropIfExists('meibo_dm_logs');
        Schema::dropIfExists('meibo_persons');
    }
};
