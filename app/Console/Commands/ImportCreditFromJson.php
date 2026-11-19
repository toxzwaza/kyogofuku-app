<?php

namespace App\Console\Commands;

use App\Models\CreditCompany;
use App\Models\CreditContract;
use App\Models\Customer;
use App\Models\CustomerTag;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Dropbox の「支払一覧表」Excel を解析した JSON（parse_credit.py の出力）を取り込む。
 *
 *  - --dry-run : DB には書き込まず、レポート CSV（ファイル単位・契約単位）だけ出す
 *  - --links   : 既存のお客様と同じ名前のファイルをどうするかの指定 CSV（source_file,customer_id）
 *                customer_id に「new」と書けば新規のお客様として登録する。
 *                指定のない同名ファイルは取り込まない（同姓同名の別人を紐付けないため）
 *  - 今月より前の支払予定は支払済で取り込む（信販・自社・現金とも。2026-11-08 決定）
 *  - 同じ取り込み元（source_file）の契約が既にあるファイルは取り込まない（何度流しても二重にならない）
 *  - --update  : 既に取り込んだファイルも列ごとに比べ、増えた列は契約を追加、変わった列は契約と支払予定を直す
 *                （Excel が現場で直されたとき用。Excel から消えた列は直さずに知らせるだけ）
 *                仮登録の印はそのまま。確認済み（仮登録を外した）の契約と、画面で支払済にした月は触らない
 *  - --renames : ファイル名が変わったときの対応 CSV（旧取り込み元,新取り込み元）。「ファイル」か「ファイル#列」で書く
 *  - --fixes   : 担当の方の回答をもとにした修正指定 CSV（取り込み元,項目,値,理由）。取り込み元は「ファイル#列」
 *                項目：company（信販会社名）／contract_date（YYYY-MM-DD）／first_month（初回 YYYY-MM。以降を同じ間隔でずらす）
 *                　　　status（completed＝全回支払済／cancelled＝組替）／exclude（契約ではない列。取り込まない）
 *                　　　sheet（読むシート。parse_credit.py 側で使うのでここでは無視）
 *  - 信販会社を判定できない契約は「仮登録」で取り込む（確認が済んだら画面で直して外す）。
 *    ほかに確認中の契約は credit:provisional で仮登録にする
 */
class ImportCreditFromJson extends Command
{
    protected $signature = 'credit:import
        {json : parse_credit.py が出力した JSON}
        {--dry-run : DB に書き込まずレポートだけ出す}
        {--links= : 同名の既存顧客の扱いを決めた CSV（source_file,customer_id|new）}
        {--fixes= : 回答をもとにした修正指定 CSV（取り込み元,項目,値,理由）}
        {--update : 取り込み済みのファイルも列ごとに比べて、追加・修正する}
        {--renames= : ファイル名が変わったときの対応 CSV（旧取り込み元,新取り込み元）}
        {--out= : レポート CSV の出力先フォルダ（既定 storage/app/credit-import）}
        {--today= : 今月の判定に使う日付（テスト用）}';

    protected $description = '支払一覧表 Excel（解析済み JSON）からクレジット契約を取り込む';

    /** 表記揺れ・誤記 → 信販会社の正式名（aliases に無いもの） */
    private const LABEL_FIXES = [
        'ニッセンレン' => '日専連',
        'ニッセレン' => '日専連',
        'ニッセンレンカード' => '日専連カード',
        'JACSS' => 'JACCS',
        'JACCCS' => 'JACCS',
        'トワライス' => 'トワライズ',
        'トアライズ' => 'トワライズ',
        '自社クレ' => '自社',
        '自社から組換え' => '自社',
    ];

    public const TAG_NAME = 'クレジット';

    private array $companyCache = [];

    /** 修正指定：取り込み元（ファイル#列）→ [項目 => [値, 理由]] */
    private array $fixes = [];

    private const FIX_ITEMS = ['company', 'contract_date', 'first_month', 'status', 'exclude', 'sheet'];

