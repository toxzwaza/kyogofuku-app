<?php

namespace Tests\Feature\Admin;

use App\Models\CreditContract;
use App\Models\CreditPayment;
use App\Models\Customer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CreditImportTest extends TestCase
{
    use RefreshDatabase;

    private string $dir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->dir = sys_get_temp_dir() . '/credit-import-test-' . uniqid();
        mkdir($this->dir);
    }

    protected function tearDown(): void
    {
        array_map('unlink', glob($this->dir . '/*'));
        rmdir($this->dir);
        parent::tearDown();
    }

    private function contract(array $override = []): array
    {
        return array_merge([
            'col' => 'C',
            'contract_date' => '2026-08-10',
            'contract_date_raw' => null,
            'item_name' => '訪問着',
            'principal' => 300000,
            'fee' => 0,
            // 2026-10, 11, 12（今日=2026-11-08 → 10月だけ過去）
            'payments' => [
                ['month' => '2026-10', 'amount' => 100000],
                ['month' => '2026-11', 'amount' => 100000],
                ['month' => '2026-12', 'amount' => 100000],
            ],
            'below' => ['自社'],
            'memo' => [],
        ], $override);
    }

    private function writeJson(array $files): string
    {
        $path = $this->dir . '/parsed.json';
        file_put_contents($path, json_encode($files, JSON_UNESCAPED_UNICODE));

        return $path;
    }

    private function file(string $src, string $name, array $contracts, array $extra = []): array
    {
        return array_merge([
            'source_file' => $src, 'kind' => 'customer', 'warnings' => [],
            'customer_name' => $name, 'staff' => '石倉', 'name_note' => [], 'sheet_name' => $name,
            'contracts' => $contracts,
        ], $extra);
    }

    private function run_(string $json, array $opts = []): \Illuminate\Testing\PendingCommand
    {
        return $this->artisan('credit:import', array_merge([
            'json' => $json, '--out' => $this->dir, '--today' => '2026-11-08',
        ], $opts));
    }

    public function test_dry_run_writes_report_only(): void
    {
        $json = $this->writeJson([
            $this->file('あ行/あ/新規　花子(石倉）.xlsx', '新規 花子', [$this->contract()]),
            ['source_file' => 'あ行/あ/支払一覧表原紙.xlsx', 'kind' => 'blank', 'warnings' => []],
        ]);

        $this->run_($json, ['--dry-run' => true])->assertSuccessful();

        $this->assertSame(0, CreditContract::count());
        $this->assertFalse(Customer::where('name', '新規　花子')->exists());
        $csv = file_get_contents($this->dir . '/20261108_クレジット取り込み_ファイル別_dryrun.csv');
        $this->assertStringContainsString('新規追加', $csv);
        $this->assertStringContainsString('取り込まない', $csv);
    }

    public function test_import_creates_customer_contract_and_paid_past_months(): void
    {
        $staff = User::factory()->create(['name' => '石倉まち子']);
        $json = $this->writeJson([
            $this->file('あ行/あ/新規　花子(石倉）.xlsx', '新規 花子', [
                $this->contract(),
                $this->contract(['col' => 'D', 'below' => ['ﾆｯｾﾝﾚﾝ'], 'payments' => [['month' => '2026-09', 'amount' => 5000]]]),
                $this->contract(['col' => 'E', 'below' => ['XYZ'], 'payments' => [], 'memo' => ['2025-04：7.4.11トワライズ組替']]),
            ]),
        ]);

        $this->run_($json)->assertSuccessful();

        $customer = Customer::where('name', '新規　花子')->firstOrFail();
        $this->assertNull($customer->shop_id);
        $this->assertTrue($customer->tags()->where('name', 'クレジット')->exists());

        [$own, $agency, $noPlan] = CreditContract::orderBy('id')->get()->all();
        $this->assertSame('自社', $own->company->name);
        $this->assertSame($staff->id, $own->staff_user_id);
        $this->assertSame('石倉', $own->staff_name);
        $this->assertSame('あ行/あ/新規　花子(石倉）.xlsx#C', $own->source_file);
        $this->assertSame(CreditContract::STATUS_ACTIVE, $own->status);
        $this->assertTrue($own->payments()->where('due_month', '2026-10-01')->first()->is_paid);
        $this->assertFalse($own->payments()->where('due_month', '2026-11-01')->first()->is_paid);

        $this->assertSame('日専連', $agency->company->name);
        $this->assertSame(CreditContract::STATUS_COMPLETED, $agency->status);

        $this->assertNull($noPlan->credit_company_id);
        $this->assertSame(CreditContract::STATUS_CANCELLED, $noPlan->status);
        $this->assertFalse($noPlan->is_provisional, '解約は信販会社が空でも仮登録にしない');
        $this->assertFalse($own->is_provisional);
        $this->assertStringContainsString('XYZ', $noPlan->note);

        // 2回目は二重に取り込まない
        $this->run_($json)->assertSuccessful();
        $this->assertSame(3, CreditContract::count());
        $this->assertSame(4, CreditPayment::count());
    }

    public function test_same_name_customer_is_not_linked_without_decision(): void
    {
        $existing = Customer::create(['name' => '山田　花子']);
        $json = $this->writeJson([
            $this->file('や行/や/山田　花子(石倉）.xlsx', '山田 花子', [$this->contract()]),
            $this->file('や行/や/山田　花子(平田）.xlsx', '山田 花子', [$this->contract()]),
        ]);

        // 指定なし：取り込まない
        $this->run_($json)->assertSuccessful();
        $this->assertSame(0, CreditContract::count());

        // 1つは既存に紐付け、1つは新規
        $links = $this->dir . '/links.csv';
        file_put_contents($links, "source_file,customer_id\n"
            . "や行/や/山田　花子(石倉）.xlsx,{$existing->id}\n"
            . "や行/や/山田　花子(平田）.xlsx,new\n");
        $this->run_($json, ['--links' => $links])->assertSuccessful();

        $this->assertSame(1, $existing->creditContracts()->count());
        $this->assertSame(2, Customer::where('name', '山田　花子')->count());
    }

    public function test_same_name_files_wait_for_decision(): void
    {
        $json = $this->writeJson([
            $this->file('は行/は/林　恭子(石倉）.xlsx', '林 恭子', [$this->contract()]),
            $this->file('は行/は/林　恭子(平田）.xlsx', '林 恭子', [$this->contract()]),
        ]);

        $this->run_($json)->assertSuccessful();
        $this->assertSame(0, CreditContract::count());

        $links = $this->dir . '/links.csv';
        file_put_contents($links, "source_file,customer_id\nは行/は/林　恭子(石倉）.xlsx,new\nは行/は/林　恭子(平田）.xlsx,new\n");
        $this->run_($json, ['--links' => $links])->assertSuccessful();
        $this->assertSame(2, Customer::where('name', '林　恭子')->count());
    }

    public function test_fixes_csv_overrides_company_date_months_status_and_excludes(): void
    {
        $src = 'か行/か/加藤　花子(石倉）.xlsx';
        $json = $this->writeJson([
            $this->file($src, '加藤 花子', [
                $this->contract(['col' => 'C', 'below' => [], 'contract_date' => null, 'contract_date_raw' => '1.12.2']),
                $this->contract(['col' => 'D', 'payments' => [['month' => '2029-01', 'amount' => 1000], ['month' => '2029-02', 'amount' => 1000]]]),
                $this->contract(['col' => 'E']),
                $this->contract(['col' => 'F']),
                $this->contract(['col' => 'G', 'principal' => 999999]),
            ]),
            $this->file('さ行/さ/佐藤　花子(石倉）.xlsx', '佐藤 花子', [$this->contract()]),
        ]);
        $fixes = $this->dir . '/fixes.csv';
        file_put_contents($fixes, "\xEF\xBB\xBF取り込み元,項目,値,理由\n"
            . "{$src}#C,company,CBS,回答\n"
            . "{$src}#C,contract_date,2019-12-02,回答\n"
            . "{$src},sheet,Sheet2,解析側で使う\n"
            . "{$src}#D,first_month,2027-01,回答\n"
            . "{$src}#E,status,completed,回答\n"
            . "{$src}#F,status,cancelled,回答\n"
            . "{$src}#G,exclude,1,合計金額欄\n"
            . "{$src}#Z,exclude,1,書き間違い\n"
            . "さ行/さ/佐藤　花子(石倉）.xlsx#C,exclude,1,契約ではない\n");

        $this->run_($json, ['--fixes' => $fixes])->assertSuccessful()
            ->expectsOutputToContain('使われなかった修正指定：' . $src . '#Z');

        $this->assertFalse(Customer::where('name', '佐藤　花子')->exists());
        $byCol = CreditContract::all()->keyBy(fn ($c) => substr($c->source_file, -1));
        $this->assertSame(['C', 'D', 'E', 'F'], $byCol->keys()->sort()->values()->all());

        $this->assertSame('CBS', $byCol['C']->company->name);
        $this->assertSame('2019-12-02', $byCol['C']->contract_date->toDateString());
        $this->assertStringContainsString('契約日（原文）：1.12.2', $byCol['C']->note);
        $this->assertStringContainsString('修正指定（company＝CBS）', $byCol['C']->note);

        $this->assertSame(['2027-01-01', '2027-02-01'], $byCol['D']->payments()->orderBy('due_month')->pluck('due_month')
            ->map(fn ($d) => substr((string) $d, 0, 10))->all());

        $this->assertSame(CreditContract::STATUS_COMPLETED, $byCol['E']->status);
        $this->assertSame(0, $byCol['E']->payments()->where('is_paid', false)->count());
        $this->assertSame(CreditContract::STATUS_CANCELLED, $byCol['F']->status);
    }

    public function test_fixes_csv_with_unknown_company_stops_before_import(): void
    {
        $json = $this->writeJson([$this->file('あ行/あ/新規　花子(石倉）.xlsx', '新規 花子', [$this->contract()])]);
        $fixes = $this->dir . '/fixes.csv';
        file_put_contents($fixes, "取り込み元,項目,値,理由\nあ行/あ/新規　花子(石倉）.xlsx#C,company,存在しない信販,回答\n");

        $this->run_($json, ['--fixes' => $fixes])->assertFailed();
        $this->assertSame(0, CreditContract::count());
    }

    public function test_update_adds_new_columns_and_fixes_changed_ones(): void
    {
        $src = 'あ行/あ/更新　花子(石倉）.xlsx';
        $this->run_($this->writeJson([
            $this->file($src, '更新 花子', [
                $this->contract(),
                $this->contract(['col' => 'D', 'principal' => 200000]),
                $this->contract(['col' => 'E', 'below' => ['XYZ']]),
                $this->contract(['col' => 'F', 'principal' => 50000]),
                $this->contract(['col' => 'G']),
            ]),
        ]))->assertSuccessful();
        $c = fn ($col) => CreditContract::where('source_file', "{$src}#{$col}")->firstOrFail();
        $c('E')->update(['provisional_note' => '確認中のこと']);
        $c('F')->update(['is_provisional' => false, 'confirmed_at' => now()]);
        $staff = User::factory()->create();
        $c('C')->payments()->where('due_month', '2026-11-01')->update(['is_paid' => true, 'paid_checked_by' => $staff->id, 'paid_checked_at' => now()]);
        $customerId = $c('C')->customer_id;

        // Excel が直された：C は支払予定が変わり、D は同じ、E は信販会社が決まり、F は確認済み、G は消え、H が増えた
        $json = $this->writeJson([
            $this->file($src, '更新 花子', [
                $this->contract(['payments' => [
                    ['month' => '2026-10', 'amount' => 100000],
                    ['month' => '2026-11', 'amount' => 50000],
                    ['month' => '2026-12', 'amount' => 50000],
                    ['month' => '2027-01', 'amount' => 100000],
                ]]),
                $this->contract(['col' => 'D', 'principal' => 200000]),
                $this->contract(['col' => 'E', 'below' => ['JACCS']]),
                $this->contract(['col' => 'F', 'principal' => 60000]),
                $this->contract(['col' => 'H', 'principal' => 120000, 'contract_date' => '2026-11-15']),
            ]),
        ]);

        // --update なしでは何もしない
        $this->run_($json)->assertSuccessful();
        $this->assertSame(5, CreditContract::count());

        $this->run_($json, ['--update' => true, '--dry-run' => true])->assertSuccessful();
        $this->assertSame(5, CreditContract::count());
        $csv = file_get_contents($this->dir . '/20261108_クレジット取り込み_契約別_dryrun.csv');
        $this->assertStringContainsString('変わった所：支払予定', $csv);
        $this->assertStringContainsString('#H', $csv);

        $this->run_($json, ['--update' => true])->assertSuccessful();

        $this->assertSame(6, CreditContract::count());
        $h = $c('H');
        $this->assertSame($customerId, $h->customer_id, '増えた列は同じお客様の契約');
        $this->assertSame(120000, $h->principal);

        $this->assertSame([100000, 50000, 50000, 100000], $c('C')->payments->pluck('amount')->map(fn ($v) => (int) $v)->all());
        $nov = $c('C')->payments()->where('due_month', '2026-11-01')->first();
        $this->assertTrue((bool) $nov->is_paid, '画面で支払済にした月はそのまま');
        $this->assertSame($staff->id, $nov->paid_checked_by);
        $this->assertFalse((bool) $c('C')->payments()->where('due_month', '2027-01-01')->first()->is_paid);

        $this->assertSame('JACCS', $c('E')->company->name);
        $this->assertTrue($c('E')->is_provisional, '仮登録の印はそのまま');
        $this->assertSame('確認中のこと', $c('E')->provisional_note);

        $this->assertSame(50000, $c('F')->principal, '確認済みの契約は直さない');
        $this->assertSame(1, CreditContract::where('source_file', "{$src}#G")->count(), 'Excel から消えた列は残す');

        $file = file_get_contents($this->dir . '/20261108_クレジット取り込み_ファイル別.csv');
        $this->assertStringContainsString('G列：Excel に無くなった', $file);
        $this->assertStringContainsString('F列：Excel が変わったが、確認済み', $file);

        // もう一度流しても変わらない
        $this->run_($json, ['--update' => true])->assertSuccessful();
        $this->assertSame(6, CreditContract::count());
        $this->assertSame(4, $c('C')->payments()->count());
    }

    public function test_update_follows_renamed_files_and_columns(): void
    {
        $this->run_($this->writeJson([
            $this->file('な行/に/西村　太郎(佐々本).xlsx', '西村 太郎', [$this->contract()], ['staff' => '佐々本']),
            $this->file('あ行/う/内田　花子(梨子木).xlsx', '内田 花子', [$this->contract()], ['staff' => '梨子木']),
        ]))->assertSuccessful();
        $nishimura = CreditContract::where('source_file', 'like', '%西村%')->firstOrFail();
        $uchida = CreditContract::where('source_file', 'like', '%内田%')->firstOrFail();

        $renames = $this->dir . '/renames.csv';
        file_put_contents($renames, "旧取り込み元,新取り込み元\n"
            . "な行/に/西村　太郎(佐々本).xlsx#C,な行/に/西村　太郎(朝日).xlsx#D\n"
            . "あ行/う/内田　花子(梨子木).xlsx,あ行/う/内田　花子(佑奈)(梨子木).xlsx\n");
        $json = $this->writeJson([
            $this->file('な行/に/西村　太郎(朝日).xlsx', '西村 太郎', [$this->contract(['col' => 'C', 'principal' => 99000]), $this->contract(['col' => 'D'])], ['staff' => '朝日']),
            $this->file('あ行/う/内田　花子(佑奈)(梨子木).xlsx', '内田 花子', [$this->contract()], ['staff' => '梨子木']),
        ]);

        $this->run_($json, ['--renames' => $renames])->assertFailed();
        $this->run_($json, ['--update' => true, '--renames' => $renames])->assertSuccessful();

        $this->assertSame(3, CreditContract::count());
        $this->assertSame('な行/に/西村　太郎(朝日).xlsx#D', $nishimura->fresh()->source_file);
        $this->assertSame('朝日', $nishimura->fresh()->staff_name);
        $this->assertSame('あ行/う/内田　花子(佑奈)(梨子木).xlsx#C', $uchida->fresh()->source_file);
        $added = CreditContract::where('source_file', 'な行/に/西村　太郎(朝日).xlsx#C')->firstOrFail();
        $this->assertSame($nishimura->customer_id, $added->customer_id);
        $this->assertSame(1, Customer::where('name', '内田　花子')->count(), '名前が変わったファイルでお客様を増やさない');
    }
}
