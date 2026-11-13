<?php

namespace Tests\Feature\Admin;

use App\Models\Incident;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * 共有ノート（incidents）：記録・グループの分離・直す権限・入力の決まり・経過・タブの件数
 */
class IncidentTest extends TestCase
{
    use RefreshDatabase;

    private Shop $okayama;

    private Shop $fukui;

    private User $staff;      // 岡山店の一般スタッフ

    private User $other;      // 岡山店の別の一般スタッフ

    private User $fukuiStaff; // 福井店の一般スタッフ

    private User $manager;    // 店舗管理者（岡山店）

    private User $admin;      // システム管理者（店舗なし）

    protected function setUp(): void
    {
        parent::setUp();

        $this->okayama = Shop::create(['name' => '岡山店', 'group_key' => 'okayama', 'is_active' => true]);
        Shop::create(['name' => '浜店', 'group_key' => 'okayama', 'is_active' => true]);
        $this->fukui = Shop::create(['name' => '福井店', 'group_key' => 'fukui', 'is_active' => true]);

        $this->staff = User::factory()->create(['attendance_role' => null]);
        $this->staff->shops()->attach($this->okayama->id, ['main' => true]);
        $this->other = User::factory()->create(['attendance_role' => null]);
        $this->other->shops()->attach($this->okayama->id);
        $this->fukuiStaff = User::factory()->create(['attendance_role' => null]);
        $this->fukuiStaff->shops()->attach($this->fukui->id);
        $this->manager = User::factory()->create(['attendance_role' => 'shop_manager']);
        $this->manager->shops()->attach($this->okayama->id);
        $this->admin = User::factory()->create(['attendance_role' => 'system_admin']);
    }

    private function payload(array $over = []): array
    {
        return array_merge([
            'occurred_on' => now()->toDateString(),
            'shop_name'   => '岡山店',
            'category'    => '前撮り',
            'kind'        => 'クレーム',
            'title'       => '重ね衿が入っていなかった',
            'what'        => '小物セットに重ね衿がなかった。',
            'action'      => '浜店から取り寄せた。',
            'consult'     => '',
        ], $over);
    }

    private function incident(string $group = 'okayama', array $over = [], ?User $by = null): Incident
    {
        return Incident::create(array_merge($this->payload(), [
            'group_key'  => $group,
            'created_by' => ($by ?? $this->staff)->id,
            'consult'    => null,
        ], $over));
    }

    public function test_staff_can_create_record_in_own_group(): void
    {
        $this->actingAs($this->staff)
            ->post(route('admin.incidents.store'), $this->payload(['title' => '  前後に空白  ']))
            ->assertRedirect()
            ->assertSessionHas('success', '共有しました。');

        $row = Incident::sole();
        $this->assertSame('okayama', $row->group_key);
        $this->assertSame($this->staff->id, $row->created_by);
        $this->assertSame('前後に空白', $row->title);
        $this->assertNull($row->consult);
    }

    public function test_create_page_shows_group_shops_and_my_shop(): void
    {
        $this->actingAs($this->staff)
            ->get(route('admin.incidents.create'))
            ->assertOk()
            ->assertInertia(fn (Assert $p) => $p
                ->component('Admin/Incident/Create')
                ->where('group', 'okayama')
                ->where('myShop', '岡山店')
                ->where('groups', [])
                ->has('shops', 2));
    }

    public function test_other_group_record_is_hidden(): void
    {
        $fukuiRecord = $this->incident('fukui', [], $this->fukuiStaff);
        $this->incident('okayama');

        $this->actingAs($this->staff)
            ->get(route('admin.incidents.show', $fukuiRecord))
            ->assertNotFound();

        $this->actingAs($this->staff)
            ->get(route('admin.incidents.index', ['group' => 'fukui']))
            ->assertInertia(fn (Assert $p) => $p
                ->where('group', 'okayama')
                ->has('incidents.data', 1));

        $this->actingAs($this->staff)
            ->post(route('admin.incidents.comments.store', $fukuiRecord), ['body' => 'x'])
            ->assertNotFound();
    }

    public function test_admin_can_switch_groups(): void
    {
        $this->incident('fukui', [], $this->fukuiStaff);

        $this->actingAs($this->admin)
            ->get(route('admin.incidents.index', ['group' => 'fukui']))
            ->assertOk()
            ->assertInertia(fn (Assert $p) => $p
                ->where('group', 'fukui')
                ->has('groups', 2)
                ->has('incidents.data', 1));

        $this->actingAs($this->admin)
            ->post(route('admin.incidents.store', ['group' => 'fukui']), $this->payload(['shop_name' => '福井店']))
            ->assertRedirect();
        $this->assertSame(2, Incident::where('group_key', 'fukui')->count());
    }

