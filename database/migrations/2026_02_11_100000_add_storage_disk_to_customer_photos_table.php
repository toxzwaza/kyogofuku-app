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
        Schema::table('customer_photos', function (Blueprint $table) {
            $table->string('storage_disk', 16)->default('public')->after('file_path')
                ->comment('保存先: public=ローカル, s3=S3');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('customer_photos', function (Blueprint $table) {
            $table->dropColumn('storage_disk');
        });
    }
};
