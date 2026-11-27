<?php

namespace Tests\Feature\PhotoSlot;

use App\Models\Customer;
use App\Models\PhotoSlot;
use App\Models\PhotoStudio;
use App\Models\Shop;
use App\Models\StaffSchedule;
use App\Models\User;
use App\Services\GoogleCalendarSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * 前撮りの二重登録の防止：同じお客様は前撮り枠に1件まで。
 * もう登録があれば確認を出し、OK（replace_photo_slot_id 付き）なら新しい枠へ移して元の枠を空きに戻す。
 */
class PhotoSlotDuplicateTest extends TestCase
{
    use RefreshDatabase;

    private Shop $okayamaShop;
    private Shop $fukuiShop;
    private User $okayamaUser;
    private User $fukuiUser;
    private PhotoStudio $garden;   // 岡山
    private PhotoStudio $shisendo; // 福井
    private Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-11-19 09:00:00');
        $this->spy(GoogleCalendarSyncService::class);

        $this->okayamaShop = Shop::create(['name' => '岡山店', 'group_key' => 'okayama', 'is_active' => true]);
        $this->fukuiShop = Shop::create(['name' => '福井店', 'group_key' => 'fukui', 'is_active' => true]);
        $this->okayamaUser = User::factory()->create();
        $this->okayamaUser->shops()->attach($this->okayamaShop->id);
        $this->fukuiUser = User::factory()->create();
        $this->fukuiUser->shops()->attach($this->fukuiShop->id);

        $this->garden = PhotoStudio::create(['name' => '岡山ガーデン', 'shop_group' => 'okayama', 'is_active' => true]);
        $this->shisendo = PhotoStudio::create(['name' => '詩仙堂', 'shop_group' => 'fukui', 'is_active' => true]);
        foreach ([$this->garden, $this->shisendo] as $studio) {
            foreach (['08:30', '09:00'] as $time) {
                foreach ([1, 2] as $seat) {
                    PhotoSlot::create([
                        'photo_studio_id' => $studio->id, 'shoot_date' => '2026-12-05',
                        'shoot_time' => $time.':00', 'seat_no' => $seat,
                    ]);
                }
            }
        }
        $this->customer = Customer::create(['name' => '山田 花子']);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function seat(PhotoStudio $studio, string $time, int $seatNo = 1, string $date = '2026-12-05'): PhotoSlot
    {
        return PhotoSlot::where('photo_studio_id', $studio->id)->whereDate('shoot_date', $date)
            ->where('shoot_time', $time.':00')->where('seat_no', $seatNo)->firstOrFail();
    }

    /** 枠の表の「予約を入れる」（JSON） */
    private function bookFromGrid(User $user, PhotoStudio $studio, string $time, array $extra = [])
    {
        return $this->actingAs($user)->postJson(route('admin.photo-slots.book'), $extra + [
            'customer_id' => $this->customer->id,
            'photo_studio_id' => $studio->id,
            'shoot_date' => '2026-12-05',
            'shoot_time' => $time,
            'shop_id' => $studio->id === $this->garden->id ? $this->okayamaShop->id : $this->fukuiShop->id,
        ]);
    }

    /** お客様詳細の「前撮り情報を追加」（Inertia） */
    private function bookFromCustomer(User $user, PhotoStudio $studio, string $time, array $extra = [])
    {
        return $this->actingAs($user)->post(route('admin.customers.photo-slots.store', $this->customer), $extra + [
            'details_undecided' => false,
            'shop_id' => $studio->id === $this->garden->id ? $this->okayamaShop->id : $this->fukuiShop->id,
            'photo_studio_id' => $studio->id,
            'shoot_date' => '2026-12-05',
            'shoot_time' => $time,
        ]);
    }

    private function countFor(Customer $customer): int
    {
        return PhotoSlot::where('customer_id', $customer->id)->count();
    }

    public function test_枠の表から2件目は確認を返して入れず_OKなら新しい枠へ移して元の枠を空きに戻す_岡山(): void
    {
        $this->bookFromGrid($this->okayamaUser, $this->garden, '08:30')->assertOk();
        $first = $this->seat($this->garden, '08:30');

        $res = $this->bookFromGrid($this->okayamaUser, $this->garden, '09:00')->assertStatus(409);
        $res->assertJsonPath('duplicate.reason', 'confirm')
            ->assertJsonPath('duplicate.existing.0.id', $first->id)
            ->assertJsonPath('duplicate.existing.0.time', '08:30');
        $this->assertStringContainsString('8:30', $res->json('message'));
        $this->assertSame(1, $this->countFor($this->customer));
        $this->assertSame($this->customer->id, $first->fresh()->customer_id);

        // OK（今の登録の ID を付けて送り直す）
        $this->bookFromGrid($this->okayamaUser, $this->garden, '09:00', ['replace_photo_slot_id' => $first->id])->assertOk();
        $this->assertNull($first->fresh()->customer_id); // 元の枠は空き（席は残る）
        $this->assertSame($this->customer->id, $this->seat($this->garden, '09:00')->customer_id);
        $this->assertSame(1, $this->countFor($this->customer));
        // 予定も移る
        $this->assertNull(StaffSchedule::where('photo_slot_id', $first->id)->first());
        $this->assertSame('09:00', StaffSchedule::where('photo_slot_id', $this->seat($this->garden, '09:00')->id)->firstOrFail()->start_at->format('H:i'));
    }