    public function handle(): int
    {
        ini_set('memory_limit', '1024M'); // 約13万行の支払予定を一度に扱うため
        $path = $this->argument('json');
        if (! is_file($path)) {
            $this->error("JSON が見つかりません: {$path}");

            return self::FAILURE;
        }
        $files = json_decode(file_get_contents($path), true);
        if (! is_array($files)) {
            $this->error('JSON を読めませんでした。');

            return self::FAILURE;
        }

        $dry = (bool) $this->option('dry-run');
        $today = $this->option('today') ? Carbon::parse($this->option('today')) : now();
        $thisMonth = $today->copy()->startOfMonth()->toDateString();
        $links = $this->loadLinks($this->option('links'));
        try {
            $this->fixes = $this->loadFixes($this->option('fixes'));
        } catch (\RuntimeException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }
        $usedFixes = [];
        $customerIndex = $this->buildCustomerIndex();
        $staffIndex = $this->buildStaffIndex();
        $update = (bool) $this->option('update');
        try {
            $renames = $this->loadRenames($this->option('renames'));
        } catch (\RuntimeException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }
        if ($renames && ! $update) {
            $this->error('--renames は --update と一緒に使ってください。');

            return self::FAILURE;
        }
        // 取り込み済みの契約：ファイル名が変わったものは新しい取り込み元で数える
        $existingByFile = [];
        foreach (CreditContract::withTrashed()->whereNotNull('source_file')
            ->get(['id', 'customer_id', 'source_file', 'deleted_at']) as $e) {
            [$file, $col] = array_pad(explode('#', $this->renamed($e->source_file, $renames), 2), 2, '');
            $existingByFile[$file][$col] = $e;
        }
        $imported = array_flip(array_keys($existingByFile));
        $updates = [];

        // Excel 側で同じ名前のファイルが複数あるか（同じ方か別の方か、人が決める）
        $sameNameFiles = [];
        foreach ($files as $f) {
            if (($f['kind'] ?? '') === 'customer') {
                $sameNameFiles[$this->nameKey($this->normalizeName($f['customer_name']))][] = $f['source_file'];
            }
        }

        $fileRows = [];
        $contractRows = [];
        $plans = [];

        foreach ($files as $f) {
            $src = $f['source_file'];
            $warn = $f['warnings'] ?? [];
            $base = ['source_file' => $src, 'name' => $f['customer_name'] ?? '', 'staff' => $f['staff'] ?? ''];

            if (($f['kind'] ?? '') !== 'customer') {
                $fileRows[] = $base + ['result' => '取り込まない', 'reason' => $this->skipReason($f), 'warnings' => $warn];

                continue;
            }
            if (isset($imported[$src])) {
                if (! $update) {
                    $fileRows[] = $base + ['result' => '取り込み済み', 'reason' => '同じファイルの契約が既にある', 'warnings' => []];

                    continue;
                }
                [$fileRow, $rows, $planned] = $this->planFileUpdate($f, $existingByFile[$src], $staffIndex, $thisMonth, $usedFixes);
                $fileRows[] = $base + $fileRow;
                array_push($contractRows, ...$rows);
                array_push($updates, ...$planned);

                continue;
            }

            // お客様：既存と同じ名前なら指定（--links）に従う。指定が無ければ取り込まない
            $name = $this->normalizeName($f['customer_name']);
            $candidates = $customerIndex[$this->nameKey($name)] ?? [];
            $link = $links[$src] ?? null;
            $customerId = null;
            $result = '新規追加';
            $reason = '';
            if ($link !== null && $link !== 'new') {
                $customerId = (int) $link;
                $result = '紐付け';
                $reason = '指定により既存のお客様に紐付け';
            } elseif ($candidates && $link === null) {
                $result = '要確認';
                $reason = '既存のお客様と同じ名前（紐付けるか新規か決めてから取り込む）';
            } elseif (count($sameNameFiles[$this->nameKey($name)] ?? []) > 1 && $link === null) {
                $result = '要確認';
                $reason = 'Excel に同じ名前のファイルが複数ある（同じ方か別の方か決めてから取り込む）';
            } elseif ($candidates && $link === 'new') {
                $reason = '同じ名前の既存のお客様がいるが、指定により新規追加';
            }

            [$staffUserId, $staffName] = $this->resolveStaff($f['staff'] ?? null, $staffIndex);
            $fileNote = array_filter([
                ($f['name_note'] ?? []) ? 'ファイル名の補足：'.implode('、', $f['name_note']) : null,
                (! empty($f['sheet_name']) && $this->nameKey($f['sheet_name']) !== $this->nameKey($name)) ? 'Excel 内の名前：'.$f['sheet_name'] : null,
            ]);

            $contracts = [];
            $excluded = [];
            foreach ($f['contracts'] as $c) {
                $key = $src.'#'.$c['col'];
                if (isset($this->fixes[$key])) {
                    $usedFixes[$key] = true;
                }
                if (isset($this->fixes[$key]['exclude'])) {
                    $excluded[] = $c['col'].'列';

                    continue;
                }
                $plan = $this->planContract($c, $src, $staffUserId, $staffName, $fileNote, $thisMonth);
                $contracts[] = $plan;
                $contractRows[] = [
                    'result' => $result,
                    'source' => $plan['source_file'],
                    'name' => $name,
                    'company' => $plan['company_name'] ?? '（未設定）',
                    'company_label' => implode(' / ', $c['below'] ?? []),
                    'contract_date' => $plan['contract_date'] ?? '',
                    'item' => $plan['item_name'] ?? '',
                    'principal' => $plan['principal'],
                    'fee' => $plan['fee'] ?? '',
                    'count' => count($plan['payments']),
                    'first' => $plan['payments'][0]['due_month'] ?? '',
                    'last' => $plan['payments'] ? end($plan['payments'])['due_month'] : '',
                    'scheduled' => array_sum(array_column($plan['payments'], 'amount')),
                    'unpaid' => $plan['unpaid'],
                    'status' => CreditContract::STATUS_LABELS[$plan['status']] ?? $plan['status'],
                    'check' => implode(' / ', $plan['checks']),
                ];
            }

            if (! $contracts) {
                $fileRows[] = $base + ['result' => '取り込まない', 'reason' => '修正指定ですべての列を除外（'.implode('、', $excluded).'）', 'warnings' => $warn];

                continue;
            }
            if ($excluded) {
                $warn[] = '修正指定により取り込まない列：'.implode('、', $excluded);
            }
            $checks = array_merge($warn, ...array_map(fn ($p) => array_map(fn ($x) => "{$p['col']}列：{$x}", $p['checks']), $contracts));
            $fileRows[] = array_merge($base, [
                'name' => $name,
                'result' => $result,
                'reason' => $reason,
                'candidates' => implode(' / ', array_map(fn ($c) => "{$c['id']}:{$c['label']}", $candidates)),
                'contracts' => count($contracts),
                'principal' => array_sum(array_column($contracts, 'principal')),
                'scheduled' => array_sum(array_map(fn ($p) => array_sum(array_column($p['payments'], 'amount')), $contracts)),
                'unpaid' => array_sum(array_column($contracts, 'unpaid')),
                'warnings' => $checks,
            ]);
            if ($result !== '要確認') {
                $plans[] = ['name' => $name, 'customer_id' => $customerId, 'source_file' => $src, 'contracts' => $contracts];
            }
        }

        // 使われなかった修正指定（取り込み元の書き間違い・取り込み済みのファイル）を知らせる
        $unused = array_diff(array_keys(array_filter($this->fixes, fn ($v) => array_diff(array_keys($v), ['sheet']))), array_keys($usedFixes));
        foreach ($unused as $key) {
            $this->warn("使われなかった修正指定：{$key}");
        }

        $outDir = $this->option('out') ?: storage_path('app/credit-import');
        if (! is_dir($outDir)) {
            mkdir($outDir, 0775, true);
        }
        $stamp = $today->format('Ymd');
        $fileCsv = "{$outDir}/{$stamp}_クレジット取り込み_ファイル別".($dry ? '_dryrun' : '').'.csv';
        $contractCsv = "{$outDir}/{$stamp}_クレジット取り込み_契約別".($dry ? '_dryrun' : '').'.csv';
        $this->writeFileReport($fileCsv, $fileRows);
        $this->writeContractReport($contractCsv, $contractRows);

        $this->printSummary($fileRows, $plans);
        if ($update) {
            $byAction = collect($updates)->countBy('action');
            $this->table(['取り込み済みファイルの変更', '契約数'], [
                ['契約を追加（Excel に増えた列）', $byAction['add'] ?? 0],
                ['契約を修正（Excel で直された列）', $byAction['update'] ?? 0],
            ]);
        }
        $this->line("レポート：{$fileCsv}");
        $this->line("　　　　　{$contractCsv}");

        if ($dry) {
            $this->info('dry-run のため DB には書き込んでいません。');

            return self::SUCCESS;
        }

        $this->apply($plans, $today);
        $this->applyUpdates($updates);
        $this->info('取り込みました。');

        return self::SUCCESS;
    }

