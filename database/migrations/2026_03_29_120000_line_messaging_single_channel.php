<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // MySQL: shop_id の FK が (shop_id, line_user_id) の UNIQUE をインデックスとして使うため、
        // 先に FK を外してから UNIQUE を削除する。
        Schema::table('customer_line_contacts', function (Blueprint $table) {
            $table->dropForeign(['shop_id']);
        });

        Schema::table('customer_line_contacts', function (Blueprint $table) {
            $table->dropUnique(['shop_id', 'line_user_id']);
        });

        Schema::table('customer_line_contacts', function (Blueprint $table) {
            $table->unique('line_user_id');
        });

        Schema::table('customer_line_contacts', function (Blueprint $table) {
            $table->foreign('shop_id')->references('id')->on('shops')->cascadeOnDelete();
        });

        Schema::table('line_unknown_inbound_messages', function (Blueprint $table) {
            $table->dropForeign(['shop_id']);
        });

        $this->setLineUnknownShopIdNullable(true);

        if (Schema::getConnection()->getDriverName() !== 'sqlite') {
            Schema::table('line_unknown_inbound_messages', function (Blueprint $table) {
                $table->foreign('shop_id')->references('id')->on('shops')->nullOnDelete();
            });
        }

        Schema::table('shops', function (Blueprint $table) {
            $table->dropColumn([
                'line_messaging_channel_id',
                'line_messaging_channel_secret',
                'line_messaging_channel_access_token',
                'line_liff_id',
            ]);
        });
    }

    public function down(): void
    {
        Schema::table('shops', function (Blueprint $table) {
            $table->string('line_messaging_channel_id')->nullable()->after('line_group_id');
            $table->text('line_messaging_channel_secret')->nullable()->after('line_messaging_channel_id');
            $table->text('line_messaging_channel_access_token')->nullable()->after('line_messaging_channel_secret');
            $table->string('line_liff_id')->nullable()->after('line_messaging_channel_access_token');
        });

        Schema::table('line_unknown_inbound_messages', function (Blueprint $table) {
            $table->dropForeign(['shop_id']);
        });

        $this->setLineUnknownShopIdNullable(false);

        if (Schema::getConnection()->getDriverName() !== 'sqlite') {
            Schema::table('line_unknown_inbound_messages', function (Blueprint $table) {
                $table->foreign('shop_id')->references('id')->on('shops')->cascadeOnDelete();
            });
        }

        Schema::table('customer_line_contacts', function (Blueprint $table) {
            $table->dropForeign(['shop_id']);
        });

        Schema::table('customer_line_contacts', function (Blueprint $table) {
            $table->dropUnique(['line_user_id']);
        });

        Schema::table('customer_line_contacts', function (Blueprint $table) {
            $table->unique(['shop_id', 'line_user_id']);
        });

        Schema::table('customer_line_contacts', function (Blueprint $table) {
            $table->foreign('shop_id')->references('id')->on('shops')->cascadeOnDelete();
        });
    }

    /**
     * Doctrine DBAL の ->change() は Laravel 9 + DBAL 4 の組み合わせで致命的エラーになるため、
     * ドライバ別に列定義を変える（MySQL は MODIFY、SQLite はテーブル再作成）。
     */
    private function setLineUnknownShopIdNullable(bool $nullable): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if (in_array($driver, ['mysql', 'mariadb'], true)) {
            $table = Schema::getConnection()->getTablePrefix().'line_unknown_inbound_messages';
            $null = $nullable ? 'NULL' : 'NOT NULL';
            DB::statement("ALTER TABLE `{$table}` MODIFY `shop_id` BIGINT UNSIGNED {$null}");

            return;
        }

        if ($driver === 'pgsql') {
            if ($nullable) {
                DB::statement('ALTER TABLE line_unknown_inbound_messages ALTER COLUMN shop_id DROP NOT NULL');
            } else {
                DB::statement('ALTER TABLE line_unknown_inbound_messages ALTER COLUMN shop_id SET NOT NULL');
            }

            return;
        }

        if ($driver === 'sqlite') {
            $this->recreateSqliteLineUnknownInboundMessages($nullable);

            return;
        }

        throw new \RuntimeException(
            'line_unknown_inbound_messages の shop_id 変更: 未対応の DB ドライバです: '.$driver
        );
    }

    private function recreateSqliteLineUnknownInboundMessages(bool $shopIdNullable): void
    {
        Schema::drop('line_unknown_inbound_messages');

        Schema::create('line_unknown_inbound_messages', function (Blueprint $table) use ($shopIdNullable) {
            $table->id();
            if ($shopIdNullable) {
                $table->foreignId('shop_id')->nullable()->constrained('shops')->nullOnDelete();
            } else {
                $table->foreignId('shop_id')->constrained()->cascadeOnDelete();
            }
            $table->string('line_user_id');
            $table->text('text')->nullable();
            $table->string('line_message_id')->nullable()->unique();
            $table->json('raw_event')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['shop_id', 'line_user_id']);
        });
    }
};
