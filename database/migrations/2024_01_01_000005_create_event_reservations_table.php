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
        Schema::create('event_reservations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->onDelete('cascade');
            $table->string('name');
            $table->string('email');
            $table->string('phone');
            
            // 予約フォーム専用項目
            $table->string('venue')->nullable();
            $table->string('reservation_datetime')->nullable();
            $table->string('used_koichi_for_seijin')->nullable();
            $table->string('furigana')->nullable();
            $table->string('school_name')->nullable();
            $table->string('parking_usage')->nullable();
            $table->integer('parking_car_count')->nullable();
            $table->string('considering_plan')->nullable();
            $table->string('heard_from')->nullable();
            $table->text('inquiry_message')->nullable();
            
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
        Schema::dropIfExists('event_reservations');
    }
};

