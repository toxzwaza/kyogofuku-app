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
        Schema::create('photo_slot_shop', function (Blueprint $table) {
            $table->id()->comment('ID');
            $table->foreignId('photo_slot_id')
                ->constrained('photo_slots')
                ->onDelete('cascade')
                ->comment('前撮り枠ID');
            $table->foreignId('shop_id')
                ->constrained('shops')
                ->onDelete('cascade')
                ->comment('担当店舗ID');
            $table->timestamps();
            
            $table->unique(['photo_slot_id', 'shop_id'], 'uq_photo_slot_shop');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('photo_slot_shop');
    }
};
