<?php

namespace Tests\Feature\Admin;

use App\Models\Contract;
use App\Models\CsvExportLog;
use App\Models\Customer;
use App\Models\Plan;
use App\Models\Shop;
use App\Models\User;
use App\Queries\CustomerSearchQuery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * 顧客一覧：成人年度の範囲絞り込みと、絞り込み結果のCSV出力
 */
class CustomerSeijinYearExportTest extends TestCase
{
    use RefreshDatabase;

    private Shop $shop;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->shop = Shop::create(['name' => '岡山店', 'is_active' => true]);
        $this->admin = User::factory()->create(['attendance_role' => 'system_admin']);
        $this->admin->shops()->attach($this->shop->id);
    }

    private function customer(string $name, ?int $year, ?Shop $shop = null): Customer
    {
        return Customer::create([
            'name' => $name,
            'coming_of_age_year' => $year,
            'shop_id' => ($shop ?? $this->shop)->id,
        ]);
    }

    private function searchIds(array $params): array
    {
        $request = Request::create('/admin/customers', 'GET', $params);
        ['query' => $query] = CustomerSearchQuery::build($request, null);

        return $query->pluck('customers.id')->sort()->values()->all();
    }

    private function exportCsv(array $params, ?User $user = null): array
    {
        $response = $this->actingAs($user ?? $this->admin)
            ->get(route('admin.customers.export', $params))
            ->assertOk();

        $content = $response->streamedContent();
        $this->assertStringStartsWith("\xEF\xBB\xBF", $content);

        $lines = array_values(array_filter(explode("\n", substr($content, 3)), fn ($l) => $l !== ''));

        return array_map('str_getcsv', $lines);
    }

    public function test_seijin_year_range_filters_between_min_and_max(): void
    {
        $this->customer('A', 2026);
        $b = $this->customer('B', 2027);
        $c = $this->customer('C', 2028);
        $this->customer('D', 2029);
        $this->customer('E', null);

        $this->assertSame([$b->id, $c->id], $this->searchIds([
            'coming_of_age_year_min' => 2027,
            'coming_of_age_year_max' => 2028,
        ]));
    }

    public function test_seijin_year_min_only_and_max_only(): void
    {
        $a = $this->customer('A', 2026);
        $b = $this->customer('B', 2027);
        $c = $this->customer('C', 2028);
        $this->customer('E', null);

        $this->assertSame([$b->id, $c->id], $this->searchIds(['coming_of_age_year_min' => 2027]));
        $this->assertSame([$a->id, $b->id], $this->searchIds(['coming_of_age_year_max' => 2027]));
    }

    public function test_index_passes_seijin_year_filters_and_export_columns(): void
    {
        $this->customer('A', 2027);

        $this->actingAs($this->admin)
            ->get(route('admin.customers.index', ['coming_of_age_year_min' => 2027, 'coming_of_age_year_max' => 2028]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('filters.coming_of_age_year_min', '2027')
                ->where('filters.coming_of_age_year_max', '2028')
                ->where('customers.total', 1)
                ->has('exportColumnOptions.coming_of_age_year')
                ->has('exportDefaultColumns'));
    }

    public function test_export_outputs_filtered_customers_with_selected_columns_and_logs(): void
    {
        $plan = Plan::create(['name' => '振袖プランA', 'code' => 'plan-a', 'is_active' => true]);
        $b = $this->customer('佐藤', 2027);
        $this->customer('田中', 2026);
        foreach ([['2026-01-10', 100000], ['2026-03-01', 250000]] as [$date, $amount]) {
            Contract::create([
                'customer_id' => $b->id, 'shop_id' => $this->shop->id, 'plan_id' => $plan->id,
                'contract_date' => $date, 'kimono_type' => '振袖', 'warranty_flag' => false,
                'total_amount' => $amount, 'status' => '成約',
            ]);
        }

        $rows = $this->exportCsv([
            'customer_shop_id' => 'all',
            'coming_of_age_year_min' => 2027,
            'columns' => ['name', 'coming_of_age_year', 'shop', 'contract_count', 'latest_contract_amount', 'latest_contract_plan', 'unknown'],
        ]);

        $this->assertSame([
            ['顧客名', '成人年度', '担当店舗', '成約件数', '最新成約金額', '最新成約プラン'],
            ['佐藤', '2027', '岡山店', '2', '250000', '振袖プランA'],
        ], $rows);

        $log = CsvExportLog::sole();
        $this->assertSame('customers', $log->target);
        $this->assertSame(1, $log->row_count);
        $this->assertSame($this->admin->id, $log->user_id);
    }

    public function test_export_escapes_formula_like_values(): void
    {
        $this->customer('=HYPERLINK("x")', 2027);

        $rows = $this->exportCsv(['customer_shop_id' => 'all', 'columns' => ['name']]);

        $this->assertSame("'=HYPERLINK(\"x\")", $rows[1][0]);
    }

    public function test_export_is_limited_to_visible_shop_group_for_staff(): void
    {
        $mine = Shop::create(['name' => '福井店', 'is_active' => true, 'group_key' => 'fukui']);
        $other = Shop::create(['name' => '金沢店', 'is_active' => true, 'group_key' => 'kanazawa']);
        $staff = User::factory()->create(['attendance_role' => 'staff']);
        $staff->shops()->attach($mine->id);
        $this->customer('自店の顧客', 2027, $mine);
        $this->customer('他店の顧客', 2027, $other);

        $rows = $this->exportCsv(['customer_shop_id' => 'all', 'columns' => ['name']], $staff);

        $this->assertSame([['顧客名'], ['自店の顧客']], $rows);
    }

    public function test_export_requires_valid_columns(): void
    {
        $this->actingAs($this->admin)
            ->get(route('admin.customers.export', ['columns' => ['unknown']]))
            ->assertStatus(422);

        $this->actingAs($this->admin)
            ->getJson(route('admin.customers.export'))
            ->assertStatus(422);

        $this->assertSame(0, CsvExportLog::count());
    }
}
