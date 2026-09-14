<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\CustomerPhoto;
use App\Models\CustomerQuestionnaire;
use App\Models\Event;
use App\Models\EventReservation;
use App\Models\PhotoType;
use App\Models\Shop;
use App\Services\ReservationAssetMigrator;
use App\Support\PhotoOwner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReservationAssetMigratorTest extends TestCase
{
    use RefreshDatabase;

    private Shop $shop;

    private Event $event;

    protected function setUp(): void
    {
        parent::setUp();
        $this->shop = Shop::create(['name' => 'テスト店舗', 'is_active' => true]);
        $this->event = Event::create([
            'title' => 'テストイベント',
            'slug' => 'test-event-'.uniqid(),
            'form_type' => 'furisode',
            'is_public' => true,
        ]);
    }

    private function makeReservation(): EventReservation
    {
        return EventReservation::create([
            'event_id' => $this->event->id,
            'name' => '予約者A',
            'email' => 'test@example.com',
            'phone' => '090-0000-0000',
        ]);
    }

    private function makeCustomer(): Customer
    {
        return Customer::create([
            'name' => '顧客A',
            'shop_id' => $this->shop->id,
        ]);
    }

    private function photoTypeId(): int
    {
        return PhotoType::firstOrCreate(['code' => 'other'], ['name' => 'その他', 'is_active' => true, 'sort_order' => 99])->id;
    }

    public function test_photos_and_questionnaire_migrate_to_customer(): void
    {
        $reservation = $this->makeReservation();
        $customer = $this->makeCustomer();

        $photo = CustomerPhoto::create([
            'event_reservation_id' => $reservation->id,
            'photo_type_id' => $this->photoTypeId(),
            'file_path' => 'reservations/'.$reservation->id.'/a.webp',
            'storage_disk' => 's3',
        ]);
        $questionnaire = CustomerQuestionnaire::create([
            'event_reservation_id' => $reservation->id,
        ]);

        $result = app(ReservationAssetMigrator::class)->migrate($reservation, $customer);

        $this->assertSame(1, $result['photos']);
        $this->assertSame('migrated', $result['questionnaire']);

        $photo->refresh();
        $this->assertSame($customer->id, $photo->customer_id);
        $this->assertNull($photo->event_reservation_id);

        $questionnaire->refresh();
        $this->assertSame($customer->id, $questionnaire->customer_id);
        $this->assertNull($questionnaire->event_reservation_id);
    }

    public function test_customer_questionnaire_is_kept_when_both_exist(): void
    {
        $reservation = $this->makeReservation();
        $customer = $this->makeCustomer();

        // 予約側アンケート（スキャン写真付き）
        $scanPhoto = CustomerPhoto::create([
            'event_reservation_id' => $reservation->id,
            'photo_type_id' => $this->photoTypeId(),
            'file_path' => 'reservations/'.$reservation->id.'/scan.webp',
            'storage_disk' => 's3',
        ]);
        $normalPhoto = CustomerPhoto::create([
            'event_reservation_id' => $reservation->id,
            'photo_type_id' => $this->photoTypeId(),
            'file_path' => 'reservations/'.$reservation->id.'/b.webp',
            'storage_disk' => 's3',
        ]);
        $reservationQuestionnaire = CustomerQuestionnaire::create([
            'event_reservation_id' => $reservation->id,
            'page1_photo_id' => $scanPhoto->id,
        ]);

        // 顧客側にも既存アンケート
        $customerQuestionnaire = CustomerQuestionnaire::create([
            'customer_id' => $customer->id,
        ]);

        $result = app(ReservationAssetMigrator::class)->migrate($reservation, $customer);

        // 通常写真のみ移行され、予約側アンケートとそのスキャン写真は予約に残る
        $this->assertSame(1, $result['photos']);
        $this->assertSame('kept_customer', $result['questionnaire']);

        $this->assertSame($customer->id, $normalPhoto->fresh()->customer_id);
        $this->assertSame($reservation->id, $scanPhoto->fresh()->event_reservation_id);
        $this->assertSame($reservation->id, $reservationQuestionnaire->fresh()->event_reservation_id);
        $this->assertSame($customer->id, $customerQuestionnaire->fresh()->customer_id);
    }

    public function test_photo_owner_resolves_customer_for_linked_reservation(): void
    {
        $reservation = $this->makeReservation();
        $customer = $this->makeCustomer();

        $owner = PhotoOwner::forReservation($reservation);
        $this->assertFalse($owner->isCustomer());
        $this->assertSame(['event_reservation_id' => $reservation->id], $owner->attrs());
        $this->assertSame('reservations', $owner->dir());

        $reservation->update(['customer_id' => $customer->id]);
        $reservation->refresh();

        $owner = PhotoOwner::forReservation($reservation);
        $this->assertTrue($owner->isCustomer());
        $this->assertSame(['customer_id' => $customer->id], $owner->attrs());
        $this->assertSame('customers', $owner->dir());
    }
}
