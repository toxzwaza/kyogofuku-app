<?php

namespace App\Console\Commands;

use App\Models\CreditCompany;
use App\Models\CreditContract;
use App\Models\Customer;
use App\Models\CustomerTag;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * credit:export で書き出したクレジットのデータを読み込む（本番へ移す用）。
 *
 *  - クレジットの契約が1件でもある DB には読み込まない（二重に入れないため。最初の1回だけ使う）
 *  - 取り込みで作ったお客様は、この DB で新しい番号を付けて作り直し、「クレジット」タグを付ける
 *  - 前からいるお客様は、番号と名前（空白を除いて比べる）が合うときだけ結び付ける。1人でも合わなければ何も書かずに止まる
 *  - 信販会社は名前で結び付ける。無い会社があれば止まる
 *  - スタッフ（担当・確認した人など）は番号と名前が合うときだけ結び付け、合わなければ空にする（担当の表記 staff_name は残る）
 *  - 全部を1つのトランザクションで入れ、入れた件数がファイルの件数と違えば元に戻す
 *  - --dry-run は確認と件数だけで、何も書かない
 */
class LoadCreditData extends Command
{
    protected $signature = 'credit:load
        {path : credit:export で書き出した JSON}
        {--dry-run : 確かめて件数を出すだけで、書き込まない}
        {--map= : お客様の番号の対応（書き出し元の番号,この DB の番号,区分）を CSV で書き出すパス}';

    protected $description = 'credit:export で書き出したクレジットのデータを読み込む（本番へ移す用）';

