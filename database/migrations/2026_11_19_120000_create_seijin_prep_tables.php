<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 成人式お支度の枠（docs/20261119_成人式お支度_設計.md）。
 * 前撮り（photo_studios・photo_slots）とは別の表。考え方は同じで、1行＝枠の席1つ＝お客様1人まで。
 *
 * seijin_prep_slots の1行は次のどれか：
 *  - 時刻の枠の席：場所・日・時刻・席番号あり（customer_id が空なら空き席）
 *  - 場所だけの登録：場所あり・日時なし（時刻が決まる前の申し込み）
 *  - 他店お支度／未定：場所なし
 * お客様1人につき1行まで（customer_id の一意制約。空き席は customer_id が空なので何行でもよい）。
 */
return new class extends Migration
{
    public function up()
    {
        Schema::create('seijin_prep_venues', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('kind', 20)->default('own')->comment('own=自社会場／salon=提携美容室');
            $table->string('shop_group', 50)->nullable()->comment('店舗グループ（okayama/fukui）。見える範囲を絞る');
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->string('address')->nullable();
            $table->string('phone', 50)->nullable();
            $table->text('remarks')->nullable();
            $table->time('default_start_time')->nullable()->comment('枠をまとめて作るときの最初の時刻');
            $table->time('default_end_time')->nullable()->comment('最後の枠が終わる時刻');
            $table->unsignedTinyInteger('default_capacity')->default(1)->comment('既定の定員（レーン数）');
            $table->unsignedSmallInteger('slot_interval_minutes')->default(30)->comment('枠の刻み（分）');
            $table->timestamps();
        });

        Schema::create('seijin_prep_slots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('seijin_prep_venue_id')->nullable()->constrained('seijin_prep_venues')->restrictOnDelete();
            $table->date('prep_date')->nullable()->comment('お支度の日（成人式の当日）');
            $table->time('prep_time')->nullable()->comment('お支度の時刻');
            $table->unsignedTinyInteger('seat_no')->default(1)->comment('席番号（レーン）。1〜定員');
            $table->foreignId('customer_id')->nullable()->unique()->constrained('customers')->nullOnDelete();
            $table->boolean('is_other_store')->default(false)->comment('他店お支度');
            $table->string('preferred_time', 50)->nullable()->comment('お客様の希望時刻');
            $table->text('remarks')->nullable();
            $table->foreignId('shop_id')->nullable()->constrained('shops')->nullOnDelete()->comment('担当店舗');
            $table->timestamps();

            $table->unique(['seijin_prep_venue_id', 'prep_date', 'prep_time', 'seat_no'], 'uq_seijin_prep_seat');
            $table->index(['prep_date', 'prep_time']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('seijin_prep_slots');
        Schema::dropIfExists('seijin_prep_venues');
    }
};
