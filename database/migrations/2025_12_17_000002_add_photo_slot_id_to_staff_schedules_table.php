<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('staff_schedules', function (Blueprint $table) {
            $table->foreignId('photo_slot_id')
                ->nullable()
                ->after('event_reservation_id')
                ->constrained('photo_slots')
                ->nullOnDelete()
                ->comment('前撮り枠ID');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('staff_schedules', function (Blueprint $table) {
            $table->dropForeign(['photo_slot_id']);
            $table->dropColumn('photo_slot_id');
        });
    }
};

