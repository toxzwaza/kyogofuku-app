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
        Schema::create('photo_studios', function (Blueprint $table) {
            $table->id()->comment('前撮り会場ID');

            $table->string('name')
                ->comment('前撮り会場名');

            $table->string('address')->nullable()
                ->comment('会場住所');

            $table->text('remarks')->nullable()
                ->comment('会場に関する備考');

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
        Schema::dropIfExists('photo_studios');
    }
};
