<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // 休憩の取り方: fixed=所定固定休憩を控除 / manual=打刻時にモーダルで都度入力
            $table->string('break_mode', 20)->default('manual')->after('work_attribute_id');
            // break_mode=fixed のときの所定休憩（分）。60=1h, 85=1h25min, 90=1.5h。manual 時は null
            $table->unsignedSmallInteger('scheduled_break_minutes')->nullable()->after('break_mode');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['break_mode', 'scheduled_break_minutes']);
        });
    }
};
