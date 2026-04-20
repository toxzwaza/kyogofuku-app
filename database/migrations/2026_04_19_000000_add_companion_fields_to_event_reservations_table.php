<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('event_reservations', function (Blueprint $table) {
            $table->json('companion_types')->nullable()->after('visitor_count');
            $table->boolean('companion_hakama_usage')->nullable()->after('companion_types');
        });
    }

    public function down(): void
    {
        Schema::table('event_reservations', function (Blueprint $table) {
            $table->dropColumn(['companion_types', 'companion_hakama_usage']);
        });
    }
};
