<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_constraints', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->onDelete('cascade');
            $table->foreignId('constraint_template_id')->constrained()->onDelete('cascade');
            $table->date('signed_at')->nullable();
            $table->text('signature_image')->nullable();
            $table->foreignId('explainer_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->json('check_values')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_constraints');
    }
};
