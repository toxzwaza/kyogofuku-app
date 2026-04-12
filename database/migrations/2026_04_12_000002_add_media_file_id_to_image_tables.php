<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('event_images', function (Blueprint $table) {
            $table->foreignId('media_file_id')->nullable()->after('event_id')
                ->constrained('media_files')->nullOnDelete();
        });

        Schema::table('slideshow_images', function (Blueprint $table) {
            $table->foreignId('media_file_id')->nullable()->after('slideshow_id')
                ->constrained('media_files')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('event_images', function (Blueprint $table) {
            $table->dropForeign(['media_file_id']);
            $table->dropColumn('media_file_id');
        });

        Schema::table('slideshow_images', function (Blueprint $table) {
            $table->dropForeign(['media_file_id']);
            $table->dropColumn('media_file_id');
        });
    }
};
