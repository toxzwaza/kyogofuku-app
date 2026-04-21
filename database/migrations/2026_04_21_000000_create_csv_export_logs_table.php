<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('csv_export_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('target', 64);
            $table->json('filters')->nullable();
            $table->json('columns')->nullable();
            $table->unsignedInteger('row_count')->default(0);
            $table->string('file_name', 255)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['target', 'created_at']);
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('csv_export_logs');
    }
};
