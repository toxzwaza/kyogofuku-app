<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('staff_schedules', function (Blueprint $table) {
            $table->foreignId('event_reservation_id')->nullable()->after('user_id')->constrained('event_reservations')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('staff_schedules', function (Blueprint $table) {
            $table->dropForeign(['event_reservation_id']);
            $table->dropColumn('event_reservation_id');
        });
    }
};