    /** 1契約（Excel の1列）を取り込み内容に変換する */
    private function planContract(array $c, string $src, ?int $staffUserId, ?string $staffName, array $fileNote, string $thisMonth): array
    {
        $checks = [];
        $below = $c['below'] ?? [];
        $fix = $this->fixes[$src.'#'.$c['col']] ?? [];
        $company = null;
        if (isset($fix['company'])) {
            $company = $this->resolveCompany($fix['company'][0]);
        } else {
            foreach ($below as $label) {
                if ($company = $this->resolveCompany($label)) {
                    break;
                }
            }
        }
        if (isset($fix['contract_date'])) {
            $c['contract_date'] = $fix['contract_date'][0];
        }
        if (isset($fix['first_month']) && $c['payments']) {
            $shift = Carbon::parse($c['payments'][0]['month'].'-01')->diffInMonths(Carbon::parse($fix['first_month'][0].'-01'), false);
            foreach ($c['payments'] as &$p) {
                $p['month'] = Carbon::parse($p['month'].'-01')->addMonths($shift)->format('Y-m');
            }
            unset($p);
        }
        if (! $company) {
            $checks[] = $below ? '信販会社を判定できない（'.implode(' / ', $below).'）' : '信販会社の記載なし';
        }

        $payments = [];
        $unpaid = 0;
        foreach ($c['payments'] as $p) {
            $due = $p['month'].'-01';
            $paid = $due < $thisMonth;
            $payments[] = ['due_month' => $due, 'amount' => (int) $p['amount'], 'is_paid' => $paid];
            if (! $paid) {
                $unpaid += (int) $p['amount'];
            }
        }

        $memoText = implode(' ', array_merge($c['memo'] ?? [], $below));
        $fixStatus = $fix['status'][0] ?? null;
        if ($fixStatus === CreditContract::STATUS_COMPLETED) {
            $payments = array_map(fn ($p) => ['is_paid' => true] + $p, $payments);
            $unpaid = 0;
            $status = CreditContract::STATUS_COMPLETED;
        } elseif ($fixStatus === CreditContract::STATUS_CANCELLED) {
            $status = CreditContract::STATUS_CANCELLED;
        } elseif (! $payments) {
            if (preg_match('/組替|組換|組み替|組み換|合算|切替/u', $memoText)) {
                $status = CreditContract::STATUS_CANCELLED;
            } else {
                $status = CreditContract::STATUS_ACTIVE;
                $checks[] = '支払予定がない';
            }
        } else {
            $status = $unpaid === 0 && collect($payments)->every(fn ($p) => $p['is_paid'])
                ? CreditContract::STATUS_COMPLETED : CreditContract::STATUS_ACTIVE;
        }

        if (empty($c['contract_date']) && ! empty($c['contract_date_raw'])) {
            $checks[] = '契約日を読めない（'.$c['contract_date_raw'].'）';
        }
        if (! empty($c['contract_date']) && $payments
            && Carbon::parse($c['contract_date'])->startOfMonth()->addMonths(24)->toDateString() < $payments[0]['due_month']) {
            $checks[] = '初回の支払が契約日の2年以上あと';
        }

        $note = array_merge(
            ['Excel から取り込み（'.$src.' '.$c['col'].'列）'],
            (empty($c['contract_date']) || isset($fix['contract_date'])) && ! empty($c['contract_date_raw']) ? ['契約日（原文）：'.$c['contract_date_raw']] : [],
            ($c['memo'] ?? []) ? ['Excel のメモ：'.implode('／', $c['memo'])] : [],
            (! $company && $below) || count($below) > 1 ? ['合計の下の記載：'.implode('／', $below)] : [],
            $fileNote,
            array_map(fn ($item, $v) => "修正指定（{$item}＝{$v[0]}）：{$v[1]}", array_keys($fix), $fix),
        );

        return [
            'col' => $c['col'],
            'credit_company_id' => $company?->id,
            'company_name' => $company?->name,
            'contract_date' => $c['contract_date'] ?? null,
            'item_name' => isset($c['item_name']) ? mb_substr(preg_replace('/\s+/u', ' ', $c['item_name']), 0, 250) : null,
            'principal' => (int) ($c['principal'] ?? 0),
            'fee' => $c['fee'] ?? null,
            'staff_user_id' => $staffUserId,
            'staff_name' => $staffName,
            'status' => $status,
            'note' => implode("\n", $note),
            'source_file' => $src.'#'.$c['col'],
            'payments' => $payments,
            'unpaid' => $unpaid,
            'checks' => $checks,
        ];
    }

