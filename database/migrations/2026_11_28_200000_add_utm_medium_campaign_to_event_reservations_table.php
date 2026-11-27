<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 予約に utm_medium・utm_campaign も残す（GBP・HP・広告を予約データだけで見分けるため）。
 * 既存の行は NULL のまま（値の書き換えはしない）。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('event_reservations', function (Blueprint $table) {
            if (!Schema::hasColumn('event_reservations', 'utm_medium')) {
                $table->string('utm_medium')->nullable()->after('utm_source');
            }
            if (!Schema::hasColumn('event_reservations', 'utm_campaign')) {
                $table->string('utm_campaign')->nullable()->after('utm_medium');
            }
        });
    }

    public function down(): void
    {
        Schema::table('event_reservations', function (Blueprint $table) {
            if (Schema::hasColumn('event_reservations', 'utm_campaign')) {
                $table->dropColumn('utm_campaign');
            }
            if (Schema::hasColumn('event_reservations', 'utm_medium')) {
                $table->dropColumn('utm_medium');
            }
        });
    }
};