    public function handle(): int
    {
        ini_set('memory_limit', '1024M'); // 支払予定が13万件あるため
        $path = $this->argument('path');
        if (! is_file($path)) {
            $this->error("ファイルが見つかりません: {$path}");

            return self::FAILURE;
        }
        $data = json_decode(file_get_contents($path), true);
        if (($data['format'] ?? null) !== ExportCreditData::FORMAT || ($data['version'] ?? null) !== ExportCreditData::VERSION) {
            $this->error('credit:export で書き出したファイルではありません（または形式の版が違います）。');

            return self::FAILURE;
        }

        // 1. まだ何も入っていないこと
        if (($n = CreditContract::withTrashed()->count()) > 0) {
            $this->error("この DB にはもうクレジットの契約が {$n} 件あります。二重に入るので読み込みません。");

            return self::FAILURE;
        }

        $errors = [];

        // 2. ファイルの中の件数が合っていること（壊れていないか）
        $fileCounts = $data['counts'] ?? [];
        $calc = $this->countFile($data);
        foreach ($calc as $k => $v) {
            if (($fileCounts[$k] ?? null) !== $v) {
                $errors[] = "ファイルの件数が合いません（{$k}：記録 ".($fileCounts[$k] ?? '-')."／数えると {$v}）";
            }
        }

        $keys = collect($data['customers'])->pluck('key')->flip();
        $orphans = collect($data['contracts'])->pluck('customer_key')->unique()->reject(fn ($k) => isset($keys[$k]));
        if ($orphans->isNotEmpty()) {
            $errors[] = 'お客様の無い契約があります（'.$orphans->implode('、').'）';
        }

        // 3. 信販会社
        $companyIds = CreditCompany::query()->pluck('id', 'name');
        $missingCompanies = collect($data['contracts'])->pluck('company')->filter()->unique()
            ->reject(fn ($name) => isset($companyIds[$name]))->values();
        foreach ($missingCompanies as $name) {
            $errors[] = "信販会社がありません：{$name}";
        }

        // 4. 前からいるお客様
        $existing = collect($data['customers'])->where('kind', 'existing');
        $found = Customer::query()->whereIn('id', $existing->pluck('id'))->get(['id', 'name'])->keyBy('id');
        $mismatch = [];
        foreach ($existing as $c) {
            $here = $found[$c['id']] ?? null;
            if (! $here) {
                $mismatch[] = [$c['id'], $c['name'], '（この DB にいない）'];
            } elseif (self::nameKey($here->name) !== self::nameKey($c['name'])) {
                $mismatch[] = [$c['id'], $c['name'], $here->name];
            }
        }
        if ($mismatch) {
            $this->table(['お客様の番号', 'ファイルの名前', 'この DB の名前'], $mismatch);
            $errors[] = '前からいるお客様のうち '.count($mismatch).' 人が、番号と名前で結び付けられません。';
        }

        // 5. スタッフ（合わない人は空にする。止めない）
        $userNames = User::query()->pluck('name', 'id');
        $unmatchedUsers = [];
        $resolveUser = function (?array $ref) use ($userNames, &$unmatchedUsers): ?int {
            if (! $ref) {
                return null;
            }
            if (isset($userNames[$ref['id']]) && self::nameKey($userNames[$ref['id']]) === self::nameKey((string) $ref['name'])) {
                return (int) $ref['id'];
            }
            $unmatchedUsers[$ref['id'].'：'.$ref['name']] = ($unmatchedUsers[$ref['id'].'：'.$ref['name']] ?? 0) + 1;

            return null;
        };
        foreach ($data['contracts'] as $k) {
            foreach (['staff_user', 'confirmed_by', 'created_by', 'updated_by'] as $f) {
                $resolveUser($k[$f] ?? null);
            }
            foreach ($k['payments'] as $p) {
                $resolveUser($p['paid_checked_by'] ?? null);
            }
        }

        $this->table(['項目', '件数'], ExportCreditData::countRows($calc));
        $tagName = $data['tag'] ?? ImportCreditFromJson::TAG_NAME;
        $this->line('「'.$tagName.'」タグ：'.(CustomerTag::query()->where('name', $tagName)->exists() ? 'あり' : '無いので作る'));
        if ($unmatchedUsers) {
            $this->warn('番号と名前が合わないスタッフ（結び付けずに空にします。担当の表記は残ります）：');
            foreach ($unmatchedUsers as $who => $n) {
                $this->line("  {$who}（{$n} か所）");
            }
        }

        if ($errors) {
            foreach ($errors as $e) {
                $this->error($e);
            }
            $this->error('何も書き込んでいません。');

            return self::FAILURE;
        }

        if ($this->option('dry-run')) {
            $this->info('dry-run：確認は済みました。何も書き込んでいません（使ったメモリ '.round(memory_get_peak_usage(true) / 1048576).' MB）。');

            return self::SUCCESS;
        }

        // 6. 読み込み
        $map = [];
        try {
            DB::transaction(function () use ($data, $companyIds, $resolveUser, $tagName, $calc, &$map) {
                $tag = CustomerTag::firstOrCreate(['name' => $tagName], ['is_active' => true]);
                foreach ($data['customers'] as $c) {
                    if ($c['kind'] === 'existing') {
                        $map[$c['key']] = ['from' => $c['id'], 'to' => $c['id'], 'kind' => 'existing'];
                        continue;
                    }
                    $customer = Customer::create(['name' => $c['name']]);
                    $customer->tags()->syncWithoutDetaching([$tag->id]);
                    $map[$c['key']] = ['from' => (int) substr($c['key'], 1), 'to' => $customer->id, 'kind' => 'new'];
                }

                $bar = $this->output->createProgressBar(count($data['contracts']));
                foreach ($data['contracts'] as $k) {
                    $contractId = DB::table('credit_contracts')->insertGetId([
                        'customer_id' => $map[$k['customer_key']]['to'],
                        'credit_company_id' => $k['company'] ? $companyIds[$k['company']] : null,
                        'contract_date' => $k['contract_date'],
                        'item_name' => $k['item_name'],
                        'principal' => $k['principal'],
                        'fee' => $k['fee'],
                        'staff_user_id' => $resolveUser($k['staff_user']),
                        'staff_name' => $k['staff_name'],
                        'status' => $k['status'],
                        'is_provisional' => $k['is_provisional'],
                        'provisional_note' => $k['provisional_note'],
                        'confirmed_at' => $k['confirmed_at'],
                        'confirmed_by' => $resolveUser($k['confirmed_by']),
                        'note' => $k['note'],
                        'source_file' => $k['source_file'],
                        'created_by' => $resolveUser($k['created_by']),
                        'updated_by' => $resolveUser($k['updated_by']),
                        'created_at' => $k['created_at'],
                        'updated_at' => $k['updated_at'],
                        'deleted_at' => $k['deleted_at'],
                    ]);
                    $rows = array_map(fn ($p) => [
                        'credit_contract_id' => $contractId,
                        'due_month' => $p['due_month'],
                        'amount' => $p['amount'],
                        'is_paid' => $p['is_paid'],
                        'paid_checked_at' => $p['paid_checked_at'],
                        'paid_checked_by' => $resolveUser($p['paid_checked_by']),
                        'note' => $p['note'],
                        'created_at' => $p['created_at'],
                        'updated_at' => $p['updated_at'],
                    ], $k['payments']);
                    foreach (array_chunk($rows, 500) as $chunk) {
                        DB::table('credit_payments')->insert($chunk);
                    }
                    $bar->advance();
                }
                $bar->finish();
                $this->newLine();

                // 入れた件数がファイルと同じこと（違えば元に戻す）
                $after = [
                    'contracts' => DB::table('credit_contracts')->count(),
                    'deleted_contracts' => DB::table('credit_contracts')->whereNotNull('deleted_at')->count(),
                    'provisional' => DB::table('credit_contracts')->where('is_provisional', true)->count(),
                    'payments' => DB::table('credit_payments')->count(),
                    'principal_sum' => (int) DB::table('credit_contracts')->sum('principal'),
                    'payment_sum' => (int) DB::table('credit_payments')->sum('amount'),
                    'customers' => DB::table('credit_contracts')->distinct()->count('customer_id'),
                ];
                $expect = array_intersect_key($calc, $after) + ['customers' => $calc['new_customers'] + $calc['existing_customers']];
                foreach ($expect as $key => $v) {
                    if ($after[$key] !== $v) {
                        throw new \RuntimeException("入れた件数がファイルと違います（{$key}：ファイル {$v}／入れた {$after[$key]}）");
                    }
                }
            });
        } catch (\Throwable $e) {
            $this->error($e->getMessage());
            $this->error('元に戻しました（何も書き込んでいません）。');

            return self::FAILURE;
        }

        if ($mapPath = $this->option('map')) {
            $fh = fopen($mapPath, 'w');
            fputcsv($fh, ['書き出し元の番号', 'この DB の番号', '区分']);
            foreach ($map as $m) {
                fputcsv($fh, [$m['from'], $m['to'], $m['kind'] === 'new' ? '新しく作った' : '前からいる']);
            }
            fclose($fh);
            $this->line("お客様の番号の対応：{$mapPath}");
        }

        $this->info('読み込みました。件数はファイルと同じです（使ったメモリ '.round(memory_get_peak_usage(true) / 1048576).' MB）。');

        return self::SUCCESS;
    }

    /** ファイルの中身から件数を数える */
    private function countFile(array $data): array
    {
        $contracts = collect($data['contracts'] ?? []);
        $customers = collect($data['customers'] ?? []);
        $payments = $contracts->flatMap(fn ($k) => $k['payments']);

        return [
            'new_customers' => $customers->where('kind', 'new')->count(),
            'existing_customers' => $customers->where('kind', 'existing')->count(),
            'contracts' => $contracts->count(),
            'deleted_contracts' => $contracts->whereNotNull('deleted_at')->count(),
            'provisional' => $contracts->where('is_provisional', true)->count(),
            'payments' => $payments->count(),
            'principal_sum' => (int) $contracts->sum('principal'),
            'payment_sum' => (int) $payments->sum('amount'),
        ];
    }

    /** 名前を比べるための形（空白を除く） */
    public static function nameKey(string $name): string
    {
        return preg_replace('/[\s　]+/u', '', $name);
    }
}
