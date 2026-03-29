<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if (in_array($driver, ['mysql', 'mariadb'], true)) {
            $this->mysqlUp();

            return;
        }

        if ($driver === 'pgsql') {
            $this->pgsqlUp();

            return;
        }

        if ($driver === 'sqlite') {
            throw new RuntimeException(
                'SQLite はこのマイグレーション未対応です。テスト・開発は MySQL または PostgreSQL を使用してください。'
            );
        }

        throw new RuntimeException('Unsupported DB driver for line reservation contacts migration: '.$driver);
    }

    public function down(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if (in_array($driver, ['mysql', 'mariadb'], true)) {
            $this->mysqlDown();

            return;
        }

        if ($driver === 'pgsql') {
            $this->pgsqlDown();

            return;
        }

        if ($driver === 'sqlite') {
            throw new RuntimeException('SQLite はこのマイグレーションのロールバック未対応です。');
        }

        throw new RuntimeException('Unsupported DB driver for line reservation contacts migration rollback: '.$driver);
    }

    private function mysqlUp(): void
    {
        $prefix = Schema::getConnection()->getTablePrefix();
        $contacts = $prefix.'customer_line_contacts';
        $tokens = $prefix.'customer_line_link_tokens';
        $customers = $prefix.'customers';
        $reservations = $prefix.'event_reservations';

        Schema::table('customer_line_contacts', function (Blueprint $table) {
            $table->dropForeign(['customer_id']);
        });
        DB::statement("ALTER TABLE `{$contacts}` MODIFY `customer_id` BIGINT UNSIGNED NULL");
        DB::statement("ALTER TABLE `{$contacts}` ADD CONSTRAINT `customer_line_contacts_customer_id_foreign` FOREIGN KEY (`customer_id`) REFERENCES `{$customers}` (`id`) ON DELETE SET NULL");

        Schema::table('customer_line_contacts', function (Blueprint $table) {
            $table->foreignId('event_reservation_id')
                ->nullable()
                ->after('customer_id')
                ->constrained('event_reservations')
                ->cascadeOnDelete();
            $table->unique('event_reservation_id');
        });

        Schema::table('customer_line_link_tokens', function (Blueprint $table) {
            $table->dropForeign(['customer_id']);
        });
        DB::statement("ALTER TABLE `{$tokens}` MODIFY `customer_id` BIGINT UNSIGNED NULL");
        DB::statement("ALTER TABLE `{$tokens}` ADD CONSTRAINT `customer_line_link_tokens_customer_id_foreign` FOREIGN KEY (`customer_id`) REFERENCES `{$customers}` (`id`) ON DELETE CASCADE");

        Schema::table('customer_line_link_tokens', function (Blueprint $table) {
            $table->foreignId('event_reservation_id')
                ->nullable()
                ->after('customer_id')
                ->constrained('event_reservations')
                ->cascadeOnDelete();
        });
    }

    private function mysqlDown(): void
    {
        $prefix = Schema::getConnection()->getTablePrefix();
        $contacts = $prefix.'customer_line_contacts';
        $tokens = $prefix.'customer_line_link_tokens';
        $customers = $prefix.'customers';

        Schema::table('customer_line_link_tokens', function (Blueprint $table) {
            $table->dropForeign(['event_reservation_id']);
            $table->dropColumn('event_reservation_id');
        });

        Schema::table('customer_line_link_tokens', function (Blueprint $table) {
            $table->dropForeign(['customer_id']);
        });
        DB::statement("ALTER TABLE `{$tokens}` MODIFY `customer_id` BIGINT UNSIGNED NOT NULL");
        DB::statement("ALTER TABLE `{$tokens}` ADD CONSTRAINT `customer_line_link_tokens_customer_id_foreign` FOREIGN KEY (`customer_id`) REFERENCES `{$customers}` (`id`) ON DELETE CASCADE");

        Schema::table('customer_line_contacts', function (Blueprint $table) {
            $table->dropForeign(['event_reservation_id']);
            $table->dropUnique(['event_reservation_id']);
            $table->dropColumn('event_reservation_id');
        });

        Schema::table('customer_line_contacts', function (Blueprint $table) {
            $table->dropForeign(['customer_id']);
        });
        DB::statement("ALTER TABLE `{$contacts}` MODIFY `customer_id` BIGINT UNSIGNED NOT NULL");
        DB::statement("ALTER TABLE `{$contacts}` ADD CONSTRAINT `customer_line_contacts_customer_id_foreign` FOREIGN KEY (`customer_id`) REFERENCES `{$customers}` (`id`) ON DELETE CASCADE");
    }

    private function pgsqlUp(): void
    {
        Schema::table('customer_line_contacts', function (Blueprint $table) {
            $table->dropForeign(['customer_id']);
        });
        DB::statement('ALTER TABLE customer_line_contacts ALTER COLUMN customer_id DROP NOT NULL');
        Schema::table('customer_line_contacts', function (Blueprint $table) {
            $table->foreign('customer_id')->references('id')->on('customers')->nullOnDelete();
        });

        Schema::table('customer_line_contacts', function (Blueprint $table) {
            $table->foreignId('event_reservation_id')
                ->nullable()
                ->constrained('event_reservations')
                ->cascadeOnDelete();
            $table->unique('event_reservation_id');
        });

        Schema::table('customer_line_link_tokens', function (Blueprint $table) {
            $table->dropForeign(['customer_id']);
        });
        DB::statement('ALTER TABLE customer_line_link_tokens ALTER COLUMN customer_id DROP NOT NULL');
        Schema::table('customer_line_link_tokens', function (Blueprint $table) {
            $table->foreign('customer_id')->references('id')->on('customers')->cascadeOnDelete();
        });

        Schema::table('customer_line_link_tokens', function (Blueprint $table) {
            $table->foreignId('event_reservation_id')
                ->nullable()
                ->constrained('event_reservations')
                ->cascadeOnDelete();
        });
    }

    private function pgsqlDown(): void
    {
        Schema::table('customer_line_link_tokens', function (Blueprint $table) {
            $table->dropForeign(['event_reservation_id']);
            $table->dropColumn('event_reservation_id');
        });

        Schema::table('customer_line_link_tokens', function (Blueprint $table) {
            $table->dropForeign(['customer_id']);
        });
        DB::statement('DELETE FROM customer_line_link_tokens WHERE customer_id IS NULL');
        DB::statement('ALTER TABLE customer_line_link_tokens ALTER COLUMN customer_id SET NOT NULL');
        Schema::table('customer_line_link_tokens', function (Blueprint $table) {
            $table->foreign('customer_id')->references('id')->on('customers')->cascadeOnDelete();
        });

        Schema::table('customer_line_contacts', function (Blueprint $table) {
            $table->dropForeign(['event_reservation_id']);
            $table->dropUnique(['event_reservation_id']);
            $table->dropColumn('event_reservation_id');
        });

        Schema::table('customer_line_contacts', function (Blueprint $table) {
            $table->dropForeign(['customer_id']);
        });
        DB::statement('DELETE FROM customer_line_contacts WHERE customer_id IS NULL');
        DB::statement('ALTER TABLE customer_line_contacts ALTER COLUMN customer_id SET NOT NULL');
        Schema::table('customer_line_contacts', function (Blueprint $table) {
            $table->foreign('customer_id')->references('id')->on('customers')->cascadeOnDelete();
        });
    }
};
