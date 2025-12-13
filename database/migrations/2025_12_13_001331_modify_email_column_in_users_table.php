<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // unique制約を削除
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['email']);
        });
        
        // 生のSQLでemailカラムをnullableに変更
        DB::statement('ALTER TABLE users MODIFY email VARCHAR(255) NULL');
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        // 生のSQLでemailカラムをnullable解除
        DB::statement('ALTER TABLE users MODIFY email VARCHAR(255) NOT NULL');
        
        // unique制約を復元
        Schema::table('users', function (Blueprint $table) {
            $table->unique('email');
        });
    }
};
