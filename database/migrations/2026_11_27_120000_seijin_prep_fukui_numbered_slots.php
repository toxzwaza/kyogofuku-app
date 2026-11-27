<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * 成人式お支度：福井は時刻で管理せず「日＋番号（1, 2, 3…）」の枠にする（11/21 打ち合わせ・taskboard 前撮り機能作成-2）。
 *
 * - 列を足す：has_makeup（メイク有無。福井の場所では必須。空＝未選択）、original_prep_time（番号枠に移す前の時刻。表示用）
 * - 福井の場所（shop_group=fukui）の時刻付きの席を、場所×日ごとの番号枠に移す
 *   - 番号枠の行は prep_date あり・prep_time なし・seat_no＝番号
 *   - 入っている人を先に、空き席を後ろにして 1 から番号を振る。入っている人の順は ORDER_BY（下）
 *   - 定員（その日の席の数）はそのまま。お客様・希望時刻・備考・担当店舗も変えない
 *   - 入っていた席の時刻は original_prep_time に残す
 * - 戻すとき（down）のために、移す前の行を seijin_prep_slots_backup_20261127 に写しておく
 */
return new class extends Migration
{
    private const BACKUP = 'seijin_prep_slots_backup_20261127';

    public function up()
    {
        Schema::table('seijin_prep_slots', function (Blueprint $table) {
            $table->boolean('has_makeup')->nullable()->after('is_other_store')->comment('メイク有無（福井）。null＝未選択');
            $table->time('original_prep_time')->nullable()->after('prep_time')->comment('番号枠に移す前の時刻（福井の移行）');
        });

        Schema::create(self::BACKUP, function (Blueprint $table) {
            $table->unsignedBigInteger('id')->primary();
            $table->unsignedBigInteger('seijin_prep_venue_id')->nullable();
            $table->date('prep_date')->nullable();
            $table->time('prep_time')->nullable();
            $table->unsignedTinyInteger('seat_no');
            $table->unsignedBigInteger('customer_id')->nullable();
            $table->timestamp('updated_at')->nullable();
        });

        $this->moveToNumbered();
    }

    /** 福井の時刻付きの席を写しに残してから、番号枠に移す（表の形は変えない。試験からも呼ぶ） */
    public function moveToNumbered(): void
    {
        $venueIds = DB::table('seijin_prep_venues')->where('shop_group', 'fukui')->pluck('id');
        $rows = DB::table('seijin_prep_slots')
            ->whereIn('seijin_prep_venue_id', $venueIds)
            ->whereNotNull('prep_date')
            ->whereNotNull('prep_time')
            ->get();
        if ($rows->isEmpty()) {
            return;
        }

        DB::transaction(function () use ($rows) {
            foreach ($rows->chunk(500) as $chunk) {
                DB::table(self::BACKUP)->insert($chunk->map(fn ($r) => [
                    'id' => $r->id,
                    'seijin_prep_venue_id' => $r->seijin_prep_venue_id,
                    'prep_date' => $r->prep_date,
                    'prep_time' => $r->prep_time,
                    'seat_no' => $r->seat_no,
                    'customer_id' => $r->customer_id,
                    'updated_at' => $r->updated_at,
                ])->values()->all());
            }

            foreach ($rows->groupBy(fn ($r) => $r->seijin_prep_venue_id.'|'.$r->prep_date) as $group) {
                $booked = $group->whereNotNull('customer_id')->sortBy(fn ($r) => self::orderKey($r))->values();
                $vacant = $group->whereNull('customer_id')->sortBy(fn ($r) => [$r->prep_time, $r->seat_no])->values();
                $no = 0;
                foreach ($booked->concat($vacant) as $r) {
                    $no++;
                    // prep_time を空にした行は一意制約（場所・日・時刻・席）に掛からないので、番号を振り直しても重ならない
                    DB::table('seijin_prep_slots')->where('id', $r->id)->update([
                        'original_prep_time' => $r->customer_id ? $r->prep_time : null,
                        'prep_time' => null,
                        'seat_no' => $no,
                    ]);
                }
            }
        });
    }

    /** 入っている人の番号の順（若い番号＝早く成約した人）：枠に入った日時（行の更新日時）→ 元の時刻 → 元の席 */
    private static function orderKey(object $r): array
    {
        return [(string) $r->updated_at, $r->prep_time, $r->seat_no, $r->id];
    }

    public function down()
    {
        if (Schema::hasTable(self::BACKUP)) {
            $this->restoreFromBackup();
            Schema::drop(self::BACKUP);
        }

        Schema::table('seijin_prep_slots', function (Blueprint $table) {
            $table->dropColumn(['has_makeup', 'original_prep_time']);
        });
    }

    /**
     * 番号枠を写しの席（日・時刻・席）に戻す（表の形は変えない。試験からも呼ぶ）。
     * 番号枠になってから足した空き枠は消し、そこに入った人は「場所だけ」の登録にする
     */
    public function restoreFromBackup(): void
    {
        $venueIds = DB::table('seijin_prep_venues')->where('shop_group', 'fukui')->pluck('id');
        DB::transaction(function () use ($venueIds) {
            $backup = DB::table(self::BACKUP)->get()->keyBy('id');
            $numbered = DB::table('seijin_prep_slots')
                ->whereIn('seijin_prep_venue_id', $venueIds)
                ->whereNotNull('prep_date')
                ->whereNull('prep_time')
                ->get();
            foreach ($numbered as $r) {
                $b = $backup->get($r->id);
                if ($b) {
                    // 移す前の席に戻す（お客様は今の値のまま）
                    DB::table('seijin_prep_slots')->where('id', $r->id)->update([
                        'prep_date' => $b->prep_date, 'prep_time' => $b->prep_time, 'seat_no' => $b->seat_no,
                    ]);
                } elseif ($r->customer_id) {
                    DB::table('seijin_prep_slots')->where('id', $r->id)->update(['prep_date' => null, 'seat_no' => 1]);
                } else {
                    DB::table('seijin_prep_slots')->where('id', $r->id)->delete();
                }
            }
        });
    }
};