    public function test_お客様詳細からも同じく止まり_OKなら移る_福井(): void
    {
        $this->bookFromCustomer($this->fukuiUser, $this->shisendo, '08:30')->assertSessionHasNoErrors();
        $first = $this->seat($this->shisendo, '08:30');

        $this->bookFromCustomer($this->fukuiUser, $this->shisendo, '09:00')->assertSessionHasErrors('duplicate');
        $this->assertSame(1, $this->countFor($this->customer));

        $this->bookFromCustomer($this->fukuiUser, $this->shisendo, '09:00', ['replace_photo_slot_id' => $first->id])
            ->assertSessionHasNoErrors();
        $this->assertNull($first->fresh()->customer_id);
        $this->assertSame($this->customer->id, $this->seat($this->shisendo, '09:00')->customer_id);
        $this->assertSame(1, $this->countFor($this->customer));
    }

    public function test_キャンセルで止めたときは何も変わらない_同じ時刻は確認なしで止める(): void
    {
        $this->bookFromGrid($this->okayamaUser, $this->garden, '08:30')->assertOk();
        $first = $this->seat($this->garden, '08:30');

        // 同じ時刻にもう一度
        $this->bookFromGrid($this->okayamaUser, $this->garden, '08:30')->assertStatus(409)
            ->assertJsonPath('duplicate.reason', 'same');
        $this->assertSame($this->customer->id, $first->fresh()->customer_id);
        $this->assertNull($this->seat($this->garden, '08:30', 2)->customer_id);
        $this->assertSame(1, StaffSchedule::count());
    }

    public function test_古い確認のまま送っても入らない(): void
    {
        // 画面Aで確認を出したあと、画面Bで別の枠へ移された → Aの「OK」は今の登録と違うので、もう一度確認になる
        $this->bookFromGrid($this->okayamaUser, $this->garden, '08:30')->assertOk();
        $first = $this->seat($this->garden, '08:30');
        $this->bookFromGrid($this->okayamaUser, $this->garden, '09:00', ['replace_photo_slot_id' => $first->id])->assertOk();
        $second = $this->seat($this->garden, '09:00');

        $this->bookFromGrid($this->okayamaUser, $this->garden, '08:30', ['replace_photo_slot_id' => $first->id])
            ->assertStatus(409)->assertJsonPath('duplicate.existing.0.id', $second->id);
        $this->assertSame(1, $this->countFor($this->customer));
        $this->assertSame($this->customer->id, $second->fresh()->customer_id);
    }

    public function test_同じお客様を2つの画面から同時に入れても_2件目は入らない(): void
    {
        // どちらの画面もまだ登録がない状態で開いた（どちらも replace なしで送る）
        $this->bookFromGrid($this->okayamaUser, $this->garden, '08:30')->assertOk();
        $this->bookFromCustomer($this->okayamaUser, $this->garden, '09:00')->assertSessionHasErrors('duplicate');
        $this->assertSame(1, $this->countFor($this->customer));
    }

    public function test_日時未定の人を枠に入れるときも確認し_OKなら未定の行を消して枠に入れる(): void
    {
        $this->actingAs($this->okayamaUser)->post(route('admin.customers.photo-slots.store', $this->customer), [
            'details_undecided' => true, 'shop_id' => $this->okayamaShop->id,
        ])->assertSessionHasNoErrors();
        $undecided = PhotoSlot::where('customer_id', $this->customer->id)->firstOrFail();

        $res = $this->bookFromGrid($this->okayamaUser, $this->garden, '09:00')->assertStatus(409);
        $res->assertJsonPath('duplicate.existing.0.undecided', true);
        $this->assertStringContainsString('日時未定', $res->json('message'));

        $this->bookFromGrid($this->okayamaUser, $this->garden, '09:00', ['replace_photo_slot_id' => $undecided->id])->assertOk();
        $this->assertNull(PhotoSlot::find($undecided->id));
        $this->assertSame($this->customer->id, $this->seat($this->garden, '09:00')->customer_id);
        $this->assertSame(1, $this->countFor($this->customer));
    }

    public function test_枠に入っている人に日時未定を足すときも確認し_OKなら枠を空きに戻して未定にする(): void
    {
        $this->bookFromCustomer($this->okayamaUser, $this->garden, '08:30')->assertSessionHasNoErrors();
        $first = $this->seat($this->garden, '08:30');
        $undecided = ['details_undecided' => true, 'shop_id' => $this->okayamaShop->id];

        $this->actingAs($this->okayamaUser)->post(route('admin.customers.photo-slots.store', $this->customer), $undecided)
            ->assertSessionHasErrors('duplicate');
        $this->assertSame(1, $this->countFor($this->customer));

        $this->actingAs($this->okayamaUser)->post(route('admin.customers.photo-slots.store', $this->customer), $undecided + ['replace_photo_slot_id' => $first->id])
            ->assertSessionHasNoErrors();
        $this->assertNull($first->fresh()->customer_id);
        $this->assertTrue(PhotoSlot::where('customer_id', $this->customer->id)->firstOrFail()->details_undecided);
        $this->assertSame(1, $this->countFor($this->customer));
        $this->assertSame(0, StaffSchedule::count());
    }

