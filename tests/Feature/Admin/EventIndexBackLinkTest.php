<?php

namespace Tests\Feature\Admin;

use App\Models\Event;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * イベント詳細の「イベント一覧に戻る」で、一覧の絞り込み・ページを保持する
 */
class EventIndexBackLinkTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Shop $shop;
    private Event $event;

    protected function setUp(): void
    {
        parent::setUp();

        $this->shop = Shop::create(['name' => '岡山店', 'is_active' => true]);
        $this->user = User::factory()->create(['attendance_role' => 'manager']);
        $this->user->shops()->attach($this->shop->id);

        $this->event = Event::create(['slug' => 'back-'.uniqid(), 'title' => '振袖展示会', 'form_type' => 'reservation', 'is_public' => true]);
        $this->event->shops()->attach($this->shop->id);
    }

    private function backUrlAfter(?array $indexQuery)
    {
        if ($indexQuery !== null) {
            $this->actingAs($this->user)->get(route('admin.events.index', $indexQuery))->assertOk();
        }

        return $this->actingAs($this->user)->get(route('admin.events.show', $this->event))->assertOk();
    }

    public function test_back_link_keeps_filters_and_page(): void
    {
        $this->backUrlAfter(['form_type' => 'reservation', 'shop_id' => $this->shop->id, 'public_status' => 'ended', 'page' => 2])
            ->assertInertia(fn (Assert $page) => $page->where('backUrl', route('admin.events.index', [
                'form_type' => 'reservation', 'shop_id' => $this->shop->id, 'public_status' => 'ended', 'page' => 2,
            ])));
    }

    public function test_all_status_is_kept_explicitly(): void
    {
        // 「すべて」はURLから省略されるが、戻り先では既定の「公開中」にならないよう明示する
        $this->backUrlAfter([])
            ->assertInertia(fn (Assert $page) => $page->where('backUrl', route('admin.events.index', ['public_status' => 'active'])));

        $this->backUrlAfter(['public_status' => 'all'])
            ->assertInertia(fn (Assert $page) => $page->where('backUrl', route('admin.events.index', ['public_status' => 'all'])));
    }

    public function test_without_visiting_index_back_link_is_plain_index(): void
    {
        $this->backUrlAfter(null)
            ->assertInertia(fn (Assert $page) => $page->where('backUrl', route('admin.events.index')));
    }
}
