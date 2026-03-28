<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attendance_payroll_settings', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('overtime_rounding_unit_minutes')->default(30);
            $table->unsignedSmallInteger('overtime_discard_remainder_upto_minutes')->default(15);
            $table->timestamps();
        });

        DB::table('attendance_payroll_settings')->insert([
            'overtime_rounding_unit_minutes' => 30,
            'overtime_discard_remainder_upto_minutes' => 15,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_payroll_settings');
    }
};