    public function test_取り消した予約は登録済みに数えない(): void
    {
        $this->bookFromGrid($this->okayamaUser, $this->garden, '08:30')->assertOk();
        $first = $this->seat($this->garden, '08:30');
        $this->actingAs($this->okayamaUser)->patch(route('admin.photo-slots.release-customer', $first))->assertSessionHasNoErrors();

        $this->bookFromGrid($this->okayamaUser, $this->garden, '09:00')->assertOk();
        $this->assertSame(1, $this->countFor($this->customer));
    }

    public function test_撮影日の過ぎた枠は登録済みに数えない(): void
    {
        // 前の年の前撮り（撮影済み）が残っている人
        $past = PhotoSlot::create([
            'photo_studio_id' => $this->garden->id, 'shoot_date' => '2025-12-06', 'shoot_time' => '09:00:00',
            'seat_no' => 1, 'customer_id' => $this->customer->id,
        ]);
        $this->bookFromGrid($this->okayamaUser, $this->garden, '09:00')->assertOk();
        $this->assertSame($this->customer->id, $past->fresh()->customer_id); // 撮影済みは触らない
        $this->assertSame(2, $this->countFor($this->customer));
    }

    public function test_撮影済みの枠を今日以降へ移して二重にはできない(): void
    {
        $past = PhotoSlot::create([
            'photo_studio_id' => $this->garden->id, 'shoot_date' => '2025-12-06', 'shoot_time' => '09:00:00',
            'seat_no' => 1, 'customer_id' => $this->customer->id,
        ]);
        $this->bookFromGrid($this->okayamaUser, $this->garden, '08:30')->assertOk();

        $this->actingAs($this->okayamaUser)->put(route('admin.customers.photo-slots.update', [$this->customer, $past]), [
            'details_undecided' => false, 'shop_id' => $this->okayamaShop->id,
            'photo_studio_id' => $this->garden->id, 'shoot_date' => '2026-12-05', 'shoot_time' => '09:00',
        ])->assertSessionHasErrors('duplicate');
        $this->assertSame($this->customer->id, $past->fresh()->customer_id);
        $this->assertNull($this->seat($this->garden, '09:00')->customer_id);
    }

    public function test_前からの二重登録は消さず_新しい登録は止め_今の枠の移動はできる(): void
    {
        // 本番にあり得る形：今日以降の登録が2件
        $a = $this->seat($this->garden, '08:30');
        $b = $this->seat($this->garden, '09:00');
        $a->update(['customer_id' => $this->customer->id]);
        $b->update(['customer_id' => $this->customer->id]);

        $res = $this->bookFromGrid($this->okayamaUser, $this->garden, '09:00', ['replace_photo_slot_id' => $a->id])->assertStatus(409);
        $res->assertJsonPath('duplicate.reason', 'multiple');
        $this->assertCount(2, $res->json('duplicate.existing'));
        $this->assertSame($this->customer->id, $a->fresh()->customer_id);
        $this->assertSame($this->customer->id, $b->fresh()->customer_id);

        // 今の枠の移動（お客様詳細の編集）はこれまでどおりできる
        $this->actingAs($this->okayamaUser)->put(route('admin.customers.photo-slots.update', [$this->customer, $a]), [
            'details_undecided' => false, 'shop_id' => $this->okayamaShop->id,
            'photo_studio_id' => $this->garden->id, 'shoot_date' => '2026-12-05', 'shoot_time' => '09:00',
        ])->assertSessionHasNoErrors();
        $this->assertNull($a->fresh()->customer_id);
        $this->assertSame($this->customer->id, $this->seat($this->garden, '09:00', 2)->customer_id);
        $this->assertSame(2, $this->countFor($this->customer));
    }

    public function test_ほかの店舗グループの登録は確認を出さずに止め_見えない枠は動かさない(): void
    {
        // 福井の枠に入っている人を、岡山の人が岡山の枠に入れようとする
        $this->bookFromGrid($this->fukuiUser, $this->shisendo, '08:30')->assertOk();
        $fukui = $this->seat($this->shisendo, '08:30');

        $res = $this->bookFromGrid($this->okayamaUser, $this->garden, '09:00', ['replace_photo_slot_id' => $fukui->id])->assertStatus(409);
        $res->assertJsonPath('duplicate.reason', 'other_group')->assertJsonPath('duplicate.existing', []);
        $this->assertStringNotContainsString('詩仙堂', $res->json('message')); // 見えない枠の中身は出さない
        $this->assertSame($this->customer->id, $fukui->fresh()->customer_id);
        $this->assertNull($this->seat($this->garden, '09:00')->customer_id);
    }
}
