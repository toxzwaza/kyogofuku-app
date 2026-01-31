<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * customersテーブルから不要なイベント予約用カラムを削除
     */
    public function up()
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn([
                'has_visited_before',
                'parking_usage',
                'parking_car_count',
                'heard_from',
                'inquiry_message',
            ]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down()
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->boolean('has_visited_before')->default(false)->after('email')->comment('過去来店有無');
            $table->string('parking_usage')->nullable()->after('visit_reasons')->comment('駐車場利用');
            $table->integer('parking_car_count')->nullable()->after('parking_usage')->comment('駐車台数');
            $table->string('heard_from')->nullable()->after('considering_plans')->comment('認知経路');
            $table->text('inquiry_message')->nullable()->after('heard_from')->comment('お問い合わせ内容');
        });
    }
};
