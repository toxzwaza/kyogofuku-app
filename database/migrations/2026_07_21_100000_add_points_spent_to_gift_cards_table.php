<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('gift_cards', function (Blueprint $table) {
            // 発行時に消費したポイント（交換レート適用後）。旧データはnull＝amount(円)を1pt=1円で返還。
            $table->unsignedInteger('points_spent')->nullable()->after('amount')->comment('発行時の消費ポイント（交換レート適用後）');
        });
    }

    public function down(): void
    {
        Schema::table('gift_cards', function (Blueprint $table) {
            $table->dropColumn('points_spent');
        });
    }
};
