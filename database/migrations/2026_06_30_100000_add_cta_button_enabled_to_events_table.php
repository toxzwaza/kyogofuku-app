<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            // フッターCTAの WEB予約／電話予約ボタンの表示有効フラグ（既定は両方有効）
            $table->boolean('cta_web_button_enabled')->default(true)->after('cta_phone_button_path');
            $table->boolean('cta_phone_button_enabled')->default(true)->after('cta_web_button_enabled');
        });
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->dropColumn([
                'cta_web_button_enabled',
                'cta_phone_button_enabled',
            ]);
        });
    }
};
