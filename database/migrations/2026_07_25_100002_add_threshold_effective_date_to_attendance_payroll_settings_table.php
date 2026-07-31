<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attendance_payroll_settings', function (Blueprint $table) {
            // 閾値方式（新残業判定）の適用開始日。この日以降の勤務分から新方式を適用し、
            // それより前のレコードは従来挙動（据え置き）とする。null のときは全期間に新方式を適用。
            $table->date('threshold_effective_date')->nullable()->after('overtime_rounding_unit_minutes');
        });
    }

    public function down(): void
    {
        Schema::table('attendance_payroll_settings', function (Blueprint $table) {
            $table->dropColumn('threshold_effective_date');
        });
    }
};
