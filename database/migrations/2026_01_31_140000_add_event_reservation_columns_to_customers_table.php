<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * イベント予約に必要なカラムをcustomersテーブルに追加
     */
    public function up()
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->string('email')->nullable()->after('address')->comment('メールアドレス');
            $table->boolean('has_visited_before')->default(false)->after('email')->comment('過去来店有無');
            $table->string('referred_by_name')->nullable()->after('has_visited_before')->comment('紹介者名');
            $table->string('school_name')->nullable()->after('referred_by_name')->comment('学校名');
            $table->string('staff_name')->nullable()->after('school_name')->comment('担当者名');
            $table->json('visit_reasons')->nullable()->after('staff_name')->comment('来店動機');
            $table->string('parking_usage')->nullable()->after('visit_reasons')->comment('駐車場利用');
            $table->integer('parking_car_count')->nullable()->after('parking_usage')->comment('駐車台数');
            $table->json('considering_plans')->nullable()->after('parking_car_count')->comment('検討中プラン');
            $table->string('heard_from')->nullable()->after('considering_plans')->comment('認知経路');
            $table->text('inquiry_message')->nullable()->after('heard_from')->comment('お問い合わせ内容');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down()
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn([
                'email',
                'has_visited_before',
                'referred_by_name',
                'school_name',
                'staff_name',
                'visit_reasons',
                'parking_usage',
                'parking_car_count',
                'considering_plans',
                'heard_from',
                'inquiry_message',
            ]);
        });
    }
};
