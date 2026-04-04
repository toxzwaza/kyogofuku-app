<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('event_reservations', function (Blueprint $table) {
            $table->date('graduation_ceremony_date')->nullable()->after('graduation_ceremony_month');
        });

        $rows = DB::table('event_reservations')
            ->whereNotNull('graduation_ceremony_year')
            ->whereNotNull('graduation_ceremony_month')
            ->whereNull('graduation_ceremony_date')
            ->get(['id', 'graduation_ceremony_year', 'graduation_ceremony_month']);

        foreach ($rows as $row) {
            $y = (int) $row->graduation_ceremony_year;
            $m = (int) $row->graduation_ceremony_month;
            if ($y < 2000 || $y > 2100 || $m < 1 || $m > 12) {
                continue;
            }
            $date = sprintf('%04d-%02d-01', $y, $m);
            DB::table('event_reservations')->where('id', $row->id)->update(['graduation_ceremony_date' => $date]);
        }
    }

    public function down(): void
    {
        Schema::table('event_reservations', function (Blueprint $table) {
            $table->dropColumn('graduation_ceremony_date');
        });
    }
};
