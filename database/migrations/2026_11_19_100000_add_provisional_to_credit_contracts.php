<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * クレジット契約の「仮登録」（担当の方に内容を確認中の契約）。
 * 確認が済んだら入力権限のある人が画面で内容を直し、仮登録を外す。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('credit_contracts', function (Blueprint $table) {
            $table->boolean('is_provisional')->default(false)->after('status')->comment('仮登録（内容を確認中）');
            $table->text('provisional_note')->nullable()->after('is_provisional')->comment('確認していること');
            $table->dateTime('confirmed_at')->nullable()->after('provisional_note')->comment('仮登録を外した日時');
            $table->foreignId('confirmed_by')->nullable()->after('confirmed_at')->constrained('users')->nullOnDelete();
            $table->index('is_provisional');
        });
    }

    public function down(): void
    {
        Schema::table('credit_contracts', function (Blueprint $table) {
            $table->dropConstrainedForeignId('confirmed_by');
            $table->dropIndex(['is_provisional']);
            $table->dropColumn(['is_provisional', 'provisional_note', 'confirmed_at']);
        });
    }
};
