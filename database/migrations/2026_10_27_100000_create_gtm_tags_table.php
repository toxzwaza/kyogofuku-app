<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * GTMタグのマスタ化。
 * イベントは gtm_tag_id でマスタを選び、LP出力用に events.gtm_id へ値を写す（出力側は従来どおり gtm_id を参照）。
 * 既存の events.gtm_id は ID ごとにマスタへ登録し、イベントの開催店舗を紐付け店舗として引き継ぐ。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gtm_tags', function (Blueprint $table) {
            $table->id();
            $table->string('company_name')->nullable()->comment('管理会社');
            $table->string('gtm_id', 32)->unique();
            $table->text('memo')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('gtm_tag_shop', function (Blueprint $table) {
            $table->id();
            $table->foreignId('gtm_tag_id')->constrained()->onDelete('cascade');
            $table->foreignId('shop_id')->constrained()->onDelete('cascade');
            $table->timestamps();

            $table->unique(['gtm_tag_id', 'shop_id']);
        });

        Schema::table('events', function (Blueprint $table) {
            $table->foreignId('gtm_tag_id')->nullable()->after('gtm_id')->constrained('gtm_tags')->nullOnDelete();
        });

        // 既存の直入力値をマスタへ移す。GTM-形式でない値はマスタ化せず、イベントの gtm_id もそのまま残す
        DB::transaction(fn () => $this->migrateExistingGtmIds());
    }

    private function migrateExistingGtmIds(): void
    {
        $now = now();
        $events = DB::table('events')->whereNotNull('gtm_id')->where('gtm_id', '!=', '')->get(['id', 'gtm_id']);
        foreach ($events->groupBy(fn ($e) => strtoupper(trim($e->gtm_id))) as $gtmId => $rows) {
            if (! preg_match('/^GTM-[A-Z0-9]{4,12}$/', $gtmId)) {
                continue;
            }
            $tagId = DB::table('gtm_tags')->insertGetId([
                'gtm_id' => $gtmId,
                'memo' => '既存イベントから自動登録',
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $eventIds = $rows->pluck('id')->all();
            DB::table('events')->whereIn('id', $eventIds)->update(['gtm_tag_id' => $tagId, 'gtm_id' => $gtmId]);

            $shopIds = DB::table('event_shop')->whereIn('event_id', $eventIds)->distinct()->pluck('shop_id');
            DB::table('gtm_tag_shop')->insert($shopIds->map(fn ($shopId) => [
                'gtm_tag_id' => $tagId,
                'shop_id' => $shopId,
                'created_at' => $now,
                'updated_at' => $now,
            ])->all());
        }
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->dropConstrainedForeignId('gtm_tag_id');
        });
        Schema::dropIfExists('gtm_tag_shop');
        Schema::dropIfExists('gtm_tags');
    }
};
