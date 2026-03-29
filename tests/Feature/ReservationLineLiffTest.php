<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\CustomerLineContact;
use App\Models\CustomerLineLinkToken;
use App\Models\Event;
use App\Models\EventReservation;
use App\Models\Shop;
use App\Services\Line\ReservationLineContactMigrator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ReservationLineLiffTest extends TestCase
{
    use RefreshDatabase;

    public function test_liff_complete_creates_reservation_contact_with_label_honjin(): void
    {
        Http::fake([
            'api.line.me/oauth2/v2.1/verify' => Http::response(['sub' => 'Uresv1'], 200),
            'api.line.me/v2/bot/message/push' => Http::response([], 200),
        ]);

        config(['line.liff.login_channel_id' => 'login-ch']);
        config(['line.messaging.channel_access_token' => 'test-token']);
        config(['line.link_welcome_text' => '']);

        $shop = Shop::create([
            'name' => 'S',
            'is_active' => true,
        ]);
        $event = Event::create([
            'slug' => 't-'.uniqid('', true),
            'title' => 'E',
            'form_type' => 'reservation',
        ]);
        $event->shops()->attach($shop->id);

        $reservation = EventReservation::create([
            'event_id' => $event->id,
            'name' => '予約者',
            'email' => 'r@example.com',
            'phone' => '000',
        ]);

        $tokenStr = CustomerLineLinkToken::generateToken();
        CustomerLineLinkToken::query()->create([
            'token' => $tokenStr,
            'customer_id' => null,
            'event_reservation_id' => $reservation->id,
            'shop_id' => $shop->id,
            'suggested_label' => '本人',
            'expires_at' => now()->addDays(7),
            'created_by_user_id' => null,
        ]);

        $this->postJson(route('line.liff.complete'), [
            'id_token' => 'dummy-token',
            'link_token' => $tokenStr,
            'label' => '勝手な名前',
        ])->assertOk();

        $contact = CustomerLineContact::query()->where('line_user_id', 'Uresv1')->first();
        $this->assertNotNull($contact);
        $this->assertNull($contact->customer_id);
        $this->assertSame((int) $reservation->id, (int) $contact->event_reservation_id);
        $this->assertSame('本人', $contact->label);
    }

    public function test_migrator_moves_contact_to_customer_on_link(): void
    {
        $shop = Shop::create(['name' => 'S', 'is_active' => true]);
        $event = Event::create([
            'slug' => 't-'.uniqid('', true),
            'title' => 'E',
            'form_type' => 'reservation',
        ]);
        $event->shops()->attach($shop->id);

        $reservation = EventReservation::create([
            'event_id' => $event->id,
            'name' => '予約者',
            'email' => 'r@example.com',
            'phone' => '000',
        ]);

        $customer = Customer::create([
            'name' => '顧客化',
            'shop_id' => $shop->id,
        ]);

        $contact = CustomerLineContact::query()->create([
            'customer_id' => null,
            'event_reservation_id' => $reservation->id,
            'shop_id' => $shop->id,
            'line_user_id' => 'Umig',
            'label' => '本人',
        ]);

        $reservation->update(['customer_id' => $customer->id]);

        $result = app(ReservationLineContactMigrator::class)
            ->migrateReservationContactsToCustomer($reservation->fresh(), $customer);

        $this->assertTrue($result['ok']);
        $contact->refresh();
        $this->assertSame((int) $customer->id, (int) $contact->customer_id);
        $this->assertNull($contact->event_reservation_id);
    }
}
