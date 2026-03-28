<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('work_attribute_pattern_times', function (Blueprint $table) {
            $table->id();
            $table->foreignId('work_attribute_id')->constrained()->cascadeOnDelete();
            $table->string('pattern', 1); // A, B, C
            $table->string('day_type', 20); // weekday, weekend
            $table->time('work_start_time')->nullable();
            $table->time('work_end_time')->nullable();
            $table->timestamps();

            $table->unique(['work_attribute_id', 'pattern', 'day_type'], 'wapattern_times_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('work_attribute_pattern_times');
    }
};
