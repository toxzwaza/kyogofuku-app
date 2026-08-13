<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('media_files', function (Blueprint $table) {
            // iPad アプリ等、登録端末からアップロードされた場合の送信元端末。
            // 管理画面からのアップロードは null のまま。
            $table->foreignId('device_registration_id')
                ->nullable()
                ->after('tags')
                ->constrained('device_registrations')
                ->nullOnDelete();

            // アップロード元ファイル（変換前）の SHA-256。端末アップロードの重複排除に使う。
            $table->char('sha256', 64)->nullable()->after('device_registration_id')->index();
        });
    }

    public function down(): void
    {
        Schema::table('media_files', function (Blueprint $table) {
            $table->dropConstrainedForeignId('device_registration_id');
            $table->dropIndex(['sha256']);
            $table->dropColumn('sha256');
        });
    }
};
