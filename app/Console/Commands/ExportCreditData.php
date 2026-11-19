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
 * クレジットのデータ（契約・支払予定と、取り込みで作ったお客様）を1つの JSON に書き出す。
 * 本番へは credit:load で読み込む（お客様の番号は本番で付け直す）。
 *
 *  - 取り込みで作ったお客様（new）：「クレジット」タグが付き、最初の契約の登録日以降に作られた人。名前とタグだけを持つ
 *  - それ以外のお客様（existing）：前からいるお客様。番号と名前で本番の同じ人に結び付ける
 *  - スタッフ（担当・確認した人など）は番号と名前で書き出し、本番で番号と名前が合うときだけ結び付ける
 *
 * ファイルにはお客様の名前が入るので、git に入れない。
 */
class ExportCreditData extends Command
{
    public const FORMAT = 'kyogofuku-credit-export';

    public const VERSION = 1;

    protected $signature = 'credit:export
        {path : 書き出す JSON のパス}
        {--new-since= : 取り込みで作ったお客様とみなす作成日（既定：最初の契約の登録日）}';

    protected $description = 'クレジットのデータを本番へ移すために JSON へ書き出す';

    public function handle(): int
    {
        ini_set('memory_limit', '1024M'); // 支払予定が13万件あるため
        $path = $this->argument('path');
        if (file_exists($path)) {
            $this->error("すでにファイルがあります（上書きしません）: {$path}");

            return self::FAILURE;
        }

        $contracts = CreditContract::withTrashed()->orderBy('id')->get();
        if ($contracts->isEmpty()) {
            $this->error('クレジットの契約がありません。');

            return self::FAILURE;
        }

        $newSince = $this->option('new-since')
            ? \Carbon\Carbon::parse($this->option('new-since'))->startOfDay()
            : $contracts->min('created_at')->copy()->startOfDay();
        $tag = CustomerTag::query()->where('name', ImportCreditFromJson::TAG_NAME)->first();
        $taggedIds = $tag ? DB::table('customer_tag_assignments')->where('customer_tag_id', $tag->id)->pluck('customer_id')->flip() : collect();

        $customerIds = $contracts->pluck('customer_id')->unique()->values();
        $customers = Customer::query()->whereIn('id', $customerIds)->orderBy('id')
            ->get(['id', 'name', 'guardian_name', 'created_at'])->keyBy('id');

        $companies = CreditCompany::query()->pluck('name', 'id');
        $userNames = User::query()->pluck('name', 'id');
        $userRef = function ($id) use ($userNames) {
            return $id === null ? null : ['id' => (int) $id, 'name' => $userNames[$id] ?? null];
        };

        $outCustomers = [];
        $counts = ['new_customers' => 0, 'existing_customers' => 0];
        foreach ($customerIds as $id) {
            $c = $customers[$id] ?? null;
            if (! $c) {
                $this->error("契約のお客様が見つかりません（お客様 id {$id}）");

                return self::FAILURE;
            }
            $isNew = isset($taggedIds[$id]) && $c->created_at && $c->created_at->gte($newSince);
            $outCustomers[] = $isNew
                ? ['key' => 'c'.$id, 'kind' => 'new', 'name' => $c->name]
                : ['key' => 'c'.$id, 'kind' => 'existing', 'id' => $id, 'name' => $c->name, 'guardian_name' => $c->guardian_name];
            $counts[$isNew ? 'new_customers' : 'existing_customers']++;
        }

        // 支払予定は件数が多いので、モデルにせず軽い形で読む
        $paymentsByContract = [];
        foreach (DB::table('credit_payments')->orderBy('credit_contract_id')->orderBy('due_month')->cursor() as $p) {
            $paymentsByContract[$p->credit_contract_id][] = $p;
        }

        $outContracts = [];
        $paymentCount = 0;
        $paymentSum = 0;
        foreach ($contracts as $k) {
            $payments = array_map(fn ($p) => [
                'due_month' => substr($p->due_month, 0, 10),
                'amount' => (int) $p->amount,
                'is_paid' => (bool) $p->is_paid,
                'paid_checked_at' => $p->paid_checked_at,
                'paid_checked_by' => $userRef($p->paid_checked_by),
                'note' => $p->note,
                'created_at' => $p->created_at,
                'updated_at' => $p->updated_at,
            ], $paymentsByContract[$k->id] ?? []);
            $paymentCount += count($payments);
            $paymentSum += array_sum(array_column($payments, 'amount'));

            $outContracts[] = [
                'customer_key' => 'c'.$k->customer_id,
                'company' => $k->credit_company_id ? ($companies[$k->credit_company_id] ?? null) : null,
                'contract_date' => $k->contract_date?->format('Y-m-d'),
                'item_name' => $k->item_name,
                'principal' => (int) $k->principal,
                'fee' => $k->fee === null ? null : (int) $k->fee,
                'staff_user' => $userRef($k->staff_user_id),
                'staff_name' => $k->staff_name,
                'status' => $k->status,
                'is_provisional' => (bool) $k->is_provisional,
                'provisional_note' => $k->provisional_note,
                'confirmed_at' => $k->confirmed_at?->format('Y-m-d H:i:s'),
                'confirmed_by' => $userRef($k->confirmed_by),
                'note' => $k->note,
                'source_file' => $k->source_file,
                'created_by' => $userRef($k->created_by),
                'updated_by' => $userRef($k->updated_by),
                'created_at' => $k->created_at?->format('Y-m-d H:i:s'),
                'updated_at' => $k->updated_at?->format('Y-m-d H:i:s'),
                'deleted_at' => $k->deleted_at?->format('Y-m-d H:i:s'),
                'payments' => $payments,
            ];
        }

        $counts += [
            'contracts' => count($outContracts),
            'deleted_contracts' => $contracts->whereNotNull('deleted_at')->count(),
            'provisional' => $contracts->where('is_provisional', true)->count(),
            'payments' => $paymentCount,
            'principal_sum' => (int) $contracts->sum('principal'),
            'payment_sum' => $paymentSum,
        ];

        $data = [
            'format' => self::FORMAT,
            'version' => self::VERSION,
            'exported_at' => now()->format('Y-m-d H:i:s'),
            'new_since' => $newSince->format('Y-m-d'),
            'tag' => ImportCreditFromJson::TAG_NAME,
            'counts' => $counts,
            'customers' => $outCustomers,
            'contracts' => $outContracts,
        ];

        $dir = dirname($path);
        if (! is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        file_put_contents($path, json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

        $this->info("書き出しました: {$path}（".round(filesize($path) / 1048576, 1).' MB、使ったメモリ '.round(memory_get_peak_usage(true) / 1048576).' MB）');
        $this->table(['項目', '件数'], self::countRows($counts));

        return self::SUCCESS;
    }

    /** 件数の表（書き出し・読み込みで共通） */
    public static function countRows(array $counts): array
    {
        $labels = [
            'new_customers' => '新しく作るお客様',
            'existing_customers' => '前からいるお客様（ひも付け）',
            'contracts' => '契約',
            'deleted_contracts' => '　うち削除済み',
            'provisional' => '仮登録',
            'payments' => '支払予定',
            'principal_sum' => '元金の合計',
            'payment_sum' => '支払予定の合計',
        ];
        $rows = [];
        foreach ($labels as $k => $label) {
            $rows[] = [$label, number_format($counts[$k] ?? 0)];
        }

        return $rows;
    }
}
