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
        Schema::table('staff_schedules', function (Blueprint $table) {
            $table->dropColumn('color');
            $table->boolean('is_public')->default(true)->after('expense_category')->comment('公開フラグ（デフォルトは公開）');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('staff_schedules', function (Blueprint $table) {
            $table->string('color')->nullable()->default('#3788d8')->after('all_day');
            $table->dropColumn('is_public');
        });
    }
};
