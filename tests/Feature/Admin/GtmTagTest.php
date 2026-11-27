<?php

namespace Tests\Feature\Admin;

use App\Models\Event;
use App\Models\GtmTag;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class GtmTagTest extends TestCase
{
    use RefreshDatabase;

    private Shop $shop;

    private function user(?string $role = 'manager'): User
    {
        $user = User::factory()->create(['attendance_role' => $role]);
        $this->shop ??= Shop::create(['name' => 'テスト店', 'is_active' => true]);
        $user->shops()->attach($this->shop->id);

        return $user;
    }

    private function event(array $attrs = []): Event
    {
        return Event::create(array_merge([
            'slug' => 'gtm-'.uniqid(),
            'title' => 'テスト',
            'form_type' => 'reservation',
            'is_public' => true,
        ], $attrs));
    }

    public function test_manager_can_create_tag_with_normalized_id_and_shops(): void
    {
        $user = $this->user();

        $this->actingAs($user)->post(route('admin.gtm-tags.store'), [
            'company_name' => 'BE FANG',
            'gtm_id' => ' gtm-abc1234 ',
            'memo' => 'LP計測用',
            'is_active' => true,
            'shop_ids' => [$this->shop->id],
        ])->assertRedirect(route('admin.gtm-tags.index'));

        $tag = GtmTag::firstOrFail();
        $this->assertSame('GTM-ABC1234', $tag->gtm_id);
        $this->assertSame([$this->shop->id], $tag->shops->pluck('id')->all());
    }

    public function test_invalid_or_duplicate_gtm_id_is_rejected(): void
    {
        $user = $this->user();
        GtmTag::create(['gtm_id' => 'GTM-DUP1234']);

        $this->actingAs($user)->post(route('admin.gtm-tags.store'), ['gtm_id' => 'UA-12345'])
            ->assertSessionHasErrors('gtm_id');
        $this->actingAs($user)->post(route('admin.gtm-tags.store'), ['gtm_id' => 'gtm-dup1234'])
            ->assertSessionHasErrors('gtm_id');
        $this->assertSame(1, GtmTag::count());
    }

    public function test_non_manager_cannot_manage_tags(): void
    {
        $user = $this->user(null);

        $this->actingAs($user)->get(route('admin.gtm-tags.index'))->assertForbidden();
        $this->actingAs($user)->post(route('admin.gtm-tags.store'), ['gtm_id' => 'GTM-ABC1234'])->assertForbidden();
    }

    public function test_event_update_copies_gtm_id_from_selected_tag(): void
    {
        $user = $this->user();
        $tag = GtmTag::create(['gtm_id' => 'GTM-SEL1234']);
        $event = $this->event(['gtm_id' => 'GTM-OLD0000']);

        $this->actingAs($user)->put(route('admin.events.update', $event), [
            'title' => 'テスト',
            'slug' => $event->slug,
            'form_type' => 'reservation',
            'is_public' => true,
            'gtm_tag_id' => $tag->id,
        ])->assertRedirect();

        $event->refresh();
        $this->assertSame($tag->id, $event->gtm_tag_id);
        $this->assertSame('GTM-SEL1234', $event->gtm_id);

        // 「設定しない」に戻すとLP出力値も消える
        $this->actingAs($user)->put(route('admin.events.update', $event), [
            'title' => 'テスト',
            'slug' => $event->slug,
            'form_type' => 'reservation',
            'is_public' => true,
            'gtm_tag_id' => null,
        ])->assertRedirect();

        $event->refresh();
        $this->assertNull($event->gtm_tag_id);
        $this->assertNull($event->gtm_id);
    }

    public function test_changing_tag_id_updates_linked_events(): void
    {
        $user = $this->user();
        $tag = GtmTag::create(['gtm_id' => 'GTM-BEFORE1']);
        $linked = $this->event(['gtm_tag_id' => $tag->id, 'gtm_id' => 'GTM-BEFORE1']);
        $other = $this->event(['gtm_id' => 'GTM-OTHER11']);

        $this->actingAs($user)->put(route('admin.gtm-tags.update', $tag), [
            'gtm_id' => 'GTM-AFTER11',
            'is_active' => true,
        ])->assertRedirect(route('admin.gtm-tags.index'));

        $this->assertSame('GTM-AFTER11', $linked->fresh()->gtm_id);
        $this->assertSame('GTM-OTHER11', $other->fresh()->gtm_id);
    }

    public function test_update_without_gtm_tag_id_keeps_existing_gtm_id(): void
    {
        $user = $this->user();
        $tag = GtmTag::create(['gtm_id' => 'GTM-KEEP123']);
        $event = $this->event(['gtm_tag_id' => $tag->id, 'gtm_id' => 'GTM-KEEP123']);

        // 旧画面のように gtm_tag_id を送らない保存
        $this->actingAs($user)->put(route('admin.events.update', $event), [
            'title' => '変更後',
            'slug' => $event->slug,
            'form_type' => 'reservation',
            'is_public' => true,
            'gtm_id' => 'GTM-OLDFORM',
        ])->assertRedirect();

        $event->refresh();
        $this->assertSame('変更後', $event->title);
        $this->assertSame($tag->id, $event->gtm_tag_id);
        $this->assertSame('GTM-KEEP123', $event->gtm_id);
    }

    public function test_changing_tag_id_also_fills_linked_events_with_null_gtm_id(): void
    {
        $user = $this->user();
        $tag = GtmTag::create(['gtm_id' => 'GTM-BEFORE2']);
        $linked = $this->event(['gtm_tag_id' => $tag->id, 'gtm_id' => null]);

        $this->actingAs($user)->put(route('admin.gtm-tags.update', $tag), [
            'gtm_id' => 'GTM-AFTER22',
            'is_active' => true,
        ])->assertRedirect(route('admin.gtm-tags.index'));

        $this->assertSame('GTM-AFTER22', $linked->fresh()->gtm_id);
    }

    public function test_options_include_inactive_tags_with_flag(): void
    {
        GtmTag::create(['gtm_id' => 'GTM-ACTIVE1']);
        GtmTag::create(['gtm_id' => 'GTM-STOPPED', 'is_active' => false]);

        $flags = GtmTag::options()->pluck('is_active', 'gtm_id')->all();
        $this->assertSame(['GTM-ACTIVE1' => true, 'GTM-STOPPED' => false], $flags);
    }

    public function test_tag_in_use_cannot_be_deleted(): void
    {
        $user = $this->user();
        $tag = GtmTag::create(['gtm_id' => 'GTM-INUSE12']);
        $this->event(['gtm_tag_id' => $tag->id, 'gtm_id' => 'GTM-INUSE12']);

        $this->actingAs($user)->delete(route('admin.gtm-tags.destroy', $tag))
            ->assertSessionHas('error');
        $this->assertNotNull($tag->fresh());

        $unused = GtmTag::create(['gtm_id' => 'GTM-UNUSED1']);
        $this->actingAs($user)->delete(route('admin.gtm-tags.destroy', $unused))
            ->assertSessionHas('success');
        $this->assertNull($unused->fresh());
    }

    public function test_manager_can_save_management_fields(): void
    {
        $user = $this->user();

        $this->actingAs($user)->post(route('admin.gtm-tags.store'), [
            'company_name' => 'BE FANG',
            'contact_person' => '山田',
            'contact' => 'yamada@example.com',
            'gtm_id' => 'GTM-MGMT123',
            'kind' => 'site',
            'purpose' => 'HPの広告',
            'used_on' => 'https://example.com',
            'confidence' => '確認済',
            'confirmed_at' => '2026-11-28',
            'is_active' => true,
        ])->assertRedirect(route('admin.gtm-tags.index'));

        $tag = GtmTag::where('gtm_id', 'GTM-MGMT123')->firstOrFail();
        $this->assertSame('site', $tag->kind);
        $this->assertSame('山田', $tag->contact_person);
        $this->assertSame('確認済', $tag->confidence);
        $this->assertSame('2026-11-28', $tag->confirmed_at->format('Y-m-d'));
        $this->assertFalse($tag->isUnconfirmed());
    }

    public function test_kind_defaults_to_event_and_invalid_values_are_rejected(): void
    {
        $user = $this->user();

        $this->actingAs($user)->post(route('admin.gtm-tags.store'), ['gtm_id' => 'GTM-DEFKIND'])
            ->assertSessionHasNoErrors();
        $this->assertSame('event', GtmTag::where('gtm_id', 'GTM-DEFKIND')->value('kind'));

        $this->actingAs($user)->post(route('admin.gtm-tags.store'), [
            'gtm_id' => 'GTM-BADKIND',
            'kind' => 'other',
            'confidence' => 'たぶん',
            'confirmed_at' => 'not-a-date',
        ])->assertSessionHasErrors(['kind', 'confidence', 'confirmed_at']);
        $this->assertNull(GtmTag::where('gtm_id', 'GTM-BADKIND')->first());
    }

    public function test_index_lists_management_fields_with_masked_contact_and_unconfirmed_flag(): void
    {
        $user = $this->user();
        $confirmed = GtmTag::create(['gtm_id' => 'GTM-CONF123', 'company_name' => 'BE FANG', 'contact' => 'yamada@example.com', 'confidence' => '確認済']);
        GtmTag::create(['gtm_id' => 'GTM-NOCOMP1']);
        GtmTag::create(['gtm_id' => 'GTM-MIDDLE1', 'company_name' => '自社', 'kind' => 'site', 'confidence' => '中', 'contact' => '090-1234-5678']);
        $this->event(['gtm_tag_id' => $confirmed->id, 'gtm_id' => 'GTM-CONF123']);

        $this->withoutVite();
        $this->actingAs($user)->get(route('admin.gtm-tags.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/GtmTag/Index')
                ->has('gtmTags', 3)
                ->where('gtmTags', function ($tags) {
                    $byId = collect($tags)->keyBy('gtm_id');
                    $this->assertSame('y***@example.com', $byId['GTM-CONF123']['contact_masked']);
                    $this->assertSame('*********5678', $byId['GTM-MIDDLE1']['contact_masked']);
                    $this->assertSame(1, $byId['GTM-CONF123']['events_count']);
                    $this->assertFalse($byId['GTM-CONF123']['is_unconfirmed']);
                    $this->assertTrue($byId['GTM-NOCOMP1']['is_unconfirmed']);
                    $this->assertTrue($byId['GTM-MIDDLE1']['is_unconfirmed']);
                    $this->assertSame('site', $byId['GTM-MIDDLE1']['kind']);

                    return true;
                })
            );
    }

    public function test_options_only_include_event_kind_except_selected(): void
    {
        $event = GtmTag::create(['gtm_id' => 'GTM-EVENT01', 'company_name' => 'BE FANG', 'contact_person' => '山田', 'purpose' => '広告']);
        $company = GtmTag::create(['gtm_id' => 'GTM-COMPANY', 'kind' => 'company']);
        GtmTag::create(['gtm_id' => 'GTM-SITE001', 'kind' => 'site']);

        $this->assertSame(['GTM-EVENT01'], GtmTag::options()->pluck('gtm_id')->all());
        $this->assertSame(['GTM-COMPANY', 'GTM-EVENT01'], GtmTag::options($company->id)->pluck('gtm_id')->sort()->values()->all());

        $option = GtmTag::options()->first();
        $this->assertSame('山田', $option['contact_person']);
        $this->assertSame('広告', $option['purpose']);
        $this->assertSame($event->id, $option['id']);
    }

    public function test_event_cannot_select_non_event_kind_tag_but_keeps_saved_one(): void
    {
        $user = $this->user();
        $site = GtmTag::create(['gtm_id' => 'GTM-SITE002', 'kind' => 'site']);
        $event = $this->event();
        $payload = fn (array $extra) => array_merge([
            'title' => 'テスト',
            'slug' => $event->slug,
            'form_type' => 'reservation',
            'is_public' => true,
        ], $extra);

        $this->actingAs($user)->put(route('admin.events.update', $event), $payload(['gtm_tag_id' => $site->id]))
            ->assertSessionHasErrors('gtm_tag_id');
        $this->assertNull($event->fresh()->gtm_tag_id);

        // 種類を後から変えたタグが付いているイベントは、そのまま保存できる
        $event->update(['gtm_tag_id' => $site->id, 'gtm_id' => 'GTM-SITE002']);
        $this->actingAs($user)->put(route('admin.events.update', $event), $payload(['gtm_tag_id' => $site->id]))
            ->assertSessionHasNoErrors();
        $this->assertSame('GTM-SITE002', $event->fresh()->gtm_id);
    }

    public function test_backfill_fills_only_empty_fields_and_is_idempotent(): void
    {
        $shared = GtmTag::create(['gtm_id' => 'GTM-5LWBZH47', 'memo' => '既存イベントから自動登録']);
        $manual = GtmTag::create(['gtm_id' => 'GTM-5MSL9GRL', 'company_name' => '手入力の会社', 'confidence' => '低']);
        $anju = GtmTag::create(['gtm_id' => 'GTM-5NP4QCSB']);

        // dry-run は何も変えない
        $this->artisan('gtm:backfill', ['--dry-run' => true])->assertSuccessful();
        $this->assertSame(3, GtmTag::count());
        $this->assertNull($shared->fresh()->company_name);

        $this->artisan('gtm:backfill')->assertSuccessful();

        $shared->refresh();
        $this->assertSame('BE FANG', $shared->company_name);
        $this->assertSame('確認済', $shared->confidence);
        $this->assertSame('2026-11-28', $shared->confirmed_at->format('Y-m-d'));
        $this->assertSame('既存イベントから自動登録', $shared->memo);
        $this->assertSame('event', $shared->kind);

        // 入っている項目は上書きしない（確度が確認済でないので確認日も入れない）
        $manual->refresh();
        $this->assertSame('手入力の会社', $manual->company_name);
        $this->assertSame('低', $manual->confidence);
        $this->assertSame('福井の広告', $manual->purpose);
        $this->assertNull($manual->confirmed_at);

        $this->assertSame('アンジュ', $anju->fresh()->company_name);

        // マスタになかった ID は足す（種類・使用中も決める）
        $byId = GtmTag::all()->keyBy('gtm_id');
        $this->assertCount(9, $byId);
        $this->assertFalse($byId['GTM-N7BDKK6S']->is_active);
        $this->assertSame('アンジュ', $byId['GTM-N7BDKK6S']->company_name);
        $this->assertSame('company', $byId['GTM-WHQZSR67']->kind);
        $this->assertSame('確認済', $byId['GTM-WHQZSR67']->confidence);
        foreach (['GTM-MXSRMSXR', 'GTM-PXPJTD9K', 'GTM-P573JPFV'] as $id) {
            $this->assertSame('site', $byId[$id]->kind);
            $this->assertSame('自社', $byId[$id]->company_name);
            $this->assertSame('中', $byId[$id]->confidence);
            $this->assertNull($byId[$id]->confirmed_at);
        }

        // 2回目は何も変わらない
        $before = GtmTag::orderBy('id')->get()->map->only(['gtm_id', 'company_name', 'kind', 'purpose', 'used_on', 'confidence', 'memo', 'is_active'])->all();
        $this->artisan('gtm:backfill')->expectsOutputToContain('追加 0件・更新 0件・変更なし 9件')->assertSuccessful();
        $after = GtmTag::orderBy('id')->get()->map->only(['gtm_id', 'company_name', 'kind', 'purpose', 'used_on', 'confidence', 'memo', 'is_active'])->all();
        $this->assertSame($before, $after);
    }
}
