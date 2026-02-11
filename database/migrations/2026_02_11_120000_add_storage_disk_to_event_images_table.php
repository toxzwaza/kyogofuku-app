<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('event_images', function (Blueprint $table) {
            $table->string('storage_disk', 32)->default('public')->after('path');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('event_images', function (Blueprint $table) {
            $table->dropColumn('storage_disk');
        });
    }
};