    public function test_only_author_and_manager_can_edit(): void
    {
        $record = $this->incident();

        $this->actingAs($this->other)->get(route('admin.incidents.edit', $record))->assertForbidden();
        $this->actingAs($this->other)->put(route('admin.incidents.update', $record), $this->payload(['title' => '他人']))->assertForbidden();

        $this->actingAs($this->other)
            ->get(route('admin.incidents.show', $record))
            ->assertInertia(fn (Assert $p) => $p->where('canEdit', false));

        $this->actingAs($this->staff)->get(route('admin.incidents.edit', $record))->assertOk();
        $this->actingAs($this->staff)
            ->put(route('admin.incidents.update', $record), $this->payload(['title' => '本人が直した']))
            ->assertRedirect(route('admin.incidents.show', $record))
            ->assertSessionHas('success', '保存しました。');
        $this->assertSame('本人が直した', $record->fresh()->title);

        $this->actingAs($this->manager)
            ->put(route('admin.incidents.update', $record), $this->payload(['title' => '店長が直した']))
            ->assertRedirect();
        $this->assertSame('店長が直した', $record->fresh()->title);
    }

    public function test_action_requirement_depends_on_kind(): void
    {
        $this->actingAs($this->staff)
            ->post(route('admin.incidents.store'), $this->payload(['kind' => 'クレーム', 'action' => '']))
            ->assertSessionHasErrors('action');

        $this->actingAs($this->staff)
            ->post(route('admin.incidents.store'), $this->payload(['kind' => 'ヒヤリ・ハット', 'action' => '']))
            ->assertSessionHasNoErrors();

        $this->actingAs($this->staff)
            ->post(route('admin.incidents.store'), $this->payload(['kind' => '気づき・提案', 'action' => '送られても捨てる']))
            ->assertSessionHasNoErrors();

        $this->assertNull(Incident::where('kind', '気づき・提案')->sole()->action);
    }

    public function test_free_text_other_kind_requires_action(): void
    {
        $this->actingAs($this->staff)
            ->post(route('admin.incidents.store'), $this->payload(['kind' => 'お礼の言葉', 'category' => '小物', 'action' => '']))
            ->assertSessionHasErrors('action');

        $this->actingAs($this->staff)
            ->post(route('admin.incidents.store'), $this->payload(['kind' => 'お礼の言葉', 'category' => '小物']))
            ->assertSessionHasNoErrors();
        $this->assertSame('小物', Incident::sole()->category);
    }

    public function test_future_date_is_rejected(): void
    {
        $this->actingAs($this->staff)
            ->post(route('admin.incidents.store'), $this->payload(['occurred_on' => now()->addDay()->toDateString()]))
            ->assertSessionHasErrors(['occurred_on' => '発生日に未来の日付は選べません。']);

        $this->assertSame(0, Incident::count());
    }

    public function test_anyone_in_group_can_add_comment(): void
    {
        $record = $this->incident();

        $this->actingAs($this->other)
            ->post(route('admin.incidents.comments.store', $record), ['body' => ''])
            ->assertSessionHasErrors('body');

        $this->actingAs($this->other)
            ->post(route('admin.incidents.comments.store', $record), ['body' => '店長と話して前日チェックにした'])
            ->assertRedirect()
            ->assertSessionHas('success', '経過を追加しました。');

        $this->actingAs($this->staff)
            ->get(route('admin.incidents.show', $record))
            ->assertInertia(fn (Assert $p) => $p
                ->has('incident.comments', 1)
                ->where('incident.comments.0.body', '店長と話して前日チェックにした')
                ->where('incident.comments.0.user', $this->other->name));
    }

    public function test_tab_counts_and_filters(): void
    {
        $this->incident('okayama', ['kind' => 'クレーム']);
        $this->incident('okayama', ['kind' => '問い合わせ']);
        $this->incident('okayama', ['kind' => '社内のミス・トラブル']);
        $this->incident('okayama', ['kind' => '気づき・提案', 'action' => null, 'title' => 'チェック表の提案']);
        $this->incident('okayama', ['kind' => 'お礼の言葉']);
        $this->incident('fukui', ['kind' => 'クレーム'], $this->fukuiStaff);

        $this->actingAs($this->staff)
            ->get(route('admin.incidents.index'))
            ->assertInertia(fn (Assert $p) => $p
                ->where('tabCounts', ['all' => 5, 'cust' => 2, 'miss' => 1, 'idea' => 1])
                ->has('incidents.data', 5)
                ->where('options.kinds', fn ($kinds) => collect($kinds)->contains('お礼の言葉')));

        $this->actingAs($this->staff)
            ->get(route('admin.incidents.index', ['tab' => 'cust']))
            ->assertInertia(fn (Assert $p) => $p->has('incidents.data', 2));

        $this->actingAs($this->staff)
            ->get(route('admin.incidents.index', ['q' => 'チェック表']))
            ->assertInertia(fn (Assert $p) => $p
                ->has('incidents.data', 1)
                ->where('incidents.data.0.kind', '気づき・提案'));

        // LIKE の特殊文字はそのまま文字として探す
        $this->actingAs($this->staff)
            ->get(route('admin.incidents.index', ['q' => '%']))
            ->assertInertia(fn (Assert $p) => $p->has('incidents.data', 0));
    }
}
