<?php

namespace Tests\Feature\Admin;

use App\Models\Event;
use App\Models\EventReservation;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReservationAssigneeTest extends TestCase
{
    use RefreshDatabase;

    public function test_update_assignee_persists_and_trims_empty_to_null(): void
    {
        $user = User::factory()->create();
        $event = Event::create([
            'slug' => 'test-assignee-event',
            'title' => 'Test',
            'description' => null,
            'form_type' => 'contact',
            'start_at' => null,
            'end_at' => null,
            'is_public' => true,
        ]);
        $shop = Shop::create([
            'name' => 'Test Shop',
            'address' => null,
            'phone' => null,
            'is_active' => true,
        ]);
        $event->shops()->attach($shop->id);

        $staff = User::factory()->create(['name' => 'Staff Tanaka']);
        $staff->shops()->attach($shop->id);

        $reservation = EventReservation::create([
            'event_id' => $event->id,
            'name' => 'Customer',
            'email' => 'c@example.com',
            'phone' => '000',
        ]);

        $this->actingAs($user)
            ->patch(route('admin.reservations.assignee.update', $reservation), [
                'admin_assignee' => '岡F',
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $reservation->refresh();
        $this->assertSame('岡F', $reservation->admin_assignee);

        $this->actingAs($user)
            ->patch(route('admin.reservations.assignee.update', $reservation), [
                'admin_assignee' => '   ',
            ])
            ->assertRedirect();

        $reservation->refresh();
        $this->assertNull($reservation->admin_assignee);
    }
}
