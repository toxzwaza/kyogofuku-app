<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * クレジット支払管理（Dropbox の「支払一覧表」Excel の置き換え）。
 *  - credit_companies : 信販会社マスタ（needs_check=true の会社だけ毎月の支払確認が要る＝自社・現金）
 *  - credit_contracts : クレジット契約（Excel の1列）
 *  - credit_payments  : 月ごとの支払予定と支払済チェック
 *  - users.can_edit_credit : 登録・修正・支払済チェックができる人（閲覧は全スタッフ）
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('credit_companies', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->json('aliases')->nullable()->comment('取り込み時の表記揺れ');
            $table->boolean('needs_check')->default(false)->comment('毎月の支払確認が要る（自社・現金）');
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('credit_contracts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->restrictOnDelete();
            $table->foreignId('credit_company_id')->nullable()->constrained()->nullOnDelete();
            $table->date('contract_date')->nullable();
            $table->string('item_name')->nullable();
            $table->integer('principal')->default(0)->comment('元金');
            $table->integer('fee')->nullable()->comment('手数料');
            $table->foreignId('staff_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('staff_name')->nullable()->comment('担当者表記（退職者もそのまま残す）');
            $table->string('status', 20)->default('active')->comment('active=支払中 / completed=完済 / cancelled=解約・組換');
            $table->text('note')->nullable();
            $table->string('source_file')->nullable()->comment('取り込み元（移行の追跡用）');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['status', 'credit_company_id']);
        });

        Schema::create('credit_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('credit_contract_id')->constrained()->cascadeOnDelete();
            $table->date('due_month')->comment('その月の1日');
            $table->integer('amount');
            $table->boolean('is_paid')->default(false);
            $table->dateTime('paid_checked_at')->nullable();
            $table->foreignId('paid_checked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('note')->nullable();
            $table->timestamps();
            $table->unique(['credit_contract_id', 'due_month']);
            $table->index(['due_month', 'is_paid']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->boolean('can_edit_credit')->default(false)->after('attendance_role')
                ->comment('クレジットの登録・修正・支払済チェックができる');
        });

        $now = now();
        $companies = [
            ['セディナ', ['ｾﾃﾞｨﾅ', 'セディナー'], false],
            ['JACCS', ['ジャックス', 'ｼﾞｬｯｸｽ', 'jACCS', 'Jaccs', 'ＪＡＣＣＳ'], false],
            ['日専連', ['日専連ﾌｧｲﾅﾝｽ'], false],
            ['日専連カード', ['日専連ｶｰﾄﾞ'], false],
            ['Nクレジット（旧ヤマトクレジット）', ['ヤマト', 'ﾔﾏﾄ', 'ヤマトクレジット', 'N', 'Ｎ', 'Nｸﾚｼﾞｯﾄ', 'Nクレジット'], false],
            ['山陰', ['山陰信販', 'ｻﾝｲﾝ'], false],
            ['トワライズ', ['ﾄﾜﾗｲｽﾞ'], false],
            ['CBS', [], false],
            ['ABCキャピタル', ['ABC', 'ABCｷｬﾋﾟﾀﾙ'], false],
            ['自社', ['自社クレジット', '自社ｸﾚｼﾞｯﾄ'], true],
            ['現金', ['現金回収'], true],
        ];
        foreach ($companies as $i => [$name, $aliases, $needsCheck]) {
            DB::table('credit_companies')->insert([
                'name' => $name,
                'aliases' => json_encode($aliases, JSON_UNESCAPED_UNICODE),
                'needs_check' => $needsCheck,
                'sort_order' => ($i + 1) * 10,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('can_edit_credit');
        });
        Schema::dropIfExists('credit_payments');
        Schema::dropIfExists('credit_contracts');
        Schema::dropIfExists('credit_companies');
    }
};
