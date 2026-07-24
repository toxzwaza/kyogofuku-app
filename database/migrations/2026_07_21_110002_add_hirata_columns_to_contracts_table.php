<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contracts', function (Blueprint $table) {
            // 平田ポイント：確定を検知した日時（起算日）と付与済み日時
            $table->timestamp('hirata_eligible_at')->nullable()->after('status')->comment('平田ポイント付与の起算日（確定検知日）');
            $table->timestamp('hirata_granted_at')->nullable()->after('hirata_eligible_at')->comment('平田ポイント付与済み日時');
        });
    }

    public function down(): void
    {
        Schema::table('contracts', function (Blueprint $table) {
            $table->dropColumn(['hirata_eligible_at', 'hirata_granted_at']);
        });
    }
};
