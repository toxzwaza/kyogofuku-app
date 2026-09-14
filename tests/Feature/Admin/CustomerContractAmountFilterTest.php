<?php

namespace Tests\Feature\Admin;

use App\Models\Contract;
use App\Models\Customer;
use App\Models\Plan;
use App\Models\Shop;
use App\Queries\CustomerSearchQuery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

class CustomerContractAmountFilterTest extends TestCase
{
    use RefreshDatabase;

    private Shop $shop;

    private Plan $plan;

    protected function setUp(): void
    {
        parent::setUp();

        $this->shop = Shop::create(['name' => 'S', 'is_active' => true]);
        $this->plan = Plan::create(['name' => 'P', 'code' => 'test-plan', 'is_active' => true]);
    }

    private function makeCustomerWithContract(string $name, int $amount): Customer
    {
        $customer = Customer::create(['name' => $name, 'shop_id' => $this->shop->id]);
        Contract::create([
            'customer_id' => $customer->id,
            'shop_id' => $this->shop->id,
            'plan_id' => $this->plan->id,
            'contract_date' => '2026-01-10',
            'kimono_type' => '振袖',
            'warranty_flag' => false,
            'total_amount' => $amount,
        ]);

        return $customer;
    }

    private function searchIds(array $params): array
    {
        $request = Request::create('/admin/customers', 'GET', $params);
        ['query' => $query] = CustomerSearchQuery::build($request, null);

        return $query->pluck('customers.id')->sort()->values()->all();
    }

    public function test_amount_range_filters_customers_between_min_and_max(): void
    {
        $this->makeCustomerWithContract('A', 100000);
        $b = $this->makeCustomerWithContract('B', 200000);
        $this->makeCustomerWithContract('C', 300000);

        $ids = $this->searchIds([
            'contract_amount_min' => 150000,
            'contract_amount_max' => 250000,
        ]);

        $this->assertSame([$b->id], $ids);
    }

    public function test_min_only_filters_greater_than_or_equal(): void
    {
        $this->makeCustomerWithContract('A', 100000);
        $b = $this->makeCustomerWithContract('B', 200000);
        $c = $this->makeCustomerWithContract('C', 300000);

        $ids = $this->searchIds(['contract_amount_min' => 200000]);

        $this->assertSame(collect([$b->id, $c->id])->sort()->values()->all(), $ids);
    }

    public function test_max_only_filters_less_than_or_equal(): void
    {
        $a = $this->makeCustomerWithContract('A', 100000);
        $b = $this->makeCustomerWithContract('B', 200000);
        $this->makeCustomerWithContract('C', 300000);

        $ids = $this->searchIds(['contract_amount_max' => 200000]);

        $this->assertSame(collect([$a->id, $b->id])->sort()->values()->all(), $ids);
    }

    public function test_same_min_and_max_is_exact_match(): void
    {
        $this->makeCustomerWithContract('A', 100000);
        $b = $this->makeCustomerWithContract('B', 200000);
        $this->makeCustomerWithContract('C', 300000);

        $ids = $this->searchIds([
            'contract_amount_min' => 200000,
            'contract_amount_max' => 200000,
        ]);

        $this->assertSame([$b->id], $ids);
    }

    public function test_no_amount_params_returns_all(): void
    {
        $this->makeCustomerWithContract('A', 100000);
        $this->makeCustomerWithContract('B', 200000);

        $ids = $this->searchIds([]);

        $this->assertCount(2, $ids);
    }
}
