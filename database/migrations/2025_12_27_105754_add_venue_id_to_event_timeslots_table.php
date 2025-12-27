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
        Schema::table('event_timeslots', function (Blueprint $table) {
            $table->foreignId('venue_id')->nullable()->after('event_id')->constrained()->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('event_timeslots', function (Blueprint $table) {
            $table->dropForeign(['venue_id']);
            $table->dropColumn('venue_id');
        });
    }
};
