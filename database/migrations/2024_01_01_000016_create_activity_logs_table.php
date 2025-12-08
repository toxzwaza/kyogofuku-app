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
        Schema::create('activity_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->onDelete('set null');
            $table->foreignId('shop_id')->nullable()->constrained()->onDelete('set null');
            $table->string('action_type'); // 'view', 'create', 'update', 'delete', 'login', 'logout' など
            $table->string('resource_type')->nullable(); // 'Event', 'Reservation', 'Shop', 'User' など
            $table->unsignedBigInteger('resource_id')->nullable(); // リソースのID
            $table->string('route_name')->nullable(); // ルート名
            $table->string('url'); // アクセスしたURL
            $table->string('method'); // HTTPメソッド
            $table->text('description')->nullable(); // 処理の説明
            $table->json('old_values')->nullable(); // 更新前の値（更新時のみ）
            $table->json('new_values')->nullable(); // 更新後の値（更新時のみ）
            $table->string('ip_address')->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
            $table->index(['shop_id', 'created_at']);
            $table->index(['action_type', 'created_at']);
            $table->index(['resource_type', 'resource_id']);
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('activity_logs');
    }
};

