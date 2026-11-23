<?php

namespace Tests\Feature\Admin;

use App\Models\ActivityLog;
use App\Models\Meibo\MeiboDmLog;
use App\Models\Meibo\MeiboLabelTemplate;
use App\Models\Meibo\MeiboPerson;
use App\Models\Meibo\MeiboZipGeo;
use App\Models\Shop;
use App\Models\User;
use Database\Seeders\MeiboDummySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * 名簿のデータ（MEIBO_DATA_SOURCE=db のとき、画面が MeiboController のルートから読む・書く）。
 * データはすべて架空（Factory・MeiboDummySeeder）。
 */
class MeiboDataTest extends TestCase
{
    use RefreshDatabase;

    private User $manager;

    protected function setUp(): void
    {
        parent::setUp();
        config(['meibo.data_source' => 'db']);
        $this->manager = $this->user();
    }

    private function user(?string $role = 'manager'): User
    {
        $user = User::factory()->create(['attendance_role' => $role]);
        $shop = Shop::create(['name' => 'テスト店', 'is_active' => true]);
        $user->shops()->attach($shop->id);

        return $user;
    }

    private function asManager()
    {
        return $this->actingAs($this->manager);
    }

    // ---------------------------------------------------------------- 切替・権限

    public function test_data_routes_are_closed_while_dummy(): void
    {
        config(['meibo.data_source' => 'dummy']);
        MeiboPerson::factory()->create();

        $this->asManager()->getJson(route('admin.meibo.data.persons.index'))->assertNotFound();
        $this->asManager()->getJson(route('admin.meibo.data.stats'))->assertNotFound();
    }

    public function test_page_passes_db_source_and_options(): void
    {
        MeiboPerson::factory()->create(['pref' => '福井県', 'seijin_year' => 2029]);
        MeiboPerson::factory()->create(['pref' => '岡山県', 'seijin_year' => 2027]);

        $this->asManager()->get(route('admin.meibo.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Meibo/Index')
                ->where('dataSource', 'db')
                ->where('meiboOptions.prefs', ['岡山県', '福井県'])
                ->where('meiboOptions.seijin_years', [2027, 2029])
                ->where('meiboOptions.statuses', MeiboPerson::STATUSES));
    }

    public function test_old_name_http_is_treated_as_db(): void
    {
        config(['meibo.data_source' => 'http']);

        $this->asManager()->get(route('admin.meibo.index'))
            ->assertInertia(fn (Assert $page) => $page->where('dataSource', 'db'));
    }

    public function test_non_manager_and_guest_cannot_read_or_write(): void
    {
        $p = MeiboPerson::factory()->create();
        $staff = $this->user(null);

        $this->actingAs($staff)->getJson(route('admin.meibo.data.persons.index'))->assertForbidden();
        $this->actingAs($staff)->getJson(route('admin.meibo.data.persons.show', $p))->assertForbidden();
        $this->actingAs($staff)->patchJson(route('admin.meibo.data.persons.update', $p), ['dm_status' => '停止'])->assertForbidden();
        $this->actingAs($staff)->postJson(route('admin.meibo.data.templates.store'), ['name' => 'x'])->assertForbidden();
        $this->assertSame('送付可', $p->fresh()->dm_status);

        auth()->logout();
        $this->getJson(route('admin.meibo.data.persons.index'))->assertUnauthorized();
    }

    // ---------------------------------------------------------------- 一覧・検索・絞り込み

    public function test_list_returns_only_screen_fields_with_code(): void
    {
        MeiboPerson::factory()->create(['master_code' => 'M900001', 'birth_date' => '2008-04-01', 'gender' => '女', 'guardian_kana' => 'ヤマダ　イチロウ']);

        $res = $this->asManager()->getJson(route('admin.meibo.data.persons.index'))->assertOk();
        $res->assertJsonPath('total', 1)->assertJsonPath('items.0.code', 'M900001');
        $item = $res->json('items.0');
        $this->assertSame([
            'id', 'code', 'pref', 'seijin_year', 'name', 'kana', 'zip', 'address', 'tel', 'mobile',
            'guardian_name', 'dm_status', 'dm_note', 'source_versions', 'merge_count',
        ], array_keys($item));
    }

