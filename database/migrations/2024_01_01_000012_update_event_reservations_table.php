<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('event_reservations', function (Blueprint $table) {
            // venueカラムを削除し、venue_idを追加
            $table->dropColumn('venue');
            $table->foreignId('venue_id')->nullable()->after('phone')->constrained()->onDelete('set null');
            
            // 新規追加項目
            $table->boolean('has_visited_before')->default(false)->after('venue_id');
            $table->string('address')->nullable()->after('has_visited_before');
            $table->date('birth_date')->nullable()->after('address');
            $table->integer('seijin_year')->nullable()->after('birth_date');
            $table->string('referred_by_name')->nullable()->after('seijin_year');
            
            // considering_planを削除し、considering_plans（JSON）を追加
            $table->dropColumn('considering_plan');
            $table->json('considering_plans')->nullable()->after('heard_from');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('event_reservations', function (Blueprint $table) {
            $table->dropForeign(['venue_id']);
            $table->dropColumn('venue_id');
            $table->string('venue')->nullable();
            
            $table->dropColumn('has_visited_before');
            $table->dropColumn('address');
            $table->dropColumn('birth_date');
            $table->dropColumn('seijin_year');
            $table->dropColumn('referred_by_name');
            
            $table->dropColumn('considering_plans');
            $table->string('considering_plan')->nullable();
        });
    }
};

