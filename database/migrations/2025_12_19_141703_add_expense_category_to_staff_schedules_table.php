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
            $table->string('expense_category')->nullable()->after('color')->comment('費用項目');
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
            $table->dropColumn('expense_category');
        });
    }
};
