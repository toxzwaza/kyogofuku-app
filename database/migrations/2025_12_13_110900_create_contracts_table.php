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
        Schema::create('contracts', function (Blueprint $table) {
            $table->id()->comment('成約ID');

            $table->foreignId('customer_id')
                ->constrained()
                ->cascadeOnDelete()
                ->comment('顧客ID（customers.id）');

            $table->foreignId('shop_id')
                ->constrained()
                ->comment('店舗ID（shops.id）');

            $table->foreignId('plan_id')
                ->constrained()
                ->comment('プランID（plans.id）');

            $table->date('contract_date')
                ->comment('成約日');

            $table->string('kimono_type')
                ->comment('着物種別（振袖 / 袴）');

            $table->boolean('warranty_flag')->default(false)
                ->comment('安心保証フラグ');

            $table->integer('total_amount')
                ->comment('成約金額（税込）');

            $table->string('preparation_venue')->nullable()
                ->comment('お仕度会場');

            $table->date('preparation_date')->nullable()
                ->comment('お仕度日程');

            $table->foreignId('user_id')
                ->nullable()
                ->constrained('users')
                ->comment('担当スタッフID');

            $table->text('remarks')->nullable()
                ->comment('成約に関する備考');

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
        Schema::dropIfExists('contracts');
    }
};
