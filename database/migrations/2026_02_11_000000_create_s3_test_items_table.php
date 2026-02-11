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
        Schema::create('s3_test_items', function (Blueprint $table) {
            $table->id();
            $table->string('path');
            $table->string('visibility_type'); // 'private' | 'public'
            $table->string('original_name')->nullable();
            $table->timestamps();
        });

        Schema::table('s3_test_items', function (Blueprint $table) {
            $table->index('visibility_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('s3_test_items');
    }
};
