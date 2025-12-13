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
        Schema::create('customer_tag_assignments', function (Blueprint $table) {
            $table->id()->comment('顧客タグ付与ID');

            $table->foreignId('customer_id')
                ->constrained()
                ->cascadeOnDelete()
                ->comment('顧客ID（customers.id）');

            $table->foreignId('customer_tag_id')
                ->constrained('customer_tags')
                ->cascadeOnDelete()
                ->comment('顧客タグID（customer_tags.id）');

            $table->text('note')->nullable()
                ->comment('タグ付与理由・補足');

            $table->timestamps();

            $table->unique(['customer_id', 'customer_tag_id'], 'uq_customer_tag');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('customer_tag_assignments');
    }
};
