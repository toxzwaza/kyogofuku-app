<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('referral_points', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')
                ->unique()
                ->constrained()
                ->cascadeOnDelete()
                ->comment('顧客ID（紹介者・被紹介者 共通の残高）');
            $table->integer('balance')->default(0)->comment('保有ポイント残高（1pt=1円）');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('referral_points');
    }
};
