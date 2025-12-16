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
        Schema::table('photo_slots', function (Blueprint $table) {
            $table->dropForeign(['shop_id']);
            $table->dropColumn('shop_id');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('photo_slots', function (Blueprint $table) {
            $table->foreignId('shop_id')
                ->nullable()
                ->after('assignment_label')
                ->constrained('shops')
                ->nullOnDelete()
                ->comment('担当店舗ID');
        });
    }
};
