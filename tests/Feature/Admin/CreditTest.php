<?php

namespace Tests\Feature\Admin;

use App\Models\CreditCompany;
use App\Models\CreditContract;
use App\Models\CreditPayment;
use App\Models\Customer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CreditTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    private function editor(): User
    {
        return User::factory()->create(['can_edit_credit' => true]);
    }

    private function viewer(): User
    {
        return User::factory()->create(['can_edit_credit' => false]);
    }

    private function company(string $name): CreditCompany
    {
        return CreditCompany::query()->where('name', $name)->firstOrFail();
    }

    private function payload(array $override = []): array
    {
        return array_merge([
            'credit_company_id' => $this->company('自社')->id,
            'contract_date' => '2026-11-01',
            'item_name' => '振袖',
            'principal' => 300000,
            'fee' => 0,
            'start_month' => '2026-12',
            'count' => 10,
            'monthly_amount' => 30000,
        ], $override);
    }

    public function test_companies_are_seeded_and_aliases_resolve(): void
    {
        $this->assertSame('Nクレジット（旧ヤマトクレジット）', CreditCompany::findByLabel('ﾔﾏﾄ')->name);
        $this->assertSame('Nクレジット（旧ヤマトクレジット）', CreditCompany::findByLabel('N')->name);
        $this->assertSame('JACCS', CreditCompany::findByLabel('ジャックス')->name);
        $this->assertSame('セディナ', CreditCompany::findByLabel('ｾﾃﾞｨﾅ')->name);
        $this->assertTrue($this->company('自社')->needs_check);
        $this->assertTrue($this->company('現金')->needs_check);
        $this->assertFalse($this->company('セディナ')->needs_check);
        $this->assertNull(CreditCompany::findByLabel('どこか'));
    }

    public function test_every_staff_can_view_but_only_editors_can_change(): void
    {
        $viewer = $this->viewer();
        $customer = Customer::create(['name' => '山田　花子']);

        $this->actingAs($viewer)->get(route('admin.credits.index'))->assertOk();
        $this->actingAs($viewer)->get(route('admin.credits.monthly'))->assertOk();
        $this->actingAs($viewer)->getJson(route('admin.customers.credits', $customer))
            ->assertOk()->assertJson(['canEdit' => false]);

        $this->actingAs($viewer)->get(route('admin.credits.create'))->assertForbidden();
        $this->actingAs($viewer)->post(route('admin.credits.store'), $this->payload(['customer_id' => $customer->id]))->assertForbidden();
        $this->assertSame(0, CreditContract::count());
    }

    public function test_store_creates_schedule_for_existing_customer(): void
    {
        $customer = Customer::create(['name' => '山田　花子']);

        $this->actingAs($this->editor())
            ->post(route('admin.credits.store'), $this->payload(['customer_id' => $customer->id, 'first_amount' => 30500]))
            ->assertRedirect();

        $contract = CreditContract::firstOrFail();
        $this->assertSame($customer->id, $contract->customer_id);
        $this->assertSame(10, $contract->payments()->count());
        $this->assertSame('2026-12-01', $contract->payments()->first()->due_month->toDateString());
        $this->assertSame('2027-09-01', $contract->payments()->get()->last()->due_month->toDateString());
        $this->assertSame(30500, $contract->payments()->first()->amount);
        $this->assertSame(30500 + 30000 * 9, (int) $contract->payments()->sum('amount'));
    }

    public function test_store_creates_new_customer_without_shop(): void
    {
        $this->actingAs($this->editor())
            ->post(route('admin.credits.store'), $this->payload(['new_customer_name' => '新規 太郎']))
            ->assertRedirect();

        $customer = Customer::where('name', '新規　太郎')->firstOrFail();
        $this->assertNull($customer->shop_id);
        $this->assertSame(1, $customer->creditContracts()->count());
    }

    public function test_mark_paid_completes_contract_and_can_revert(): void
    {
        $editor = $this->editor();
        $customer = Customer::create(['name' => '山田　花子']);
        $this->actingAs($editor)->post(route('admin.credits.store'), $this->payload(['customer_id' => $customer->id, 'count' => 2]));
        $contract = CreditContract::firstOrFail();
        $ids = $contract->payments()->pluck('id')->all();

        $this->actingAs($editor)->post(route('admin.credits.payments.paid'), ['ids' => $ids, 'is_paid' => true])->assertRedirect();
        $this->assertSame(CreditContract::STATUS_COMPLETED, $contract->fresh()->status);
        $this->assertSame($editor->id, CreditPayment::find($ids[0])->paid_checked_by);

        $this->actingAs($editor)->post(route('admin.credits.payments.paid'), ['ids' => [$ids[1]], 'is_paid' => false])->assertRedirect();
        $this->assertSame(CreditContract::STATUS_ACTIVE, $contract->fresh()->status);
        $this->assertNull(CreditPayment::find($ids[1])->paid_checked_by);
    }

    public function test_viewer_cannot_mark_paid(): void
    {
        $customer = Customer::create(['name' => '山田　花子']);
        $this->actingAs($this->editor())->post(route('admin.credits.store'), $this->payload(['customer_id' => $customer->id]));
        $id = CreditPayment::firstOrFail()->id;

        $this->actingAs($this->viewer())->post(route('admin.credits.payments.paid'), ['ids' => [$id], 'is_paid' => true])->assertForbidden();
        $this->assertFalse(CreditPayment::find($id)->is_paid);
    }

    public function test_monthly_shows_only_own_credit_and_cash(): void
    {
        $editor = $this->editor();
        $customer = Customer::create(['name' => '山田　花子']);
        $this->actingAs($editor)->post(route('admin.credits.store'), $this->payload(['customer_id' => $customer->id]));
        $this->actingAs($editor)->post(route('admin.credits.store'), $this->payload([
            'customer_id' => $customer->id,
            'credit_company_id' => $this->company('セディナ')->id,
        ]));

        $this->actingAs($editor)->get(route('admin.credits.monthly', ['month' => '2026-12']))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Admin/Credit/Monthly')
                ->has('payments', 1)
                ->where('payments.0.company', '自社'));
    }

    public function test_index_defaults_to_sheet_with_monthly_cells(): void
    {
        $editor = $this->editor();
        $customer = Customer::create(['name' => '山田　花子']);
        $this->actingAs($editor)->post(route('admin.credits.store'), $this->payload(['customer_id' => $customer->id]));

        $this->actingAs($editor)->get(route('admin.credits.index', ['from' => '2026-11']))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Admin/Credit/Index')
                ->where('filters.view', 'sheet')
                ->has('months', 12)
                ->where('months.0', '2026-11')
                ->where('customers.data.0.contracts.0.cells.2026-12.amount', 30000)
                ->where('customers.data.0.contracts.0.cells.2026-12.is_paid', false));

        $this->actingAs($editor)->get(route('admin.credits.index', ['view' => 'list']))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('filters.view', 'list')
                ->has('months', 0)
                ->missing('customers.data.0.contracts.0.cells'));
    }

    public function test_index_groups_contracts_by_customer(): void
    {
        $editor = $this->editor();
        $a = Customer::create(['name' => '山田　花子']);
        $b = Customer::create(['name' => '鈴木　一郎']);
        $this->actingAs($editor)->post(route('admin.credits.store'), $this->payload(['customer_id' => $a->id]));
        $this->actingAs($editor)->post(route('admin.credits.store'), $this->payload([
            'customer_id' => $a->id, 'credit_company_id' => $this->company('セディナ')->id,
        ]));
        $this->actingAs($editor)->post(route('admin.credits.store'), $this->payload(['customer_id' => $b->id]));

        foreach (['sheet', 'list'] as $view) {
            $this->actingAs($editor)->get(route('admin.credits.index', ['view' => $view]))
                ->assertOk()
                ->assertInertia(fn ($page) => $page
                    ->where('customers.total', 2)
                    ->where('contractTotal', 3)
                    ->has('customers.data', 2)
                    ->where('customers.data', fn ($rows) => collect($rows)->firstWhere('customer.name', '山田　花子')['contract_count'] === 2
                        && count(collect($rows)->firstWhere('customer.name', '山田　花子')['contracts']) === 2));
        }

        // 絞り込みは明細にも効く（セディナで絞ると、山田さんの行の明細はセディナの1件だけ）
        $this->actingAs($editor)->get(route('admin.credits.index', ['view' => 'list', 'company' => $this->company('セディナ')->id]))
            ->assertInertia(fn ($page) => $page
                ->where('customers.total', 1)
                ->where('customers.data.0.contract_count', 1)
                ->where('customers.data.0.contracts.0.company', 'セディナ'));
    }

    public function test_index_shows_and_searches_guardian_name(): void
    {
        $editor = $this->editor();
        $child = Customer::create(['name' => '北島　美結', 'guardian_name' => '北島　和美']);
        $this->actingAs($editor)->post(route('admin.credits.store'), $this->payload(['customer_id' => $child->id]));

        $this->actingAs($editor)->get(route('admin.credits.index', ['q' => '北島和美']))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('customers.data', 1)
                ->where('customers.data.0.customer.guardian_name', '北島　和美'));
    }

    public function test_agency_past_months_are_settled_automatically(): void
    {
        $editor = $this->editor();
        $customer = Customer::create(['name' => '山田　花子']);
        $past = now()->startOfMonth()->subMonths(2)->format('Y-m');
        // 信販会社：2か月前から3回（2回は過去、1回は今月）
        $this->actingAs($editor)->post(route('admin.credits.store'), $this->payload([
            'customer_id' => $customer->id, 'credit_company_id' => $this->company('セディナ')->id, 'start_month' => $past, 'count' => 3,
        ]));
        // 自社：同じ期間（自動では支払済にしない）
        $this->actingAs($editor)->post(route('admin.credits.store'), $this->payload([
            'customer_id' => $customer->id, 'start_month' => $past, 'count' => 3,
        ]));
        [$agency, $own] = CreditContract::orderBy('id')->get()->all();

        $this->assertSame(2, CreditContract::settleAgencyPayments());
        $this->assertSame(1, $agency->payments()->where('is_paid', false)->count());
        $this->assertSame(3, $own->payments()->where('is_paid', false)->count());
        $this->assertSame(CreditContract::STATUS_ACTIVE, $agency->fresh()->status);

        // 2か月後には全部過ぎるので完済になる
        $this->assertSame(1, CreditContract::settleAgencyPayments(now()->addMonths(2)));
        $this->assertSame(CreditContract::STATUS_COMPLETED, $agency->fresh()->status);
        $this->assertSame(0, CreditContract::settleAgencyPayments(now()->addMonths(2)));

        // 画面を開いたときにも同じ処理が走る（コマンドからも呼べる）
        $this->artisan('credit:settle')->assertSuccessful();
    }

    public function test_update_edits_amounts_and_paid_flags(): void
    {
        $editor = $this->editor();
        $customer = Customer::create(['name' => '山田　花子']);
        $this->actingAs($editor)->post(route('admin.credits.store'), $this->payload(['customer_id' => $customer->id, 'count' => 2]));
        $contract = CreditContract::firstOrFail();
        [$p1, $p2] = $contract->payments()->get()->all();

        $this->actingAs($editor)->put(route('admin.credits.update', $contract), array_merge($this->payload(), [
            'status' => 'active',
            'payments' => [
                ['id' => $p1->id, 'amount' => 35000, 'is_paid' => true],
                ['id' => $p2->id, 'amount' => 25000, 'is_paid' => false],
            ],
        ]))->assertRedirect();

        $this->assertSame(35000, $p1->fresh()->amount);
        $this->assertTrue($p1->fresh()->is_paid);
        $this->assertSame(CreditContract::STATUS_ACTIVE, $contract->fresh()->status);
    }

    public function test_user_flag_is_kept_when_legacy_form_omits_it(): void
    {
        $admin = User::factory()->create(['attendance_role' => 'system_admin']);
        $target = User::factory()->create(['can_edit_credit' => true]);

        $this->actingAs($admin)->put(route('admin.users.update', $target), [
            'name' => $target->name,
            'email' => $target->email,
            'break_mode' => 'manual',
        ]);
        $this->assertTrue($target->fresh()->can_edit_credit);

        $this->actingAs($admin)->put(route('admin.users.update', $target), [
            'name' => $target->name,
            'email' => $target->email,
            'break_mode' => 'manual',
            'can_edit_credit' => false,
        ]);
        $this->assertFalse($target->fresh()->can_edit_credit);
    }
    public function test_provisional_contract_can_be_saved_without_company_only_while_provisional(): void
    {
        $editor = $this->editor();
        $customer = Customer::create(['name' => '仮　花子']);

        // 仮登録でなければ信販会社は必須
        $this->actingAs($editor)->post(route('admin.credits.store'), $this->payload([
            'customer_id' => $customer->id, 'credit_company_id' => '',
        ]))->assertSessionHasErrors('credit_company_id');

        // 仮登録なら未定のまま登録できる
        $this->actingAs($editor)->post(route('admin.credits.store'), $this->payload([
            'customer_id' => $customer->id, 'credit_company_id' => '',
            'is_provisional' => true, 'provisional_note' => '信販会社がわからない',
        ]))->assertSessionHasNoErrors();
        $contract = CreditContract::firstOrFail();
        $this->assertNull($contract->credit_company_id);
        $this->assertTrue($contract->is_provisional);
        $this->assertSame('信販会社がわからない', $contract->provisional_note);

        // 未定のまま仮登録を外そうとすると止まる
        $this->actingAs($editor)->put(route('admin.credits.update', $contract), array_merge($this->payload(), [
            'credit_company_id' => '', 'is_provisional' => false, 'status' => 'active', 'payments' => [],
        ]))->assertSessionHasErrors('credit_company_id');
        $this->assertTrue($contract->fresh()->is_provisional);

        // 信販会社を入れて外すと、確認した人と日時が残る
        $this->actingAs($editor)->put(route('admin.credits.update', $contract), array_merge($this->payload(), [
            'is_provisional' => false, 'status' => 'active', 'payments' => [],
        ]))->assertSessionHasNoErrors();
        $fresh = $contract->fresh();
        $this->assertFalse($fresh->is_provisional);
        $this->assertNotNull($fresh->confirmed_at);
        $this->assertSame($editor->id, $fresh->confirmed_by);
    }

    public function test_update_without_provisional_field_keeps_the_mark(): void
    {
        $editor = $this->editor();
        $customer = Customer::create(['name' => '仮　次郎']);
        $this->actingAs($editor)->post(route('admin.credits.store'), $this->payload([
            'customer_id' => $customer->id, 'is_provisional' => true, 'provisional_note' => '契約日の確認',
        ]));
        $contract = CreditContract::firstOrFail();

        $this->actingAs($editor)->put(route('admin.credits.update', $contract), array_merge($this->payload(), [
            'status' => 'active', 'payments' => [],
        ]))->assertSessionHasNoErrors();
        $this->assertTrue($contract->fresh()->is_provisional);
        $this->assertSame('契約日の確認', $contract->fresh()->provisional_note);
    }

    public function test_index_filters_provisional_including_completed(): void
    {
        $editor = $this->editor();
        $a = Customer::create(['name' => '仮登録　一']);
        $b = Customer::create(['name' => '通常　二']);
        $this->actingAs($editor)->post(route('admin.credits.store'), $this->payload(['customer_id' => $a->id, 'is_provisional' => true]));
        $this->actingAs($editor)->post(route('admin.credits.store'), $this->payload(['customer_id' => $b->id]));
        CreditContract::query()->where('customer_id', $a->id)->update(['status' => 'completed']);

        $this->actingAs($editor)->get(route('admin.credits.index', ['view' => 'list', 'provisional' => '1']))
            ->assertInertia(fn ($page) => $page
                ->where('customers.total', 1)
                ->where('contractTotal', 1)
                ->where('customers.data.0.customer.name', '仮登録　一')
                ->where('customers.data.0.provisional_count', 1)
                ->where('customers.data.0.contracts.0.is_provisional', true)
                ->where('filters.status', 'all')
                ->where('provisionalCount', 1));
    }

    public function test_provisional_command_marks_by_source_file_and_unknown_company(): void
    {
        $customer = Customer::create(['name' => '取込　三']);
        $base = ['customer_id' => $customer->id, 'principal' => 100000, 'fee' => 0, 'status' => 'active'];
        $a = CreditContract::create($base + ['credit_company_id' => $this->company('自社')->id, 'source_file' => 'a.xls#C']);
        $b = CreditContract::create($base + ['credit_company_id' => null, 'source_file' => 'b.xls#C']);
        $c = CreditContract::create($base + ['credit_company_id' => null, 'source_file' => 'c.xls#C',
            'confirmed_at' => now(), 'confirmed_by' => null]);
        $d = CreditContract::create($base + ['credit_company_id' => $this->company('自社')->id, 'source_file' => 'd.xls#C']);
        $e = CreditContract::create(array_merge($base, ['credit_company_id' => null, 'source_file' => 'e.xls#C', 'status' => 'cancelled']));

        $csv = tempnam(sys_get_temp_dir(), 'prov');
        file_put_contents($csv, "\xEF\xBB\xBF取り込み元,確認していること\na.xls#C,現金払いか確認\nzzz.xls#C,なし\n");

        $this->artisan('credit:provisional', ['csv' => $csv, '--unknown-company' => true, '--dry-run' => true])->assertSuccessful();
        $this->assertFalse($a->fresh()->is_provisional);

        $this->artisan('credit:provisional', ['csv' => $csv, '--unknown-company' => true])->assertSuccessful();
        unlink($csv);

        $this->assertTrue($a->fresh()->is_provisional);
        $this->assertSame('現金払いか確認', $a->fresh()->provisional_note);
        $this->assertTrue($b->fresh()->is_provisional);
        $this->assertSame(\App\Console\Commands\MarkCreditProvisional::UNKNOWN_COMPANY_NOTE, $b->fresh()->provisional_note);
        $this->assertFalse($c->fresh()->is_provisional, '確認済みは触らない');
        $this->assertFalse($d->fresh()->is_provisional);
        $this->assertFalse($e->fresh()->is_provisional, '解約は対象外');
    }
}
