<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('constraint_templates', function (Blueprint $table) {
            $table->json('display_settings')->nullable()->after('body');
        });
    }

    public function down(): void
    {
        Schema::table('constraint_templates', function (Blueprint $table) {
            $table->dropColumn('display_settings');
        });
    }
};
