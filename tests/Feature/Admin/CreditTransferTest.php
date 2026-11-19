<?php

namespace Tests\Feature\Admin;

use App\Models\CreditCompany;
use App\Models\CreditContract;
use App\Models\CreditPayment;
use App\Models\Customer;
use App\Models\CustomerTag;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/** credit:export → credit:load（ローカルのクレジットのデータを本番へ移す） */
class CreditTransferTest extends TestCase
{
    use RefreshDatabase;

    private string $dir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->dir = sys_get_temp_dir() . '/credit-transfer-test-' . uniqid();
        mkdir($this->dir);
    }

    protected function tearDown(): void
    {
        array_map('unlink', glob($this->dir . '/*'));
        rmdir($this->dir);
        parent::tearDown();
    }

    /** ローカルの状態：前からいるお客様1人＋取り込みで作ったお客様2人 */
    private function seedLocal(User $staff): array
    {
        $existing = Customer::create(['name' => '前川　一郎']);
        $existing->forceFill(['created_at' => '2026-10-01 10:00:00'])->saveQuietly();

        $tag = CustomerTag::firstOrCreate(['name' => 'クレジット'], ['is_active' => true]);
        $new1 = Customer::create(['name' => '新規　花子']);
        $new2 = Customer::create(['name' => '新規　次郎']);
        $new1->tags()->syncWithoutDetaching([$tag->id]);
        $new2->tags()->syncWithoutDetaching([$tag->id]);

        $own = CreditCompany::where('name', '自社')->value('id');
        $jaccs = CreditCompany::where('name', 'JACCS')->value('id');
        $mk = function (Customer $c, ?int $company, array $extra = [], int $months = 3) use ($staff) {
            $k = CreditContract::create(array_merge([
                'customer_id' => $c->id, 'credit_company_id' => $company, 'contract_date' => '2026-08-10',
                'item_name' => '訪問着', 'principal' => 300000, 'fee' => 0,
                'staff_user_id' => $staff->id, 'staff_name' => '石倉', 'status' => 'active',
                'source_file' => $c->name . '.xlsx#C',
            ], $extra));
            for ($i = 0; $i < $months; $i++) {
                CreditPayment::create([
                    'credit_contract_id' => $k->id, 'due_month' => sprintf('2026-%02d-01', 10 + $i),
                    'amount' => 100000, 'is_paid' => $i === 0,
                    'paid_checked_at' => $i === 0 ? '2026-10-31 10:00:00' : null,
                    'paid_checked_by' => $i === 0 ? $staff->id : null,
                ]);
            }

            return $k;
        };
        $mk($existing, $own);
        $mk($new1, $jaccs);
        $mk($new1, null, ['is_provisional' => true, 'provisional_note' => '信販会社がわからない', 'source_file' => '新規　花子.xlsx#D'], 2);
        $mk($new2, $own, ['status' => 'cancelled'], 1)->delete(); // 削除済み

        return [$existing, $new1, $new2];
    }

    /** 本番の状態にする：クレジットのデータと、取り込みで作ったお客様を消す。番号がずれるようにお客様を1人足す */
    private function toFreshTarget(array $newCustomers): void
    {
        DB::table('credit_payments')->delete();
        DB::table('credit_contracts')->delete();
        $ids = collect($newCustomers)->pluck('id');
        DB::table('customer_tag_assignments')->whereIn('customer_id', $ids)->delete();
        DB::table('customer_stages')->whereIn('customer_id', $ids)->delete();
        DB::table('referral_points')->whereIn('customer_id', $ids)->delete();
        DB::table('customers')->whereIn('id', $ids)->delete();
        Customer::create(['name' => '本番で増えた　お客様']);
    }

    private function export(): string
    {
        $path = $this->dir . '/export.json';
        $this->artisan('credit:export', ['path' => $path])->assertSuccessful();

        return $path;
    }

    public function test_export_then_load_reproduces_data_with_new_customer_ids(): void
    {
        $staff = User::factory()->create(['name' => '石倉まち子']);
        [$existing, $new1, $new2] = $this->seedLocal($staff);
        $path = $this->export();

        $data = json_decode(file_get_contents($path), true);
        $this->assertSame(2, $data['counts']['new_customers']);
        $this->assertSame(1, $data['counts']['existing_customers']);
        $this->assertSame(4, $data['counts']['contracts']);
        $this->assertSame(1, $data['counts']['deleted_contracts']);
        $this->assertSame(1, $data['counts']['provisional']);
        $this->assertSame(9, $data['counts']['payments']);

        // 同じパスには上書きしない
        $this->artisan('credit:export', ['path' => $path])->assertFailed();

        $this->toFreshTarget([$new1, $new2]);

        // dry-run は何も書かない
        $customersBefore = Customer::count();
        $this->artisan('credit:load', ['path' => $path, '--dry-run' => true])->assertSuccessful();
        $this->assertSame(0, CreditContract::withTrashed()->count());
        $this->assertSame($customersBefore, Customer::count());

        $map = $this->dir . '/map.csv';
        $this->artisan('credit:load', ['path' => $path, '--map' => $map])->assertSuccessful();

        $this->assertSame(4, CreditContract::withTrashed()->count());
        $this->assertSame(3, CreditContract::count());
        $this->assertSame(9, CreditPayment::count());

        // 前からいるお客様は同じ番号
        $this->assertSame(1, CreditContract::where('customer_id', $existing->id)->count());

        // 取り込みで作ったお客様は新しい番号で作り直し、タグ・紹介の基盤もできる
        $hanako = Customer::where('name', '新規　花子')->sole();
        $this->assertNotSame($new1->id, $hanako->id);
        $this->assertTrue($hanako->tags()->where('name', 'クレジット')->exists());
        $this->assertTrue(DB::table('customer_stages')->where('customer_id', $hanako->id)->exists());
        $this->assertSame(2, CreditContract::where('customer_id', $hanako->id)->count());

        $prov = CreditContract::where('source_file', '新規　花子.xlsx#D')->sole();
        $this->assertTrue($prov->is_provisional);
        $this->assertSame('信販会社がわからない', $prov->provisional_note);
        $this->assertNull($prov->credit_company_id);

        $jaccs = CreditContract::where('source_file', '新規　花子.xlsx#C')->sole();
        $this->assertSame('JACCS', $jaccs->company->name);
        $this->assertSame($staff->id, $jaccs->staff_user_id);
        $paid = $jaccs->payments()->where('is_paid', true)->sole();
        $this->assertSame($staff->id, $paid->paid_checked_by);
        $this->assertSame('2026-10-31 10:00:00', $paid->paid_checked_at->format('Y-m-d H:i:s'));

        $jiro = Customer::where('name', '新規　次郎')->sole();
        $this->assertTrue(CreditContract::onlyTrashed()->where('customer_id', $jiro->id)->exists());

        $csv = file_get_contents($map);
        $this->assertStringContainsString("{$new1->id},{$hanako->id},新しく作った", $csv);
        $this->assertStringContainsString("{$existing->id},{$existing->id},前からいる", $csv);

        // 2回目は読み込まない
        $this->artisan('credit:load', ['path' => $path])->assertFailed();
        $this->assertSame(4, CreditContract::withTrashed()->count());
    }

    public function test_load_stops_when_existing_customer_does_not_match(): void
    {
        $staff = User::factory()->create(['name' => '石倉まち子']);
        [$existing, $new1, $new2] = $this->seedLocal($staff);
        $path = $this->export();
        $this->toFreshTarget([$new1, $new2]);

        $existing->update(['name' => '別の　人']);
        $customersBefore = Customer::count();

        $this->artisan('credit:load', ['path' => $path])->assertFailed();
        $this->assertSame(0, CreditContract::withTrashed()->count());
        $this->assertSame($customersBefore, Customer::count());
    }

    public function test_load_clears_unmatched_staff_but_keeps_staff_name(): void
    {
        $staff = User::factory()->create(['name' => '石倉まち子']);
        [, $new1, $new2] = $this->seedLocal($staff);
        $path = $this->export();
        $this->toFreshTarget([$new1, $new2]);

        $staff->update(['name' => '別のスタッフ']);

        $this->artisan('credit:load', ['path' => $path])->assertSuccessful();
        $this->assertSame(0, CreditContract::withTrashed()->whereNotNull('staff_user_id')->count());
        $this->assertSame(4, CreditContract::withTrashed()->where('staff_name', '石倉')->count());
        $this->assertSame(0, CreditPayment::whereNotNull('paid_checked_by')->count());
    }

    public function test_load_rejects_broken_file(): void
    {
        $staff = User::factory()->create(['name' => '石倉まち子']);
        [, $new1, $new2] = $this->seedLocal($staff);
        $path = $this->export();
        $this->toFreshTarget([$new1, $new2]);

        $data = json_decode(file_get_contents($path), true);
        array_pop($data['contracts']);
        file_put_contents($path, json_encode($data, JSON_UNESCAPED_UNICODE));

        $this->artisan('credit:load', ['path' => $path])->assertFailed();
        $this->assertSame(0, CreditContract::withTrashed()->count());
    }
}