    private function apply(array $plans, Carbon $today): void
    {
        $tag = CustomerTag::firstOrCreate(['name' => self::TAG_NAME], ['is_active' => true]);
        $now = now();
        $bar = $this->output->createProgressBar(count($plans));

        foreach ($plans as $plan) {
            DB::transaction(function () use ($plan, $tag, $now) {
                $customerId = $plan['customer_id'];
                if (! $customerId) {
                    $customer = Customer::create(['name' => $plan['name']]);
                    $customer->tags()->syncWithoutDetaching([$tag->id]);
                    $customerId = $customer->id;
                }
                foreach ($plan['contracts'] as $c) {
                    $this->createContract($customerId, $c, $now);
                }
            });
            $bar->advance();
        }
        $bar->finish();
        $this->newLine();
    }

    /** 1契約と支払予定を登録する */
    private function createContract(int $customerId, array $c, Carbon $now): CreditContract
    {
        $contract = CreditContract::create([
            'customer_id' => $customerId,
            'credit_company_id' => $c['credit_company_id'],
            'contract_date' => $c['contract_date'],
            'item_name' => $c['item_name'],
            'principal' => $c['principal'],
            'fee' => $c['fee'],
            'staff_user_id' => $c['staff_user_id'],
            'staff_name' => $c['staff_name'],
            'status' => $c['status'],
            'note' => $c['note'],
            'source_file' => $c['source_file'],
            // 信販会社がわからない契約は仮登録にして、確認が済んだら画面で直す（解約は除く）
            'is_provisional' => $unknown = ($c['credit_company_id'] === null && $c['status'] !== CreditContract::STATUS_CANCELLED),
            'provisional_note' => $unknown ? MarkCreditProvisional::UNKNOWN_COMPANY_NOTE : null,
        ]);
        $rows = array_map(fn ($p) => [
            'credit_contract_id' => $contract->id,
            'due_month' => $p['due_month'],
            'amount' => $p['amount'],
            'is_paid' => $p['is_paid'],
            'paid_checked_at' => $p['is_paid'] ? $now : null,
            'paid_checked_by' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ], $c['payments']);
        foreach (array_chunk($rows, 500) as $chunk) {
            DB::table('credit_payments')->insert($chunk);
        }

        return $contract;
    }

