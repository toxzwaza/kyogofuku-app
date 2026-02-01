<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 振袖アンケート（Informationシート）の回答をJSONで保存
     */
    public function up()
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->json('additional_info')->nullable()->after('remarks')->comment('追加情報（振袖アンケート等）');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down()
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn('additional_info');
        });
    }
};
