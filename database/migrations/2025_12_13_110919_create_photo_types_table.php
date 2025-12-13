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
        Schema::create('photo_types', function (Blueprint $table) {
            $table->id()->comment('写真種類ID');

            $table->string('name')
                ->comment('写真種類名（全身・半身アップなど）');

            $table->string('code')->unique()
                ->comment('写真種類コード');

            $table->text('description')->nullable()
                ->comment('写真種類の説明');

            $table->integer('sort_order')->default(0)
                ->comment('表示順');

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
        Schema::dropIfExists('photo_types');
    }
};