    public function test_filters_by_pref_year_status_source_and_keyword(): void
    {
        $a = MeiboPerson::factory()->r11()->create(['name' => '山田　花子', 'kana' => 'ヤマダ　ハナコ']);
        $b = MeiboPerson::factory()->create(['pref' => '岡山県', 'seijin_year' => 2027, 'name' => '佐藤　美咲', 'source_versions' => 'G_R07/H_2024']);
        $c = MeiboPerson::factory()->status('停止', '電話で停止の依頼')->create(['pref' => '福井県', 'seijin_year' => 2029, 'name' => '鈴木　結衣', 'source_versions' => 'G_R110']);

        $ids = fn (array $q) => collect($this->asManager()->getJson(route('admin.meibo.data.persons.index', $q))->assertOk()->json('items'))->pluck('id')->sort()->values()->all();

        $this->assertSame([$b->id], $ids(['pref' => '岡山県']));
        $this->assertSame([$a->id, $c->id], $ids(['seijin_year' => 2029]));
        $this->assertSame([$c->id], $ids(['status' => '停止']));
        // 出所は「/」区切りの 1 つとして見る（G_R110 は R11 ではない）
        $this->assertSame([$a->id], $ids(['source' => 'r11']));
        $this->assertSame([$b->id, $c->id], $ids(['source' => 'other']));
        // キーワード：全角・半角スペースの違いは無視
        $this->assertSame([$a->id], $ids(['q' => '山田 花子']));
        $this->assertSame([$a->id], $ids(['q' => 'ヤマダハナコ']));
        $this->assertSame([], $ids(['q' => '%']));
    }

    public function test_paging_and_select_all_ids(): void
    {
        MeiboPerson::factory()->count(7)->create(['pref' => '福井県']);
        MeiboPerson::factory()->count(2)->create(['pref' => '岡山県']);

        $this->asManager()->getJson(route('admin.meibo.data.persons.index', ['page' => 2, 'page_size' => 5]))
            ->assertOk()->assertJsonPath('total', 9)->assertJsonCount(4, 'items');

        $ids = $this->asManager()->getJson(route('admin.meibo.data.persons.ids', ['pref' => '福井県']))->assertOk()->json('ids');
        $this->assertCount(7, $ids);
        $this->assertEqualsCanonicalizing(MeiboPerson::where('pref', '福井県')->pluck('id')->all(), $ids);

        $this->asManager()->getJson(route('admin.meibo.data.persons.index', ['page_size' => 500]))->assertUnprocessable();
        $this->asManager()->getJson(route('admin.meibo.data.persons.index', ['status' => '不明']))->assertUnprocessable();
    }

    public function test_by_ids_keeps_order_and_summary_counts_r11_by_post(): void
    {
        $r11 = MeiboPerson::factory()->r11()->count(3)->create();
        $other = MeiboPerson::factory()->count(2)->create();
        $order = [$other[1]->id, $r11[0]->id, $other[0]->id];

        $rows = $this->asManager()->getJson(route('admin.meibo.data.persons.by-ids', ['ids' => implode(',', $order)]))->assertOk()->json();
        $this->assertSame($order, array_column($rows, 'id'));

        $all = array_merge($r11->pluck('id')->all(), $other->pluck('id')->all(), [999999]);
        $this->asManager()->postJson(route('admin.meibo.data.persons.summary'), ['ids' => $all])
            ->assertOk()->assertExactJson(['count' => 5, 'r11' => 3]);
    }

    public function test_detail_returns_logs_newest_first_with_who(): void
    {
        $p = MeiboPerson::factory()->status('未達', '宛先不明で返送')->create();
        MeiboDmLog::factory()->create(['person_id' => $p->id, 'changed_at' => '2026-02-10 10:15:00']);
        MeiboDmLog::factory()->create(['person_id' => $p->id, 'old_status' => '未達', 'new_status' => '未達', 'changed_by_user_id' => $this->manager->id, 'changed_at' => '2026-03-01 09:00:00']);

        $this->asManager()->getJson(route('admin.meibo.data.persons.show', $p))
            ->assertOk()
            ->assertJsonPath('code', $p->master_code)
            ->assertJsonCount(2, 'logs')
            ->assertJsonPath('logs.0.changed_at', '2026-03-01T09:00:00')
            ->assertJsonPath('logs.0.changed_by', $this->manager->name)
            ->assertJsonPath('logs.1.changed_by', '（取り込み）');

        $this->asManager()->getJson(route('admin.meibo.data.persons.show', 999999))->assertNotFound();
    }

