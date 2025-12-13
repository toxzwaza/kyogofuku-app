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
        Schema::create('photo_slots', function (Blueprint $table) {
            $table->id()->comment('前撮り枠ID');

            $table->foreignId('photo_studio_id')
                ->constrained('photo_studios')
                ->comment('前撮り会場ID');

            $table->date('shoot_date')
                ->comment('撮影日');

            $table->time('shoot_time')
                ->comment('撮影開始時刻');

            $table->foreignId('customer_id')
                ->nullable()
                ->constrained('customers')
                ->nullOnDelete()
                ->comment('予約顧客ID');

            $table->text('remarks')->nullable()
                ->comment('前撮り枠に関する備考');

            $table->timestamps();

            $table->unique(
                ['photo_studio_id', 'shoot_date', 'shoot_time'],
                'uq_photo_slot'
            );
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('photo_slots');
    }
};
