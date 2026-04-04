<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('event_reservations', function (Blueprint $table) {
            $table->boolean('koichi_furisode_used')->nullable()->after('school_name');
            $table->smallInteger('graduation_ceremony_year')->nullable()->after('koichi_furisode_used');
            $table->unsignedTinyInteger('graduation_ceremony_month')->nullable()->after('graduation_ceremony_year');
            $table->unsignedSmallInteger('visitor_count')->nullable()->after('graduation_ceremony_month');
        });
    }

    public function down(): void
    {
        Schema::table('event_reservations', function (Blueprint $table) {
            $table->dropColumn([
                'koichi_furisode_used',
                'graduation_ceremony_year',
                'graduation_ceremony_month',
                'visitor_count',
            ]);
        });
    }
};
