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
        Schema::create('email_threads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_reservation_id')->constrained('event_reservations')->onDelete('cascade');
            $table->string('subject');
            $table->timestamps();
            
            $table->index('event_reservation_id');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('email_threads');
    }
};
