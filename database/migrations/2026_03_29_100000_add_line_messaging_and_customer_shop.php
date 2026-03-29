<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->foreignId('shop_id')
                ->nullable()
                ->after('id')
                ->constrained('shops')
                ->nullOnDelete();
        });

        Schema::table('shops', function (Blueprint $table) {
            $table->string('line_messaging_channel_id')->nullable()->after('line_group_id')
                ->comment('Messaging API チャネルID（IDトークン検証の client_id 兼用）');
            $table->text('line_messaging_channel_secret')->nullable()->after('line_messaging_channel_id');
            $table->text('line_messaging_channel_access_token')->nullable()->after('line_messaging_channel_secret');
            $table->string('line_liff_id')->nullable()->after('line_messaging_channel_access_token')
                ->comment('顧客連携用 LIFF アプリID');
        });

        Schema::create('customer_line_contacts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('shop_id')->constrained()->cascadeOnDelete();
            $table->string('line_user_id');
            $table->string('label')->default('お客様');
            $table->timestamps();

            $table->unique(['shop_id', 'line_user_id']);
        });

        Schema::create('customer_line_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_line_contact_id')->constrained()->cascadeOnDelete();
            $table->string('direction', 16);
            $table->string('message_type', 32)->default('text');
            $table->text('text')->nullable();
            $table->string('line_message_id')->nullable()->unique();
            $table->json('payload')->nullable();
            $table->foreignId('sent_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('customer_line_link_tokens', function (Blueprint $table) {
            $table->id();
            $table->uuid('token')->unique();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('shop_id')->constrained()->cascadeOnDelete();
            $table->timestamp('expires_at');
            $table->timestamp('used_at')->nullable();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('line_unknown_inbound_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shop_id')->constrained()->cascadeOnDelete();
            $table->string('line_user_id');
            $table->text('text')->nullable();
            $table->string('line_message_id')->nullable()->unique();
            $table->json('raw_event')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['shop_id', 'line_user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('line_unknown_inbound_messages');
        Schema::dropIfExists('customer_line_link_tokens');
        Schema::dropIfExists('customer_line_messages');
        Schema::dropIfExists('customer_line_contacts');

        Schema::table('shops', function (Blueprint $table) {
            $table->dropColumn([
                'line_messaging_channel_id',
                'line_messaging_channel_secret',
                'line_messaging_channel_access_token',
                'line_liff_id',
            ]);
        });

        Schema::table('customers', function (Blueprint $table) {
            $table->dropConstrainedForeignId('shop_id');
        });
    }
};
