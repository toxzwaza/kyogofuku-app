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
        Schema::create('plans', function (Blueprint $table) {
            $table->id()->comment('プランID');

                $table->string('name')
                    ->comment('プラン名（例：袴R、振袖フルセット）');

                $table->string('code')->unique()
                    ->comment('プランコード（システム用）');

                $table->text('description')->nullable()
                    ->comment('プラン説明');

                $table->integer('base_price')->nullable()
                    ->comment('基本価格（仮・税込想定）');

                $table->boolean('is_active')->default(true)
                    ->comment('有効フラグ');

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
        Schema::dropIfExists('plans');
    }
};
