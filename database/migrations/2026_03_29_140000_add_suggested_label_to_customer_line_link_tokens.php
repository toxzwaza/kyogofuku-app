<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customer_line_link_tokens', function (Blueprint $table) {
            $table->string('suggested_label', 50)->nullable()->after('shop_id');
        });
    }

    public function down(): void
    {
        Schema::table('customer_line_link_tokens', function (Blueprint $table) {
            $table->dropColumn('suggested_label');
        });
    }
};