    /**
     * 取り込み済みのファイルを列ごとに比べる（--update）。
     * 返り値：[ファイル別レポートの行, 契約別レポートの行, 追加・修正の予定]
     */
    private function planFileUpdate(array $f, array $existing, array $staffIndex, string $thisMonth, array &$usedFixes): array
    {
        $src = $f['source_file'];
        $name = $this->normalizeName($f['customer_name']);
        $customerId = collect($existing)->first()->customer_id;
        [$staffUserId, $staffName] = $this->resolveStaff($f['staff'] ?? null, $staffIndex);
        $fileNote = array_filter([
            ($f['name_note'] ?? []) ? 'ファイル名の補足：'.implode('、', $f['name_note']) : null,
            (! empty($f['sheet_name']) && $this->nameKey($f['sheet_name']) !== $this->nameKey($name)) ? 'Excel 内の名前：'.$f['sheet_name'] : null,
        ]);
        $current = CreditContract::withTrashed()->with('payments')
            ->whereIn('id', collect($existing)->pluck('id'))->get()->keyBy('id');

        $planned = [];
        $rows = [];
        $warn = [];
        $seen = [];
        foreach ($f['contracts'] as $c) {
            $key = $src.'#'.$c['col'];
            if (isset($this->fixes[$key])) {
                $usedFixes[$key] = true;
            }
            $e = isset($existing[$c['col']]) ? $current[$existing[$c['col']]->id] : null;
            $seen[$c['col']] = true;
            if (isset($this->fixes[$key]['exclude'])) {
                if ($e) {
                    $warn[] = "{$c['col']}列：修正指定で取り込まない列だが、契約が登録されている（直さない）";
                }

                continue;
            }
            $plan = $this->planContract($c, $src, $staffUserId, $staffName, $fileNote, $thisMonth);
            if (! $e) {
                $planned[] = ['action' => 'add', 'customer_id' => $customerId, 'contract' => $plan];
                $rows[] = $this->contractReportRow('追加', $name, $c, $plan, '');

                continue;
            }
            $diff = $this->contractDiff($e, $plan);
            if (! $diff) {
                continue;
            }
            if ($e->trashed() || $e->confirmed_at !== null) {
                $warn[] = "{$c['col']}列：Excel が変わったが、".($e->trashed() ? '削除済み' : '確認済み').'の契約なので直さない（'.implode('・', $diff).'）';

                continue;
            }
            $planned[] = ['action' => 'update', 'id' => $e->id, 'contract' => $plan];
            $rows[] = $this->contractReportRow('修正', $name, $c, $plan, '変わった所：'.implode('・', $diff));
        }
        foreach ($existing as $col => $e) {
            if (! isset($seen[$col]) && ! $e->trashed()) {
                $warn[] = "{$col}列：Excel に無くなった（契約はそのまま）";
            }
        }

        $result = $planned ? '更新' : ($warn ? '要確認' : '変更なし');

        return [[
            'name' => $name,
            'result' => $result,
            'reason' => $planned ? '追加 '.collect($planned)->where('action', 'add')->count().'・修正 '.collect($planned)->where('action', 'update')->count() : '',
            'contracts' => count($f['contracts']),
            'warnings' => $warn,
        ], $rows, $planned];
    }

