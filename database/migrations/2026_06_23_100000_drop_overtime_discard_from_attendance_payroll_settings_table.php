<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * 残業は単位での切り捨てに統一したため、端数切り捨て上限の設定は不要になった。
     */
    public function up(): void
    {
        Schema::table('attendance_payroll_settings', function (Blueprint $table) {
            $table->dropColumn('overtime_discard_remainder_upto_minutes');
        });
    }

    public function down(): void
    {
        Schema::table('attendance_payroll_settings', function (Blueprint $table) {
            $table->unsignedSmallInteger('overtime_discard_remainder_upto_minutes')->default(15);
        });
    }
};
