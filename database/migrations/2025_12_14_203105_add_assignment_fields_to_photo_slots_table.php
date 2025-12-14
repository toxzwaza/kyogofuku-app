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
            $table->string('assignment_label')->nullable()->after('remarks')->comment('担当者用メモラベル');
            $table->foreignId('shop_id')->nullable()->after('assignment_label')->constrained('shops')->nullOnDelete()->comment('担当店舗ID');
            $table->foreignId('user_id')->nullable()->after('shop_id')->constrained('users')->nullOnDelete()->comment('担当者ID');
            $table->foreignId('plan_id')->nullable()->after('user_id')->constrained('plans')->nullOnDelete()->comment('プランID');
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
            $table->dropForeign(['shop_id']);
            $table->dropForeign(['user_id']);
            $table->dropForeign(['plan_id']);
            $table->dropColumn(['assignment_label', 'shop_id', 'user_id', 'plan_id']);
        });
    }
};
