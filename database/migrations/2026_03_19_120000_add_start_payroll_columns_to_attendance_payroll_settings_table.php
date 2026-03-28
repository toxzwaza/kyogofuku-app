<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attendance_payroll_settings', function (Blueprint $table) {
            $table->unsignedSmallInteger('start_early_threshold_minutes')->default(0)->after('id');
            $table->unsignedSmallInteger('start_rounding_unit_minutes')->default(1)->after('start_early_threshold_minutes');
        });
    }

    public function down(): void
    {
        Schema::table('attendance_payroll_settings', function (Blueprint $table) {
            $table->dropColumn(['start_early_threshold_minutes', 'start_rounding_unit_minutes']);
        });
    }
};