    /** 登録済みの契約と Excel の差（項目名の一覧） */
    private function contractDiff(CreditContract $e, array $plan): array
    {
        $diff = [];
        $pairs = [
            '信販会社' => [$e->credit_company_id, $plan['credit_company_id']],
            '契約日' => [$e->contract_date?->format('Y-m-d'), $plan['contract_date']],
            '商品' => [$e->item_name, $plan['item_name']],
            '元金' => [(int) $e->principal, (int) $plan['principal']],
            '手数料' => [$e->fee === null ? null : (int) $e->fee, $plan['fee'] === null ? null : (int) $plan['fee']],
            '担当' => [$e->staff_name, $plan['staff_name']],
            '状態' => [$e->status, $plan['status']],
            '取り込み元' => [$e->source_file, $plan['source_file']],
        ];
        foreach ($pairs as $label => [$a, $b]) {
            if ($a !== $b) {
                $diff[] = $label;
            }
        }
        $cur = $e->payments->map(fn ($p) => substr((string) $p->due_month, 0, 10).':'.(int) $p->amount)->values()->all();
        $new = array_map(fn ($p) => $p['due_month'].':'.$p['amount'], $plan['payments']);
        if ($cur !== $new) {
            $diff[] = '支払予定';
        }

        return $diff;
    }

    private function contractReportRow(string $result, string $name, array $c, array $plan, string $prefix): array
    {
        return [
            'result' => $result,
            'source' => $plan['source_file'],
            'name' => $name,
            'company' => $plan['company_name'] ?? '（未設定）',
            'company_label' => implode(' / ', $c['below'] ?? []),
            'contract_date' => $plan['contract_date'] ?? '',
            'item' => $plan['item_name'] ?? '',
            'principal' => $plan['principal'],
            'fee' => $plan['fee'] ?? '',
            'count' => count($plan['payments']),
            'first' => $plan['payments'][0]['due_month'] ?? '',
            'last' => $plan['payments'] ? end($plan['payments'])['due_month'] : '',
            'scheduled' => array_sum(array_column($plan['payments'], 'amount')),
            'unpaid' => $plan['unpaid'],
            'status' => CreditContract::STATUS_LABELS[$plan['status']] ?? $plan['status'],
            'check' => implode(' / ', array_filter([$prefix, ...$plan['checks']])),
        ];
    }

