<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * .env の GOOGLE_CALENDAR_REFRESH_TOKEN を使用するため、users のカラムは不要
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['google_calendar_refresh_token', 'google_calendar_token_expires_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->text('google_calendar_refresh_token')->nullable()->after('remember_token');
            $table->timestamp('google_calendar_token_expires_at')->nullable()->after('google_calendar_refresh_token');
        });
    }
};
