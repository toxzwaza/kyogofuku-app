<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attendance_records', function (Blueprint $table) {
            // 日次パターン上書き（会社カレンダーに無い日のベース時刻算出用。A/B/C）
            $table->string('pattern_override', 1)->nullable()->after('status');
            // 振替対象日（代わりに休んだ出勤予定日）。登録ありなら振替勤務扱い＝休日出勤に含めない
            $table->date('substitute_for_date')->nullable()->after('pattern_override');
        });
    }

    public function down(): void
    {
        Schema::table('attendance_records', function (Blueprint $table) {
            $table->dropColumn(['pattern_override', 'substitute_for_date']);
        });
    }
};
