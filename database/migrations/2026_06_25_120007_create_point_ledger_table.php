<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('point_ledger', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')
                ->constrained()
                ->cascadeOnDelete()
                ->comment('顧客ID');
            $table->integer('amount')->comment('+獲得 / -引換・調整');
            // referrer_reward / referred_bonus / gift_card_redeem / gift_card_cancel_refund / adjust
            $table->string('type')->comment('増減の種別');
            $table->foreignId('referral_id')
                ->nullable()
                ->constrained('referrals')
                ->nullOnDelete()
                ->comment('付与の根拠となる紹介ID');
            $table->foreignId('gift_card_id')
                ->nullable()
                ->constrained('gift_cards')
                ->nullOnDelete()
                ->comment('引換/返還の根拠となるギフトカードID');
            $table->string('note')->nullable();
            $table->timestamps();

            $table->index(['customer_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('point_ledger');
    }
};
