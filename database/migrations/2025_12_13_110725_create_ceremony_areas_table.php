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
        Schema::create('ceremony_areas', function (Blueprint $table) {
            $table->id()->comment('成人式エリアID');

            $table->string('name')
                ->comment('成人式エリア名（例：赤磐市、岡山市）');
                
            $table->string('furi')->nullable()
                ->comment('成人式エリア名のふりがな');

            $table->string('prefecture')
                ->comment('都道府県名（例：岡山県）');

            $table->text('remarks')->nullable()
                ->comment('エリアに関する補足メモ');

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
        Schema::dropIfExists('ceremony_areas');
    }
};
