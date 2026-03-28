<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customer_constraints', function (Blueprint $table) {
            $table->string('attachment_path', 512)->nullable()->after('check_values');
            $table->string('attachment_disk', 32)->nullable()->after('attachment_path');
            $table->string('attachment_original_name', 255)->nullable()->after('attachment_disk');
        });
    }

    public function down(): void
    {
        Schema::table('customer_constraints', function (Blueprint $table) {
            $table->dropColumn(['attachment_path', 'attachment_disk', 'attachment_original_name']);
        });
    }
};