    /** --update の追加・修正を書き込む */
    private function applyUpdates(array $updates): void
    {
        $now = now();
        foreach ($updates as $u) {
            DB::transaction(function () use ($u, $now) {
                $c = $u['contract'];
                if ($u['action'] === 'add') {
                    $this->createContract($u['customer_id'], $c, $now);

                    return;
                }
                $e = CreditContract::query()->with('payments')->findOrFail($u['id']);
                // 画面で支払済にした月（確認した人が残っている月）はそのまま
                $manual = $e->payments->whereNotNull('paid_checked_by')
                    ->mapWithKeys(fn ($p) => [substr((string) $p->due_month, 0, 10) => $p])->all();
                $e->update([
                    'credit_company_id' => $c['credit_company_id'],
                    'contract_date' => $c['contract_date'],
                    'item_name' => $c['item_name'],
                    'principal' => $c['principal'],
                    'fee' => $c['fee'],
                    'staff_user_id' => $c['staff_user_id'],
                    'staff_name' => $c['staff_name'],
                    'status' => $c['status'],
                    'note' => $c['note'],
                    'source_file' => $c['source_file'],
                ]);
                $e->payments()->delete();
                $rows = array_map(function ($p) use ($e, $manual, $now) {
                    $m = $manual[$p['due_month']] ?? null;

                    return [
                        'credit_contract_id' => $e->id,
                        'due_month' => $p['due_month'],
                        'amount' => $p['amount'],
                        'is_paid' => $m ? (bool) $m->is_paid : $p['is_paid'],
                        'paid_checked_at' => $m ? $m->paid_checked_at : ($p['is_paid'] ? $now : null),
                        'paid_checked_by' => $m?->paid_checked_by,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }, $c['payments']);
                foreach (array_chunk($rows, 500) as $chunk) {
                    DB::table('credit_payments')->insert($chunk);
                }
            });
        }
    }

    /** 旧取り込み元 → 新取り込み元 */
    private function loadRenames(?string $path): array
    {
        if (! $path) {
            return [];
        }
        if (! is_file($path)) {
            throw new \RuntimeException("ファイル名の対応 CSV が見つかりません: {$path}");
        }
        $map = [];
        $fh = fopen($path, 'r');
        if (fread($fh, 3) !== "\xEF\xBB\xBF") {
            rewind($fh);
        }
        $line = 0;
        while (($row = fgetcsv($fh)) !== false) {
            if (++$line === 1 || count($row) < 2 || trim($row[0]) === '') {
                continue;
            }
            [$old, $new] = [trim($row[0]), trim($row[1])];
            if (str_contains($old, '#') !== str_contains($new, '#')) {
                throw new \RuntimeException("ファイル名の対応 {$line}行目：「ファイル」同士か「ファイル#列」同士で書く（{$old} → {$new}）");
            }
            $map[$old] = $new;
        }
        fclose($fh);

        return $map;
    }

    private function renamed(string $source, array $renames): string
    {
        if (isset($renames[$source])) {
            return $renames[$source];
        }
        [$file, $col] = array_pad(explode('#', $source, 2), 2, null);

        return isset($renames[$file]) && $col !== null ? $renames[$file].'#'.$col : $source;
    }

    private function resolveCompany(string $label): ?CreditCompany
    {
        $label = trim(mb_convert_kana($label, 'asKV'));
        if (array_key_exists($label, $this->companyCache)) {
            return $this->companyCache[$label];
        }
        $fixed = self::LABEL_FIXES[$label] ?? null;
        $company = $fixed
            ? CreditCompany::query()->where('name', $fixed)->first()
            : CreditCompany::findByLabel($label);

        return $this->companyCache[$label] = $company;
    }

    /** 担当者：苗字が1人だけに当てはまるスタッフなら紐付ける。表記は常に残す（退職者もそのまま） */
    private function resolveStaff(?string $label, array $staffIndex): array
    {
        if (! $label) {
            return [null, null];
        }
        $key = $this->nameKey($label);
        $hits = array_values(array_filter($staffIndex, fn ($u) => str_starts_with($u['key'], $key)));

        return [count($hits) === 1 ? $hits[0]['id'] : null, $label];
    }

    private function buildStaffIndex(): array
    {
        return User::query()->get(['id', 'name'])
            ->map(fn ($u) => ['id' => $u->id, 'key' => $this->nameKey($u->name)])->all();
    }

    /** 既存のお客様を名前（本人・保護者）で引けるようにする */
    private function buildCustomerIndex(): array
    {
        $index = [];
        Customer::query()->select(['id', 'name', 'guardian_name'])->orderBy('id')->chunk(1000, function ($rows) use (&$index) {
            foreach ($rows as $c) {
                if ($c->name) {
                    $index[$this->nameKey($c->name)][] = ['id' => $c->id, 'label' => $c->name.'（本人）'];
                }
                if ($c->guardian_name) {
                    $index[$this->nameKey($c->guardian_name)][] = ['id' => $c->id, 'label' => $c->name.'（保護者：'.$c->guardian_name.'）'];
                }
            }
        });

        return $index;
    }

    private function loadLinks(?string $path): array
    {
        if (! $path) {
            return [];
        }
        $links = [];
        $fh = fopen($path, 'r');
        $bom = fread($fh, 3);
        if ($bom !== "\xEF\xBB\xBF") {
            rewind($fh);
        }
        while (($row = fgetcsv($fh)) !== false) {
            if (count($row) < 2 || $row[0] === 'source_file') {
                continue;
            }
            $v = trim($row[1]);
            if ($v === '') {
                continue;
            }
            $links[trim($row[0])] = $v === 'new' ? 'new' : (string) (int) $v;
        }
        fclose($fh);

        return $links;
    }

    /** 修正指定 CSV を読む。値のおかしい行があれば止める（取り込んでから気づかないように） */
    private function loadFixes(?string $path): array
    {
        if (! $path) {
            return [];
        }
        if (! is_file($path)) {
            throw new \RuntimeException("修正指定が見つかりません: {$path}");
        }
        $fixes = [];
        $fh = fopen($path, 'r');
        if (fread($fh, 3) !== "\xEF\xBB\xBF") {
            rewind($fh);
        }
        $line = 0;
        while (($row = fgetcsv($fh)) !== false) {
            $line++;
            if ($line === 1 || count($row) < 3 || trim($row[0]) === '') {
                continue;
            }
            [$key, $item, $value] = array_map('trim', array_slice($row, 0, 3));
            $reason = trim($row[3] ?? '');
            $bad = match ($item) {
                'company' => $this->resolveCompany($value) ? null : '信販会社が見つからない',
                'contract_date' => preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) ? null : '日付は YYYY-MM-DD',
                'first_month' => preg_match('/^\d{4}-\d{2}$/', $value) ? null : '月は YYYY-MM',
                'status' => in_array($value, [CreditContract::STATUS_COMPLETED, CreditContract::STATUS_CANCELLED], true) ? null : 'completed か cancelled',
                'exclude', 'sheet' => null,
                default => '項目は '.implode('／', self::FIX_ITEMS),
            };
            if ($item !== 'sheet' && ! str_contains($key, '#')) {
                $bad = '取り込み元は「ファイル#列」';
            }
            if ($bad) {
                throw new \RuntimeException("修正指定 {$line}行目：{$bad}（{$key} / {$item} / {$value}）");
            }
            $fixes[$key][$item] = [$value, $reason];
        }
        fclose($fh);

        return $fixes;
    }

