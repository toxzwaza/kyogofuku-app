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
        Schema::table('emails', function (Blueprint $table) {
            $table->foreignId('event_reservation_id')->nullable()->after('id')->constrained('event_reservations')->onDelete('cascade');
            $table->foreignId('email_thread_id')->nullable()->after('event_reservation_id')->constrained('email_threads')->onDelete('cascade');
            
            $table->index('event_reservation_id');
            $table->index('email_thread_id');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('emails', function (Blueprint $table) {
            $table->dropForeign(['event_reservation_id']);
            $table->dropForeign(['email_thread_id']);
            $table->dropIndex(['event_reservation_id']);
            $table->dropIndex(['email_thread_id']);
            $table->dropColumn(['event_reservation_id', 'email_thread_id']);
        });
    }
};
