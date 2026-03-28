<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('event_reservations', function (Blueprint $table) {
            $table->string('entrance_ticket_send_status', 32)
                ->default('未送付')
                ->after('admin_assignee');
        });
    }

    public function down(): void
    {
        Schema::table('event_reservations', function (Blueprint $table) {
            $table->dropColumn('entrance_ticket_send_status');
        });
    }
};
