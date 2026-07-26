<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('work_attributes', function (Blueprint $table) {
            // 残業の決め方: base_end=ベース終業超過（従来・正社員） / threshold=実働-閾値（パート・時短）
            $table->string('overtime_mode', 20)->default('base_end')->after('name');
            // threshold 時の残業閾値（分）。480=8h, 470=7h50min。base_end 時は null
            $table->unsignedSmallInteger('overtime_threshold_minutes')->nullable()->after('overtime_mode');
        });
    }

    public function down(): void
    {
        Schema::table('work_attributes', function (Blueprint $table) {
            $table->dropColumn(['overtime_mode', 'overtime_threshold_minutes']);
        });
    }
};