    // ---------------------------------------------------------------- DM 状態・備考

    public function test_update_dm_writes_log_only_when_changed(): void
    {
        $p = MeiboPerson::factory()->create();

        $this->asManager()->patchJson(route('admin.meibo.data.persons.update', $p), ['dm_status' => '停止', 'dm_note' => ' ご家族から送付停止の依頼 '])
            ->assertOk()->assertJsonPath('dm_status', '停止')->assertJsonPath('dm_note', 'ご家族から送付停止の依頼');

        $this->assertSame('停止', $p->fresh()->dm_status);
        $log = MeiboDmLog::sole();
        $this->assertSame(['送付可', '停止', $this->manager->id], [$log->old_status, $log->new_status, $log->changed_by_user_id]);
        $this->assertSame('ご家族から送付停止の依頼', $log->note);

        // 同じ内容をもう一度送っても履歴は増えない
        $this->asManager()->patchJson(route('admin.meibo.data.persons.update', $p), ['dm_status' => '停止', 'dm_note' => 'ご家族から送付停止の依頼'])->assertOk();
        $this->assertSame(1, MeiboDmLog::count());

        // 備考だけ変えたときも残す
        $this->asManager()->patchJson(route('admin.meibo.data.persons.update', $p), ['dm_status' => '停止', 'dm_note' => ''])->assertOk();
        $this->assertSame(2, MeiboDmLog::count());

        $this->asManager()->patchJson(route('admin.meibo.data.persons.update', $p), ['dm_status' => '保留'])->assertUnprocessable();
        $this->assertSame(2, MeiboDmLog::count());
    }

    public function test_activity_log_keeps_only_summary_without_personal_info_or_keywords(): void
    {
        $p = MeiboPerson::factory()->create(['name' => '架空　太郎', 'address' => '福井市架空町1丁目']);

        // 画面を開く・検索する・テンプレートを触るのは汎用ログに残らない
        $this->asManager()->get(route('admin.meibo.index', ['q' => '架空']))->assertOk();
        $this->asManager()->getJson(route('admin.meibo.data.persons.index', ['q' => '架空']))->assertOk();
        $this->asManager()->postJson(route('admin.meibo.data.templates.store'), ['name' => '金沢発送'])->assertCreated();
        $this->assertSame(0, ActivityLog::count());

        // DM の変更だけ要約が 1 件残る
        $this->asManager()->patchJson(route('admin.meibo.data.persons.update', $p), ['dm_status' => '未達', 'dm_note' => '架空町から返送'])->assertOk();
        $log = ActivityLog::sole();
        $this->assertSame('MeiboPerson', $log->resource_type);
        $this->assertSame($p->id, $log->resource_id);
        $this->assertStringContainsString($p->master_code, $log->description);
        $this->assertStringContainsString('送付可→未達', $log->description);
        $dump = json_encode($log->toArray(), JSON_UNESCAPED_UNICODE);
        $this->assertStringNotContainsString('架空', $dump);
        $this->assertStringNotContainsString('q=', $log->url);
    }

    // ---------------------------------------------------------------- ラベルのテンプレート