    /** 姓と名の間は全角スペース1つにそろえる */
    private function normalizeName(string $name): string
    {
        $name = preg_replace('/[\s　]+/u', '　', mb_convert_kana($name, 'KV'));
        $name = preg_replace('/^　+|　+$/u', '', $name);

        return preg_replace('/　?様$/u', '', $name);
    }

    private function nameKey(string $name): string
    {
        $s = preg_replace('/[\s　]+/u', '', mb_convert_kana($name, 'asKV'));

        return strtr($s, ['﨑' => '崎', '髙' => '高', '齋' => '斎', '齊' => '斉', '邊' => '辺', '邉' => '辺']);
    }

    private function skipReason(array $f): string
    {
        return match ($f['kind'] ?? '') {
            'blank' => '空のファイル（原紙・契約なし）',
            'conflict_copy' => 'Dropbox の競合コピー（元のファイルを使う）',
            'skip' => 'Excel 以外のファイル',
            'error' => '読み取れない',
            default => '不明',
        };
    }

    private function writeFileReport(string $path, array $rows): void
    {
        $fh = fopen($path, 'w');
        fwrite($fh, "\xEF\xBB\xBF");
        fputcsv($fh, ['判定', '理由', 'ファイル', 'お客様名', '担当', '同じ名前の既存のお客様', '契約数', '元金合計', '予定額合計', '今月以降の残額', '確認事項', '扱い（要確認のとき：既存のお客様ID か new）']);
        foreach ($rows as $r) {
            fputcsv($fh, [
                $r['result'], $r['reason'] ?? '', $r['source_file'], $r['name'] ?? '', $r['staff'] ?? '',
                $r['candidates'] ?? '', $r['contracts'] ?? '', $r['principal'] ?? '', $r['scheduled'] ?? '', $r['unpaid'] ?? '',
                implode(' / ', $r['warnings'] ?? []), '',
            ]);
        }
        fclose($fh);
    }

    private function writeContractReport(string $path, array $rows): void
    {
        $fh = fopen($path, 'w');
        fwrite($fh, "\xEF\xBB\xBF");
        fputcsv($fh, ['判定', '取り込み元', 'お客様名', '信販会社', 'Excel の表記', '契約日', '商品', '元金', '手数料', '回数', '初回', '最終', '予定額合計', '今月以降の残額', '状態', '確認事項']);
        foreach ($rows as $r) {
            fputcsv($fh, array_values($r));
        }
        fclose($fh);
    }

    private function printSummary(array $fileRows, array $plans): void
    {
        $byResult = collect($fileRows)->countBy('result');
        $contracts = collect($plans)->flatMap(fn ($p) => $p['contracts']);
        $this->table(['判定', 'ファイル数'], $byResult->map(fn ($n, $k) => [$k, $n])->values()->all());
        $this->table(['項目', '値'], [
            ['取り込む契約数', $contracts->count()],
            ['うち 支払中 / 完済 / 解約・組換', $contracts->where('status', 'active')->count().' / '.$contracts->where('status', 'completed')->count().' / '.$contracts->where('status', 'cancelled')->count()],
            ['支払予定の行数', $contracts->sum(fn ($c) => count($c['payments']))],
            ['元金の合計', number_format($contracts->sum('principal'))],
            ['予定額の合計', number_format($contracts->sum(fn ($c) => array_sum(array_column($c['payments'], 'amount'))))],
            ['今月以降の残額の合計', number_format($contracts->sum('unpaid'))],
            ['信販会社を判定できない契約', $contracts->whereNull('credit_company_id')->count()],
            ['確認事項のあるファイル', collect($fileRows)->filter(fn ($r) => ! empty($r['warnings']))->count()],
        ]);
    }
}
