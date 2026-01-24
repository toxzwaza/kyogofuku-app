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
        Schema::create('timeslot_template_slots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('timeslot_template_id')->constrained()->onDelete('cascade');
            $table->integer('hour')->comment('時（0-23）');
            $table->integer('minute')->comment('分（0-59）');
            $table->integer('capacity')->comment('枠数');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('timeslot_template_slots');
    }
};
