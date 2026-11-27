<?php

namespace Tests\Feature\SeijinPrep;

use App\Models\CeremonyArea;
use App\Models\Contract;
use App\Models\Customer;
use App\Models\Plan;
use App\Models\SeijinPrepSlot;
use App\Models\SeijinPrepVenue;
use App\Models\Shop;
use App\Models\User;
use App\Services\GoogleCalendarSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * 成人式お支度（福井）：時刻なしの「日＋番号」の枠・メイク有無・希望時刻を入力しない・美容室ごとの CSV・既存データの移し替え。
 * 岡山（時刻の枠）の動きが変わらないことも確かめる。
 */
class SeijinPrepFukuiTest extends TestCase
{
    use RefreshDatabase;

    private User $fukuiUser;
    private User $okayamaUser;
    private Shop $fukuiShop;
    private Shop $okayamaShop;
    private SeijinPrepVenue $salon;
    private SeijinPrepVenue $plaza;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-11-27 09:00:00');
        $this->spy(GoogleCalendarSyncService::class);

        $this->fukuiShop = Shop::create(['name' => '福井店', 'group_key' => 'fukui', 'is_active' => true]);
        $this->okayamaShop = Shop::create(['name' => '岡山店', 'group_key' => 'okayama', 'is_active' => true]);
        $this->fukuiUser = User::factory()->create();
        $this->fukuiUser->shops()->attach($this->fukuiShop->id);
        $this->okayamaUser = User::factory()->create();
        $this->okayamaUser->shops()->attach($this->okayamaShop->id);

