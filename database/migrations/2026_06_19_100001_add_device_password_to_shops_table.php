<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shops', function (Blueprint $table) {
            $table->string('device_password')->nullable()->after('google_calendar_id'); // 端末登録用パスワード（ハッシュ）
            $table->timestamp('device_password_updated_at')->nullable()->after('device_password');
        });
    }

    public function down(): void
    {
        Schema::table('shops', function (Blueprint $table) {
            $table->dropColumn(['device_password', 'device_password_updated_at']);
        });
    }
};
