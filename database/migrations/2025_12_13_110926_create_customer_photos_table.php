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
        Schema::create('customer_photos', function (Blueprint $table) {
            $table->id()->comment('顧客写真ID');

            $table->foreignId('customer_id')
                ->constrained()
                ->cascadeOnDelete()
                ->comment('顧客ID');

            $table->foreignId('photo_type_id')
                ->constrained('photo_types')
                ->comment('写真種類ID');

            $table->string('file_path')
                ->comment('画像ファイルパス');

            $table->text('remarks')->nullable()
                ->comment('写真に関する備考');

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
        Schema::dropIfExists('customer_photos');
    }
};