        $this->salon = SeijinPrepVenue::create(['name' => '福井の美容室', 'kind' => 'salon', 'shop_group' => 'fukui']);
        $this->plaza = SeijinPrepVenue::create(['name' => '岡山プラザホテル', 'kind' => 'own', 'shop_group' => 'okayama', 'slot_interval_minutes' => 20]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function bulk(int $capacity, string $date = '2027-01-10')
    {
        return $this->actingAs($this->fukuiUser)->postJson(route('admin.seijin-prep-slots.bulk'), [
            'seijin_prep_venue_id' => $this->salon->id, 'prep_date' => $date, 'capacity' => $capacity,
        ]);
    }

    private function saveFor(Customer $c, array $data, ?User $user = null)
    {
        return $this->actingAs($user ?? $this->fukuiUser)->putJson(route('admin.customers.seijin-prep.save', $c), $data);
    }

    private function book(Customer $c, ?bool $makeup = true, string $date = '2027-01-10')
    {
        return $this->saveFor($c, ['mode' => 'venue', 'venue_id' => $this->salon->id, 'prep_date' => $date, 'has_makeup' => $makeup]);
    }

    /** 番号の順に並べたお客様の名前（空きは null） */
    private function order(string $date = '2027-01-10'): array
    {
        return SeijinPrepSlot::with('customer')->where('seijin_prep_venue_id', $this->salon->id)
            ->whereDate('prep_date', $date)->whereNull('prep_time')->orderBy('seat_no')->get()
            ->map(fn ($s) => $s->customer?->name)->all();
    }

    public function test_福井の場所は日と枠数だけで番号枠を作り_時刻はいらない(): void
    {
        $this->bulk(3)->assertOk();

        $rows = SeijinPrepSlot::where('seijin_prep_venue_id', $this->salon->id)->orderBy('seat_no')->get();
        $this->assertSame([1, 2, 3], $rows->pluck('seat_no')->all());
        $this->assertTrue($rows->every(fn ($r) => $r->prep_time === null && $r->isNumberedSeat()));

        // もう枠のある日には作れない（枠数は定員の変更で変える）
        $this->bulk(2)->assertStatus(422)->assertJsonValidationErrors('prep_date');
        // 上限は福井の上限（岡山の 20 ではない）
        $this->bulk(SeijinPrepVenue::MAX_NUMBERED_CAPACITY, '2027-01-11')->assertOk();
        $this->bulk(SeijinPrepVenue::MAX_NUMBERED_CAPACITY + 1, '2027-01-12')->assertStatus(422)->assertJsonValidationErrors('capacity');
    }

    public function test_入った順に番号が付き_取り消すと後ろが詰まり_新しい人は最後に入る(): void
    {
        $this->bulk(3)->assertOk();
        [$a, $b, $c, $d] = [Customer::create(['name' => 'A']), Customer::create(['name' => 'B']), Customer::create(['name' => 'C']), Customer::create(['name' => 'D'])];

        $this->book($a)->assertOk();
        $this->book($b)->assertOk();
        $this->book($c)->assertOk();
        $this->assertSame(['A', 'B', 'C'], $this->order());

        // 満員
        $this->book($d)->assertStatus(422)->assertJsonValidationErrors('prep_date');

        // A を取り消すと B・C が 1・2 に詰まり、空きは 3
        $this->actingAs($this->fukuiUser)->deleteJson(route('admin.customers.seijin-prep.destroy', $a))->assertOk();
        $this->assertSame(['B', 'C', null], $this->order());

        // 新しい人は最後（3）に入る
        $this->book($d)->assertOk();
        $this->assertSame(['B', 'C', 'D'], $this->order());

        // 同じ日のまま保存し直しても番号は変わらない
        $this->book($b, false)->assertOk();
        $this->assertSame(['B', 'C', 'D'], $this->order());
        $this->assertFalse(SeijinPrepSlot::where('customer_id', $b->id)->first()->has_makeup);
    }

    public function test_別の日へ移すと元の日の番号が詰まる(): void
    {
        $this->bulk(2, '2027-01-10')->assertOk();
        $this->bulk(2, '2027-01-11')->assertOk();
        [$a, $b] = [Customer::create(['name' => 'A']), Customer::create(['name' => 'B'])];
        $this->book($a)->assertOk();
        $this->book($b)->assertOk();

        $this->book($a, true, '2027-01-11')->assertOk();
        $this->assertSame(['B', null], $this->order('2027-01-10'));
        $this->assertSame(['A', null], $this->order('2027-01-11'));
    }

    public function test_空き枠を消す_枠数を減らすと番号が詰まる(): void
    {
        $this->bulk(4)->assertOk();
        [$a, $b] = [Customer::create(['name' => 'A']), Customer::create(['name' => 'B'])];
        $this->book($a)->assertOk();
        $this->book($b)->assertOk();
        $target = ['seijin_prep_venue_id' => $this->salon->id, 'prep_date' => '2027-01-10'];

        // 枠数：時刻なしで変えられる。入っている人より少なくはできない
        $this->actingAs($this->fukuiUser)->putJson(route('admin.seijin-prep-slots.capacity'), $target + ['capacity' => 1])
            ->assertStatus(422)->assertJsonValidationErrors('capacity');
        $this->actingAs($this->fukuiUser)->putJson(route('admin.seijin-prep-slots.capacity'), $target + ['capacity' => 3])->assertOk();
        $this->assertSame(['A', 'B', null], $this->order());

        // 1枠足す（時刻なし）
        $this->actingAs($this->fukuiUser)->postJson(route('admin.seijin-prep-slots.seats.store'), $target)->assertOk();
        $this->assertSame(['A', 'B', null, null], $this->order());

        // A を取り消して B が 1 になったあと、空き枠を1つ消しても番号は 1..n のまま
        $this->actingAs($this->fukuiUser)->deleteJson(route('admin.customers.seijin-prep.destroy', $a))->assertOk();
        $vacant = SeijinPrepSlot::where('seijin_prep_venue_id', $this->salon->id)->whereNull('customer_id')->orderBy('seat_no')->first();
        $this->actingAs($this->fukuiUser)->deleteJson(route('admin.seijin-prep-slots.destroy', $vacant))->assertOk();
        $this->assertSame(['B', null, null], $this->order());
        $this->assertSame([1, 2, 3], SeijinPrepSlot::where('seijin_prep_venue_id', $this->salon->id)->orderBy('seat_no')->pluck('seat_no')->all());
    }

    public function test_メイク有無は福井の場所では必須_ほかでは聞かない(): void
    {
        $this->bulk(2)->assertOk();
        $c = Customer::create(['name' => 'A']);

        // 未選択（null・送らない）はエラー
        $this->book($c, null)->assertStatus(422)->assertJsonValidationErrors('has_makeup');
        $this->saveFor($c, ['mode' => 'venue', 'venue_id' => $this->salon->id])->assertStatus(422)->assertJsonValidationErrors('has_makeup');
        $this->assertSame(0, SeijinPrepSlot::whereNotNull('customer_id')->count());

        $this->book($c, false)->assertOk();
        $this->assertFalse(SeijinPrepSlot::where('customer_id', $c->id)->first()->has_makeup);

        // 月の表から入れるときも必須
        $d = Customer::create(['name' => 'D']);
        $this->actingAs($this->fukuiUser)->postJson(route('admin.seijin-prep-slots.book'), [
            'customer_id' => $d->id, 'seijin_prep_venue_id' => $this->salon->id, 'prep_date' => '2027-01-10',
        ])->assertStatus(422)->assertJsonValidationErrors('has_makeup');
        $this->actingAs($this->fukuiUser)->postJson(route('admin.seijin-prep-slots.book'), [
            'customer_id' => $d->id, 'seijin_prep_venue_id' => $this->salon->id, 'prep_date' => '2027-01-10', 'has_makeup' => true,
        ])->assertOk();
        $this->assertSame(['A', 'D'], $this->order());

        // 他店・未定・岡山では聞かない（送っても入れない）
        $e = Customer::create(['name' => 'E']);
        $this->saveFor($e, ['mode' => 'other', 'has_makeup' => true, 'shop_id' => $this->fukuiShop->id])->assertOk();
        $this->assertNull(SeijinPrepSlot::where('customer_id', $e->id)->first()->has_makeup);
        $f = Customer::create(['name' => 'F']);
        SeijinPrepSlot::create(['seijin_prep_venue_id' => $this->plaza->id, 'prep_date' => '2027-01-10', 'prep_time' => '08:00:00', 'seat_no' => 1]);
        $this->saveFor($f, ['mode' => 'venue', 'venue_id' => $this->plaza->id, 'prep_date' => '2027-01-10', 'prep_time' => '08:00', 'has_makeup' => true], $this->okayamaUser)->assertOk();
        $this->assertNull(SeijinPrepSlot::where('customer_id', $f->id)->first()->has_makeup);
    }

    public function test_福井では希望時刻を入力できず_今の値は残る_岡山では今までどおり(): void
    {
        $this->bulk(1)->assertOk();
        $c = Customer::create(['name' => 'A']);
        // 前の登録の希望時刻（移行前のデータなど）
        SeijinPrepSlot::create(['customer_id' => $c->id, 'seijin_prep_venue_id' => $this->salon->id, 'seat_no' => 1, 'preferred_time' => '7時台']);

        $this->saveFor($c, ['mode' => 'venue', 'venue_id' => $this->salon->id, 'prep_date' => '2027-01-10', 'has_makeup' => true, 'preferred_time' => '書き換え'])->assertOk();
        $row = SeijinPrepSlot::where('customer_id', $c->id)->first();
        $this->assertSame('7時台', $row->preferred_time);
        $this->assertSame(1, $row->seat_no);

        // 他店・未定でも、担当店舗が福井なら入力しない
        $d = Customer::create(['name' => 'D']);
        $this->saveFor($d, ['mode' => 'undecided', 'shop_id' => $this->fukuiShop->id, 'preferred_time' => '朝'])->assertOk();
        $this->assertNull(SeijinPrepSlot::where('customer_id', $d->id)->first()->preferred_time);

        // 岡山は今までどおり入る
        $e = Customer::create(['name' => 'E']);
        $this->saveFor($e, ['mode' => 'venue', 'venue_id' => $this->plaza->id, 'preferred_time' => '朝早め'], $this->okayamaUser)->assertOk();
        $this->assertSame('朝早め', SeijinPrepSlot::where('customer_id', $e->id)->first()->preferred_time);
    }

    public function test_日の空きは番号枠を1つで返し_表は番号の行に出る(): void
    {
        $this->bulk(3)->assertOk();
        $a = Customer::create(['name' => 'A']);
        $this->book($a)->assertOk();

        $this->actingAs($this->fukuiUser)->getJson(route('admin.customers.seijin-prep.times', $a).'?venue_id='.$this->salon->id.'&prep_date=2027-01-10')
            ->assertOk()->assertExactJson(['times' => [['time' => null, 'capacity' => 3, 'booked' => 1, 'mine' => true]]]);

        $res = $this->actingAs($this->fukuiUser)->getJson(route('admin.seijin-prep-slots.grid', ['studio_ids' => [$this->salon->id], 'month' => '2027-01']))->assertOk();
        $this->assertSame(['番号'], $res->json('times'));
        $cell = $res->json('cells')['2027-01-10|'.$this->salon->id]['番号'];
        $this->assertSame(3, $cell['capacity']);
        $this->assertSame(1, $cell['booked']);
        $this->assertSame([1, 2, 3], array_column($cell['seats'], 'no'));
        $this->assertSame('メイクあり', $cell['seats'][0]['plan']);
        $this->assertTrue($res->json('studios.0.numbered'));

        $this->actingAs($this->fukuiUser)->getJson(route('admin.customers.seijin-prep.show', $a))
            ->assertOk()->assertJsonPath('prep.numbered', true)->assertJsonPath('prep.has_makeup', true)
            ->assertJsonPath('prep.label', '福井の美容室・2027-01-10・番号1');
    }

    public function test_福井のCSVは美容室に渡す列_岡山のCSVは今までの列(): void
    {
        $this->bulk(2)->assertOk();
        $area = CeremonyArea::create(['name' => '福井市 1月', 'prefecture' => '福井県']);
        $rental = Plan::create(['name' => 'レンタル', 'code' => 'r']);
        $mama = Plan::create(['name' => 'ママ振(小物レンタル)', 'code' => 'm']);
        $a = Customer::create(['name' => '福井 花子', 'kana' => 'ふくい はなこ', 'phone_number' => '0776-00-0000', 'postal_code' => '910-0000', 'address' => '福井市1-1', 'ceremony_area_id' => $area->id]);
        Contract::create(['customer_id' => $a->id, 'shop_id' => $this->fukuiShop->id, 'plan_id' => $rental->id, 'contract_date' => '2025-05-01', 'kimono_type' => '振袖', 'total_amount' => 110000, 'status' => '確定']);
        Contract::create(['customer_id' => $a->id, 'shop_id' => $this->fukuiShop->id, 'plan_id' => $mama->id, 'contract_date' => '2025-08-01', 'kimono_type' => '振袖', 'total_amount' => 110000, 'status' => '確定']); // 最新
        $this->book($a, false)->assertOk();
        SeijinPrepSlot::where('customer_id', $a->id)->update(['original_prep_time' => '07:40:00']);

        $csv = $this->actingAs($this->fukuiUser)->get(route('admin.seijin-prep-slots.csv', ['venue_id' => $this->salon->id]))->assertOk()->streamedContent();
        $lines = array_map('str_getcsv', explode("\n", trim(substr($csv, 3))));
        $this->assertSame(['お支度場所', '日', '番号', 'お客様', 'ふりがな', '電話', '郵便番号', '住所', '種別', 'メイク有無', '出る会場', '担当店舗', '備考', '元の時刻'], $lines[0]);
        $this->assertSame(['福井の美容室', '2027-01-10', '1', '福井 花子', 'ふくい はなこ', '0776-00-0000', '910-0000', '福井市1-1', 'ママ振(小物レンタル)', 'なし', '福井市 1月', '', '', '07:40'], $lines[1]);
        $this->assertCount(2, $lines); // 空き枠は出さない

        // 岡山の CSV（場所ごと・全部）は今までの列のまま
        $okHeader = ['お支度場所', '日', '時刻', 'レーン', 'お客様', 'ふりがな', '電話', '担当店舗', '他店お支度', '希望時刻', '備考'];
        SeijinPrepSlot::create(['seijin_prep_venue_id' => $this->plaza->id, 'prep_date' => '2027-01-10', 'prep_time' => '08:00:00', 'seat_no' => 1, 'customer_id' => Customer::create(['name' => '岡山 太郎'])->id]);
        foreach ([['venue_id' => $this->plaza->id], []] as $q) {
            $csv = $this->actingAs($this->okayamaUser)->get(route('admin.seijin-prep-slots.csv', $q))->assertOk()->streamedContent();
            $lines = array_map('str_getcsv', explode("\n", trim(substr($csv, 3))));
            $this->assertSame($okHeader, $lines[0]);
            $this->assertSame('08:00', $lines[1][2]);
        }

        // 福井の人の「全部」CSV は、今までの列にメイク有無を足す
        $csv = $this->actingAs($this->fukuiUser)->get(route('admin.seijin-prep-slots.csv'))->assertOk()->streamedContent();
        $lines = array_map('str_getcsv', explode("\n", trim(substr($csv, 3))));
        $this->assertSame(array_merge($okHeader, ['メイク有無']), $lines[0]);
        $this->assertSame(['番号1', 'なし'], [$lines[1][2], $lines[1][11]]);
    }

    public function test_時刻の枠のある場所は_岡山と福井のあいだで店舗グループを変えられない(): void
    {
        SeijinPrepSlot::create(['seijin_prep_venue_id' => $this->plaza->id, 'prep_date' => '2027-01-10', 'prep_time' => '08:00:00', 'seat_no' => 1]);
        $admin = User::factory()->create();
        $admin->shops()->attach([$this->okayamaShop->id, $this->fukuiShop->id]);

        $this->actingAs($admin)->put(route('admin.seijin-prep-venues.update', $this->plaza), [
            'name' => $this->plaza->name, 'kind' => 'own', 'shop_group' => 'fukui', 'is_active' => true,
            'slot_interval_minutes' => 20, 'default_capacity' => 1,
        ])->assertSessionHasErrors('shop_group');
        $this->assertSame('okayama', $this->plaza->fresh()->shop_group);
    }

    public function test_マイグレーション_福井の時刻の枠を更新日時の順で番号枠に移し_戻せる(): void
    {
        // 列の追加・削除（DDL）は試験のトランザクションを切るので、データの移し替えと戻しだけを呼ぶ
        $migration = require database_path('migrations/2026_11_27_120000_seijin_prep_fukui_numbered_slots.php');

        $a = Customer::create(['name' => 'A']);
        $b = Customer::create(['name' => 'B']);
        $c = Customer::create(['name' => 'C']);
        $ins = fn (array $r) => DB::table('seijin_prep_slots')->insertGetId($r + ['seijin_prep_venue_id' => $this->salon->id, 'created_at' => now(), 'is_other_store' => false]);
        // 時刻は B が早いが、枠に入ったのは A が先
        $idA = $ins(['prep_date' => '2027-01-10', 'prep_time' => '09:00:00', 'seat_no' => 1, 'customer_id' => $a->id, 'preferred_time' => '9時', 'updated_at' => '2026-11-01 10:00:00']);
        $idB = $ins(['prep_date' => '2027-01-10', 'prep_time' => '07:00:00', 'seat_no' => 1, 'customer_id' => $b->id, 'updated_at' => '2026-11-02 10:00:00']);
        $idV = $ins(['prep_date' => '2027-01-10', 'prep_time' => '07:00:00', 'seat_no' => 2, 'customer_id' => null, 'updated_at' => '2026-10-01 10:00:00']);
        $idC = $ins(['prep_date' => '2027-01-11', 'prep_time' => '08:00:00', 'seat_no' => 1, 'customer_id' => $c->id, 'updated_at' => '2026-11-03 10:00:00']);
        $idU = $ins(['prep_date' => null, 'prep_time' => null, 'seat_no' => 1, 'customer_id' => Customer::create(['name' => 'U'])->id, 'updated_at' => '2026-11-03 10:00:00']);
        // 岡山は変えない
        $idO = DB::table('seijin_prep_slots')->insertGetId(['seijin_prep_venue_id' => $this->plaza->id, 'prep_date' => '2027-01-10', 'prep_time' => '08:00:00', 'seat_no' => 1, 'customer_id' => null, 'is_other_store' => false, 'created_at' => now(), 'updated_at' => now()]);

        $migration->moveToNumbered();

        $row = fn ($id) => DB::table('seijin_prep_slots')->find($id);
        $this->assertSame([null, 1, '09:00:00', '9時'], [$row($idA)->prep_time, (int) $row($idA)->seat_no, $row($idA)->original_prep_time, $row($idA)->preferred_time]);
        $this->assertSame([null, 2, '07:00:00'], [$row($idB)->prep_time, (int) $row($idB)->seat_no, $row($idB)->original_prep_time]);
        $this->assertSame([null, 3, null], [$row($idV)->prep_time, (int) $row($idV)->seat_no, $row($idV)->original_prep_time]);
        $this->assertSame([null, 1], [$row($idC)->prep_time, (int) $row($idC)->seat_no]);
        $this->assertNull($row($idU)->prep_date); // 場所だけの登録はそのまま
        $this->assertSame(['08:00:00', 1], [$row($idO)->prep_time, (int) $row($idO)->seat_no]);
        $this->assertSame(4, DB::table('seijin_prep_slots_backup_20261127')->count());

        // 移したあとはアプリから番号枠として使える
        $this->assertSame(['A', 'B', null], $this->order());

        // 番号枠になってから入った人（空きの3番）
        $n = Customer::create(['name' => 'N']);
        $this->book($n)->assertOk();

        $migration->restoreFromBackup();
        $this->assertSame(['2027-01-10', '09:00:00', 1], [$row($idA)->prep_date, $row($idA)->prep_time, (int) $row($idA)->seat_no]);
        $this->assertSame(['07:00:00', 1], [$row($idB)->prep_time, (int) $row($idB)->seat_no]);
        // 空きだった席に入った N は、元の席（07:00 の2）に戻る（行は同じ）
        $this->assertSame(['07:00:00', 2, $n->id], [$row($idV)->prep_time, (int) $row($idV)->seat_no, $row($idV)->customer_id]);
        // 番号枠になってから足した空き枠は消える
        $this->assertSame(0, DB::table('seijin_prep_slots')->where('seijin_prep_venue_id', $this->salon->id)->whereNotNull('prep_date')->whereNull('prep_time')->count());
    }
}
