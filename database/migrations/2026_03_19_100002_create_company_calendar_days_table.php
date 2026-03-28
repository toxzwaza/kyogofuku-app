<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('company_calendar_days', function (Blueprint $table) {
            $table->id();
            $table->date('calendar_date')->unique();
            $table->string('pattern', 1)->nullable(); // A, B, C or null
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('company_calendar_days');
    }
};
