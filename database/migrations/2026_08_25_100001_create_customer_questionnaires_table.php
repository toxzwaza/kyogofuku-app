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
        Schema::create('customer_questionnaires', function (Blueprint $table) {
            $table->id()->comment('アンケートID');

            $table->foreignId('customer_id')
                ->constrained()
                ->cascadeOnDelete()
                ->comment('顧客ID');

            $table->foreignId('page1_photo_id')->nullable()
                ->constrained('customer_photos')
                ->nullOnDelete()
                ->comment('1ページ目スキャン（customer_photos）');

            $table->foreignId('page2_photo_id')->nullable()
                ->constrained('customer_photos')
                ->nullOnDelete()
                ->comment('2ページ目スキャン（customer_photos）');

            $table->json('placements')->nullable()
                ->comment('写真添付欄の配置情報（用紙比率の相対座標）');

            $table->string('composed_page2_path')->nullable()
                ->comment('写真合成済み2ページ目のS3パス');

            $table->timestamps();

            $table->unique('customer_id');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('customer_questionnaires');
    }
};
