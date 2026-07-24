<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('point_ledger', function (Blueprint $table) {
            // ポイント種別：referral=紹介ポイント / hirata=平田ポイント（用途制限あり）
            $table->string('point_type')->default('referral')->after('type')->comment('referral / hirata');
            $table->index(['customer_id', 'point_type']);
        });
    }

    public function down(): void
    {
        Schema::table('point_ledger', function (Blueprint $table) {
            $table->dropIndex(['customer_id', 'point_type']);
            $table->dropColumn('point_type');
        });
    }
};
