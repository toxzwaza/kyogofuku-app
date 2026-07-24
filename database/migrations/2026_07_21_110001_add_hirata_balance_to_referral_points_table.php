<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('referral_points', function (Blueprint $table) {
            // 平田ポイント残高（既存 balance は紹介ポイント残高）
            $table->integer('hirata_balance')->default(0)->after('balance')->comment('平田ポイント残高（1pt=1円・物品購入/譲渡のみ）');
        });
    }

    public function down(): void
    {
        Schema::table('referral_points', function (Blueprint $table) {
            $table->dropColumn('hirata_balance');
        });
    }
};
