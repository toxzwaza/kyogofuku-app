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
        Schema::table('users', function (Blueprint $table) {
            $table->text('google_calendar_refresh_token')->nullable()->after('remember_token');
            $table->timestamp('google_calendar_token_expires_at')->nullable()->after('google_calendar_refresh_token');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['google_calendar_refresh_token', 'google_calendar_token_expires_at']);
        });
    }
};
