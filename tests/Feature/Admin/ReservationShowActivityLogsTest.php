<?php

namespace Tests\Feature\Admin;

use App\Http\Middleware\LogActivity;
use App\Models\ActivityLog;
use App\Models\Event;
use App\Models\EventReservation;
use App\Models\Shop;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ReservationShowActivityLogsTest extends TestCase
{
    use RefreshDatabase;

    public function test_show_includes_activity_logs_for_reservation_newest_first(): void
    {
        $user = User::factory()->create(['name' => 'Operator A']);
        $event = Event::create([
            'slug' => 'test-activity-logs-event',
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

        $reservation = EventReservation::create([
            'event_id' => $event->id,
            'name' => 'Customer',
            'email' => 'c@example.com',
            'phone' => '000',
        ]);

        $older = ActivityLog::create([
            'user_id' => $user->id,
            'shop_id' => null,
            'action_type' => 'view',
            'resource_type' => 'EventReservation',
            'resource_id' => $reservation->id,
            'route_name' => 'admin.reservations.show',
            'url' => 'https://example.test/admin/reservations/'.$reservation->id,
            'method' => 'GET',
            'description' => 'Older log',
        ]);
        $older->timestamps = false;
        $older->forceFill([
            'created_at' => Carbon::parse('2026-01-01 10:00:00'),
            'updated_at' => Carbon::parse('2026-01-01 10:00:00'),
        ])->save();

        $newer = ActivityLog::create([
            'user_id' => $user->id,
            'shop_id' => null,
            'action_type' => 'update',
            'resource_type' => 'EventReservation',
            'resource_id' => $reservation->id,
            'route_name' => 'admin.reservations.status.update',
            'url' => 'https://example.test/admin/reservations/'.$reservation->id.'/status',
            'method' => 'PATCH',
            'description' => 'Newer log',
        ]);
        $newer->timestamps = false;
        $newer->forceFill([
            'created_at' => Carbon::parse('2026-06-01 12:00:00'),
            'updated_at' => Carbon::parse('2026-06-01 12:00:00'),
        ])->save();

        $this->withoutMiddleware(LogActivity::class);

        $this->actingAs($user)
            ->get(route('admin.reservations.show', $reservation))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Reservation/Show')
                ->has('activity_logs', 2)
                ->where('activity_logs.0.description', 'Newer log')
                ->where('activity_logs.0.operator_name', 'Operator A')
                ->where('activity_logs.1.description', 'Older log'));
    }
}
