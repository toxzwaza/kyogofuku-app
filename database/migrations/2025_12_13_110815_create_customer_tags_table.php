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
        Schema::create('customer_tags', function (Blueprint $table) {
            $table->id()->comment('顧客タグID');

            $table->string('name')
                ->comment('タグ名（例：クレーム履歴あり）');

            $table->string('slug')->unique()
                ->comment('タグ識別子（システム用、一意）');

            $table->text('description')->nullable()
                ->comment('タグの説明');

            $table->string('color')->nullable()
                ->comment('タグ表示色（UI用）');

            $table->boolean('is_active')->default(true)
                ->comment('有効フラグ（0:無効, 1:有効）');

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
        Schema::dropIfExists('customer_tags');
    }
};
