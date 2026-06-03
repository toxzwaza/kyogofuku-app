<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->unsignedBigInteger('thumbnail_media_file_id')->nullable()->after('ended_message_text');
            $table->string('thumbnail_path')->nullable()->after('thumbnail_media_file_id');
            $table->string('thumbnail_storage_disk')->nullable()->default('public')->after('thumbnail_path');

            $table->foreign('thumbnail_media_file_id')
                ->references('id')->on('media_files')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->dropForeign(['thumbnail_media_file_id']);
            $table->dropColumn(['thumbnail_media_file_id', 'thumbnail_path', 'thumbnail_storage_disk']);
        });
    }
};
