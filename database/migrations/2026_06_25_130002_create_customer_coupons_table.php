<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_coupons', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')
                ->constrained()
                ->cascadeOnDelete()
                ->comment('保有顧客ID');
            $table->foreignId('coupon_id')
                ->constrained('coupons')
                ->restrictOnDelete()
                ->comment('クーポンマスタID');
            $table->string('status')->default('held')->comment('held（保有）/used（使用済）/expired（期限切れ）');
            $table->date('valid_until')->nullable()->comment('有効期限（配布時に確定）');
            $table->timestamp('used_at')->nullable();
            $table->foreignId('used_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('used_shop_id')->nullable()->constrained('shops')->nullOnDelete();
            $table->timestamps();

            $table->index(['customer_id', 'status']);
            $table->index(['status', 'valid_until']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_coupons');
    }
};
