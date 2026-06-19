<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('device_registrations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shop_id')->constrained('shops')->cascadeOnDelete();
            $table->string('device_code')->unique();          // 画面表示用の端末ID（非機密）
            $table->string('token_hash')->unique();           // localStorageトークンのSHA-256（生は保存しない）
            $table->string('label')->nullable();              // 端末名（任意）
            $table->string('ip_address')->nullable();         // 登録時IP
            $table->string('last_ip')->nullable();            // 最終アクセスIP
            $table->text('user_agent')->nullable();
            $table->foreignId('registered_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('revoked_at')->nullable();      // null=有効
            $table->foreignId('revoked_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['shop_id', 'revoked_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('device_registrations');
    }
};
