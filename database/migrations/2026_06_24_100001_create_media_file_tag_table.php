<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('media_file_tag', function (Blueprint $table) {
            $table->id();
            $table->foreignId('media_file_id')
                ->constrained('media_files')
                ->cascadeOnDelete()
                ->comment('メディアID（media_files.id）');
            $table->foreignId('media_tag_id')
                ->constrained('media_tags')
                ->cascadeOnDelete()
                ->comment('タグID（media_tags.id）');
            $table->timestamps();

            $table->unique(['media_file_id', 'media_tag_id'], 'uq_media_file_tag');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('media_file_tag');
    }
};
