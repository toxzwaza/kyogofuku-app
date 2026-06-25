<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_stages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')
                ->unique()
                ->constrained()
                ->cascadeOnDelete()
                ->comment('顧客ID');
            $table->string('stage')->default('bronze')->comment('bronze/silver/gold/platinum');
            $table->unsignedInteger('matured_referrals_count')->default(0)->comment('成立(matured)した紹介数');
            $table->timestamp('last_evaluated_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_stages');
    }
};
