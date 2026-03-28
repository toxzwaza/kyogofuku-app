<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->string('seijin_preparation_venue')->nullable()->after('ceremony_area_id');
            $table->string('seijin_preparation_time')->nullable()->after('seijin_preparation_venue');
            $table->boolean('other_store_preparation')->default(false)->after('seijin_preparation_time');
            $table->string('other_store_salon_name')->nullable()->after('other_store_preparation');
            $table->string('other_store_salon_address')->nullable()->after('other_store_salon_name');
            $table->string('other_store_salon_phone')->nullable()->after('other_store_salon_address');
            $table->date('kimono_ship_date')->nullable()->after('other_store_salon_phone');
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn([
                'seijin_preparation_venue',
                'seijin_preparation_time',
                'other_store_preparation',
                'other_store_salon_name',
                'other_store_salon_address',
                'other_store_salon_phone',
                'kimono_ship_date',
            ]);
        });
    }
};
