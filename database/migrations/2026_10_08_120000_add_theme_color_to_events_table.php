<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            // LPテーマカラー（#RRGGBB）。CTA・ナビ・予約フォームで共有
            $table->string('theme_color', 9)->nullable()->after('cta_color_type');
        });
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->dropColumn('theme_color');
        });
    }
};
