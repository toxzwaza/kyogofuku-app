<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gift_cards', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')
                ->constrained()
                ->cascadeOnDelete()
                ->comment('引換した顧客ID');
            $table->unsignedInteger('amount')->comment('引換額（円・引換単位の倍数）');
            $table->string('status')->default('issued')->comment('issued（発行済）/canceled（取消）');
            $table->foreignId('issued_by_user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete()
                ->comment('「発行済み」操作をしたスタッフ');
            $table->foreignId('issued_shop_id')
                ->nullable()
                ->constrained('shops')
                ->nullOnDelete()
                ->comment('発行した店舗');
            $table->timestamp('issued_at')->nullable();
            $table->timestamp('canceled_at')->nullable();
            $table->timestamps();

            $table->index(['customer_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gift_cards');
    }
};
