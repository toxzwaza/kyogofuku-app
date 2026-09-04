<?php

namespace Tests\Feature\Admin;

use App\Models\Customer;
use App\Models\CustomerNote;
use App\Models\Event;
use App\Models\EventReservation;
use App\Models\ReservationNote;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class NoteCrossReferenceTest extends TestCase
{
    use RefreshDatabase;

    private function createLinkedReservation(Customer $customer): EventReservation
    {
        $event = Event::create([
            'slug' => 'note-test-event',
            'title' => 'メモテストイベント',
            'form_type' => 'reservation',
            'is_public' => true,
        ]);

        return EventReservation::create([
            'event_id' => $event->id,
            'name' => '予約者',
            'email' => 'test@example.com',
            'phone' => '090-0000-0000',
            'customer_id' => $customer->id,
        ]);
    }

    public function test_customer_show_includes_linked_reservation_notes(): void
    {
        $user = User::factory()->create();
        $customer = Customer::create(['name' => '山田 花子']);
        $reservation = $this->createLinkedReservation($customer);

        ReservationNote::create([
            'user_id' => $user->id,
            'event_reservation_id' => $reservation->id,
            'content' => '予約時の相談内容メモ',
        ]);

        $this->actingAs($user)
            ->get(route('admin.customers.show', $customer))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('reservationNotes', 1)
                ->where('reservationNotes.0.content', '予約時の相談内容メモ')
                ->where('reservationNotes.0.user.name', $user->name)
                ->where('reservationNotes.0.reservation.id', $reservation->id)
            );
    }

    public function test_reservation_show_includes_customer_notes(): void
    {
        $user = User::factory()->create();
        $customer = Customer::create(['name' => '山田 花子']);
        $reservation = $this->createLinkedReservation($customer);

        CustomerNote::create([
            'user_id' => $user->id,
            'customer_id' => $customer->id,
            'content' => '顧客側で追記したメモ',
        ]);

        $this->actingAs($user)
            ->get(route('admin.reservations.show', $reservation))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('customerNotes', 1)
                ->where('customerNotes.0.content', '顧客側で追記したメモ')
                ->where('customerNotes.0.user.name', $user->name)
            );
    }

    public function test_reservation_show_without_customer_has_empty_customer_notes(): void
    {
        $user = User::factory()->create();
        $event = Event::create([
            'slug' => 'note-test-event2',
            'title' => 'メモテストイベント2',
            'form_type' => 'reservation',
            'is_public' => true,
        ]);
        $reservation = EventReservation::create([
            'event_id' => $event->id,
            'name' => '予約者',
            'email' => 'test@example.com',
            'phone' => '090-0000-0000',
        ]);

        $this->actingAs($user)
            ->get(route('admin.reservations.show', $reservation))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('customerNotes', 0)
            );
    }
}
