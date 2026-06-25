<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stage_settings', function (Blueprint $table) {
            $table->id();
            $table->string('stage')->unique()->comment('bronze/silver/gold/platinum');
            $table->unsignedInteger('min_referrals')->default(0)->comment('このステージになる最小成立人数');
            $table->decimal('reward_rate_percent', 5, 2)->default(0)->comment('還元率%（成約金額に対する）');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stage_settings');
    }
};
