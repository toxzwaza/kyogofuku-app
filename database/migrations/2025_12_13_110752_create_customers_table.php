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
        Schema::create('customers', function (Blueprint $table) {
            $table->id()->comment('顧客ID');

            $table->string('name')
                ->comment('顧客氏名（フルネーム）');

            $table->string('kana')->nullable()
                ->comment('顧客氏名のふりがな');

            $table->string('guardian_name')->nullable()
                ->comment('保護者氏名');

            $table->string('guardian_name_kana')->nullable()
                ->comment('保護者氏名のふりがな');

            $table->date('birth_date')->nullable()
                ->comment('生年月日');

            $table->integer('coming_of_age_year')->nullable()
                ->comment('成人年度（例：2026）');

            $table->foreignId('ceremony_area_id')
                ->nullable()
                ->constrained()
                ->comment('成人式エリアID（ceremony_areas.id）');

            $table->string('phone_number')->nullable()
                ->comment('電話番号');

            $table->string('postal_code')->nullable()
                ->comment('郵便番号（例：700-0914）');

            $table->text('address')->nullable()
                ->comment('住所（郵便番号除く）');

            $table->text('remarks')->nullable()
                ->comment('顧客に関する備考・注意事項');

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
        Schema::dropIfExists('customers');
    }
};
