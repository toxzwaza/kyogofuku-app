<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('media_files', function (Blueprint $table) {
            $table->id();
            $table->string('original_filename');
            $table->string('path');
            $table->string('storage_disk')->default('s3');
            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('file_size')->nullable();
            $table->unsignedInteger('width')->nullable();
            $table->unsignedInteger('height')->nullable();
            $table->string('alt')->nullable();
            $table->json('tags')->nullable();
            $table->timestamps();

            $table->index('original_filename');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('media_files');
    }
};
