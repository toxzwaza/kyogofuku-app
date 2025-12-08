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
        Schema::table('event_reservations', function (Blueprint $table) {
            // 資料請求フォーム専用項目
            $table->string('request_method')->nullable()->after('phone'); // 郵送・デジタルカタログ
            $table->string('postal_code')->nullable()->after('request_method');
            $table->boolean('privacy_agreed')->default(false)->after('inquiry_message');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('event_reservations', function (Blueprint $table) {
            $table->dropColumn(['request_method', 'postal_code', 'privacy_agreed']);
        });
    }
};

