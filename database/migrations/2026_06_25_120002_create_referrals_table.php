<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('referrals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('referrer_customer_id')
                ->constrained('customers')
                ->cascadeOnDelete()
                ->comment('紹介者の顧客ID');
            $table->string('referred_line_user_id')->comment('被紹介者のLINE user_id（LIFF経由で取得）');
            $table->foreignId('referred_customer_id')
                ->nullable()
                ->constrained('customers')
                ->nullOnDelete()
                ->comment('被紹介者の顧客ID（後でcustomer作成時に後埋め）');
            // linked / contracted(仮) / matured(確定) / expired / rejected
            $table->string('status')->default('linked')->comment('紹介ステータス');
            $table->foreignId('contract_id')
                ->nullable()
                ->constrained('contracts')
                ->nullOnDelete()
                ->comment('成約レコードID');
            $table->timestamp('contracted_at')->nullable()->comment('成約検知時刻（matured判定の起算）');
            $table->timestamp('matured_at')->nullable()->comment('確定（特典付与）時刻');
            $table->timestamp('expires_at')->nullable()->comment('linked時刻＋有効期限（既定6ヶ月）');
            $table->string('reject_reason')->nullable()->comment('rejected の理由（self/existing_customer/duplicate 等）');
            $table->timestamps();

            $table->unique(['referrer_customer_id', 'referred_line_user_id'], 'uq_referral_referrer_line');
            $table->index('referred_line_user_id');
            $table->index(['status', 'expires_at']);
            $table->index(['status', 'contracted_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('referrals');
    }
};