    public function test_template_crud_and_name_conflict_is_409(): void
    {
        $fukui = MeiboLabelTemplate::factory()->create(['name' => '福井発送']);

        $created = $this->asManager()->postJson(route('admin.meibo.data.templates.store'), [
            'name' => '  岡山発送 ', 'sender_zip' => '700-0000', 'with_family' => false, 'extra' => 'x',
        ])->assertCreated()->assertJsonPath('name', '岡山発送')->assertJsonPath('with_family', false)->json();
        $this->assertArrayNotHasKey('extra', $created);

        // 同じ名前の追加は 409（code: name_taken）
        $this->asManager()->postJson(route('admin.meibo.data.templates.store'), ['name' => '福井発送'])
            ->assertStatus(409)->assertJsonPath('code', 'name_taken');
        $this->asManager()->postJson(route('admin.meibo.data.templates.store'), ['name' => '   '])
            ->assertUnprocessable()->assertJsonValidationErrors(['name' => 'テンプレート名を入れてください']);

        // 編集：中身と名前。ほかと同じ名前は 409、自分と同じ名前はよい
        $this->asManager()->putJson(route('admin.meibo.data.templates.update', $created['id']), ['name' => '岡山発送（本店）', 'sender_address' => '岡山県岡山市北区架空町1-2-3'])
            ->assertOk()->assertJsonPath('name', '岡山発送（本店）')->assertJsonPath('sender_address', '岡山県岡山市北区架空町1-2-3');
        $this->asManager()->putJson(route('admin.meibo.data.templates.update', $created['id']), ['name' => '福井発送'])->assertStatus(409);
        $this->asManager()->putJson(route('admin.meibo.data.templates.update', $created['id']), ['name' => '岡山発送（本店）', 'tantou' => '担当A'])->assertOk();

        // 一覧は id 順
        $this->asManager()->getJson(route('admin.meibo.data.templates.index'))
            ->assertOk()->assertJsonPath('0.id', $fukui->id)->assertJsonPath('1.name', '岡山発送（本店）');

        // 削除
        $this->asManager()->deleteJson(route('admin.meibo.data.templates.destroy', $created['id']))->assertOk();
        $this->assertSame(['福井発送'], MeiboLabelTemplate::pluck('name')->all());
        $this->asManager()->putJson(route('admin.meibo.data.templates.update', $created['id']), ['name' => 'x'])->assertNotFound();
    }

    // ---------------------------------------------------------------- ダッシュボード・地図

    public function test_stats_counts_status_year_and_r11(): void
    {
        MeiboPerson::factory()->r11()->count(2)->create();
        MeiboPerson::factory()->status('未達')->create(['pref' => '岡山県', 'seijin_year' => 2027]);
        MeiboPerson::factory()->status('停止')->create(['pref' => '岡山県', 'seijin_year' => 2027]);

        $s = $this->asManager()->getJson(route('admin.meibo.data.stats'))->assertOk()->json();
        $this->assertSame(4, $s['total']);
        $this->assertSame(['送付可' => 2, '未達' => 1, '停止' => 1], $s['by_status']);
        $this->assertSame(2, $s['r11']);
        $this->assertEqualsCanonicalizing([
            ['pref' => '岡山県', 'seijin_year' => 2027, 'c' => 2],
            ['pref' => '福井県', 'seijin_year' => 2029, 'c' => 2],
        ], $s['by_year']);
    }

    public function test_map_joins_zip_geo_and_counts_unmatched(): void
    {
        MeiboZipGeo::factory()->create(['zip' => '910-0001', 'lat' => 36.06, 'lng' => 136.22]);
        MeiboPerson::factory()->count(3)->create(['pref' => '福井県', 'zip' => '910-0001', 'address' => '福井市中央1丁目1-1', 'dm_status' => '送付可']);
        MeiboPerson::factory()->status('停止')->create(['pref' => '福井県', 'zip' => '910-0001', 'address' => '福井市中央1丁目1-2']);
        MeiboPerson::factory()->create(['pref' => '福井県', 'zip' => '919-9999']);

        $this->asManager()->getJson(route('admin.meibo.data.map'))
            ->assertOk()
            ->assertJsonPath('plotted', 4)
            ->assertJsonPath('spots', 1)
            ->assertJsonPath('unmatched', 1)
            ->assertJsonPath('points.0.zip', '910-0001')
            ->assertJsonPath('points.0.count', 4)
            ->assertJsonPath('points.0.area', '福井県福井市');

        $this->asManager()->getJson(route('admin.meibo.data.map', ['status' => '停止']))
            ->assertOk()->assertJsonPath('plotted', 1)->assertJsonPath('unmatched', 0);
    }

    // ---------------------------------------------------------------- 架空ダミー

    public function test_dummy_seeder_fills_fictitious_data(): void
    {
        $this->seed(MeiboDummySeeder::class);

        $this->assertSame(MeiboDummySeeder::COUNT, MeiboPerson::count());
        $this->assertSame(2, MeiboLabelTemplate::count());
        $this->assertGreaterThan(0, MeiboPerson::fromR11()->count());
        $this->assertGreaterThan(0, MeiboZipGeo::count());
        $this->assertSame(MeiboPerson::where('dm_status', '!=', '送付可')->count(), MeiboDmLog::count());
        // 電話は使われていない番号の形だけ
        $this->assertSame(0, MeiboPerson::whereNotNull('tel')->where('tel', 'not like', '%-00-%')->count());
    }
}
