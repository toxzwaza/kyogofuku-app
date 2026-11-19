<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CreditCompany;
use App\Models\CreditContract;
use App\Models\CreditPayment;
use App\Models\Customer;
use App\Models\Shop;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

/**
 * クレジット支払管理（旧：Dropbox の「支払一覧表」Excel）。
 * 閲覧はログインしたスタッフ全員。登録・修正・支払済チェックは can_edit_credit の人だけ。
 */
class CreditController extends Controller
{
    private function ensureCanEdit(Request $request): void
    {
        if (!$request->user()->canEditCredit()) {
            abort(403);
        }
    }

    /** 未払い残額（予定のうち未払いの合計）と次回の支払月を付けたクエリ */
    private function contractQuery()
    {
        return CreditContract::query()
            ->with(['customer:id,name,kana,guardian_name,shop_id', 'customer.shop:id,name', 'company:id,name,needs_check', 'staff:id,name'])
            ->withSum(['payments as scheduled_total' => fn ($q) => $q], 'amount')
            ->withSum(['payments as unpaid_total' => fn ($q) => $q->where('is_paid', false)], 'amount')
            ->withCount('payments');
    }

    private function contractRow(CreditContract $c): array
    {
        return [
            'id' => $c->id,
            'customer' => $c->customer ? [
                'id' => $c->customer->id,
                'name' => $c->customer->name,
                'kana' => $c->customer->kana,
                'guardian_name' => $c->customer->guardian_name,
                'shop' => $c->customer->shop?->name,
            ] : null,
            'company' => $c->company?->name,
            'needs_check' => (bool) $c->company?->needs_check,
            'contract_date' => $c->contract_date?->format('Y-m-d'),
            'item_name' => $c->item_name,
            'principal' => $c->principal,
            'fee' => $c->fee,
            'staff' => $c->staff_label,
            'status' => $c->status,
            'status_label' => CreditContract::STATUS_LABELS[$c->status] ?? $c->status,
            'note' => $c->note,
            'is_provisional' => (bool) $c->is_provisional,
            'provisional_note' => $c->provisional_note,
            'scheduled_total' => (int) ($c->scheduled_total ?? 0),
            'unpaid_total' => (int) ($c->unpaid_total ?? 0),
            'payments_count' => (int) ($c->payments_count ?? 0),
        ];
    }

    private function companyOptions()
    {
        return CreditCompany::query()->where('is_active', true)->ordered()->get(['id', 'name', 'needs_check']);
    }

    private function staffOptions()
    {
        return User::query()->orderBy('name')->get(['id', 'name']);
    }

    /** 1. クレジット一覧 */
    public function index(Request $request)
    {
        CreditContract::settleAgencyPayments();
        $filters = [
            'q' => trim((string) $request->query('q', '')),
            'company' => $request->query('company', ''),
            // 仮登録だけを見るときは、完済・解約も含めて全部出す
            'status' => $request->query('status', $request->query('provisional') === '1' ? 'all' : 'active'),
            'staff' => trim((string) $request->query('staff', '')),
            'shop' => $request->query('shop', ''),
            'provisional' => $request->query('provisional') === '1' ? '1' : '',
        ];

        // 絞り込み（契約に対して）。お客様の一覧と、その中の明細の両方に同じ条件を使う
        $applyFilters = function ($query) use ($filters) {
            if ($filters['q'] !== '') {
                $q = preg_replace('/\s+/u', '', $filters['q']);
                $query->whereHas('customer', function ($cq) use ($q) {
                    $cq->whereRaw("REPLACE(REPLACE(name, ' ', ''), '　', '') LIKE ?", ['%'.$q.'%'])
                        ->orWhereRaw("REPLACE(REPLACE(COALESCE(kana, ''), ' ', ''), '　', '') LIKE ?", ['%'.$q.'%'])
                        ->orWhereRaw("REPLACE(REPLACE(COALESCE(guardian_name, ''), ' ', ''), '　', '') LIKE ?", ['%'.$q.'%']);
                });
            }
            if ($filters['company'] !== '' && $filters['company'] !== null) {
                $query->where('credit_company_id', $filters['company']);
            }
            if (in_array($filters['status'], array_keys(CreditContract::STATUS_LABELS), true)) {
                $query->where('status', $filters['status']);
            }
            if ($filters['staff'] !== '') {
                $s = $filters['staff'];
                $query->where(function ($w) use ($s) {
                    $w->where('staff_name', 'like', '%'.$s.'%')
                        ->orWhereHas('staff', fn ($u) => $u->where('name', 'like', '%'.$s.'%'));
                });
            }
            if ($filters['provisional'] === '1') {
                $query->where('is_provisional', true);
            }
            if ($filters['shop'] === 'none') {
                $query->whereHas('customer', fn ($cq) => $cq->whereNull('shop_id'));
            } elseif ($filters['shop'] !== '' && $filters['shop'] !== null) {
                $query->whereHas('customer', fn ($cq) => $cq->where('shop_id', $filters['shop']));
            }

            return $query;
        };

        // 表示の切り替え：sheet＝支払状況の表（既定）／list＝契約のリスト
        $view = $request->query('view') === 'list' ? 'list' : 'sheet';
        $months = [];
        $from = $to = null;
        if ($view === 'sheet') {
            // 表に出す12か月（既定：5か月前〜6か月後）
            try {
                $from = $request->query('from')
                    ? Carbon::createFromFormat('Y-m', $request->query('from'))->startOfMonth()
                    : now()->startOfMonth()->subMonths(5);
            } catch (\Throwable $e) {
                $from = now()->startOfMonth()->subMonths(5);
            }
            $to = $from->copy()->addMonths(11);
            for ($m = $from->copy(); $m->lte($to); $m->addMonth()) {
                $months[] = $m->format('Y-m');
            }
            $filters['from'] = $from->format('Y-m');
        }
        $filters['view'] = $view;

        // 1行＝お客様1人。条件に合う契約を持つお客様を 50 人ずつ
        $matched = $applyFilters(CreditContract::query());
        $customerQuery = Customer::query()
            ->with('shop:id,name')
            ->whereIn('id', (clone $matched)->select('customer_id'));
        if ($view === 'sheet') {
            $customerQuery->orderByRaw("COALESCE(NULLIF(kana, ''), name)")->orderBy('id');
        } else {
            // 新しい契約のあるお客様が上
            $customerQuery->orderByDesc(
                $applyFilters(CreditContract::query())->selectRaw('MAX(contract_date)')->whereColumn('credit_contracts.customer_id', 'customers.id')
            )->orderByDesc('id');
        }
        $customers = $customerQuery->paginate(50, ['id', 'name', 'kana', 'guardian_name', 'shop_id'])->withQueryString();

        // そのページのお客様の契約（明細）
        $detail = $applyFilters($this->contractQuery())->whereIn('customer_id', $customers->pluck('id'));
        if ($view === 'sheet') {
            $detail->with(['payments' => fn ($p) => $p->whereBetween('due_month', [$from->toDateString(), $to->toDateString()])]);
        }
        $byCustomer = $detail->orderByDesc('contract_date')->orderByDesc('id')->get()->groupBy('customer_id');

        $customers->through(function ($cu) use ($byCustomer, $view) {
            $rows = ($byCustomer[$cu->id] ?? collect())->map(function ($c) use ($view) {
                $row = $this->contractRow($c);
                if ($view === 'sheet') {
                    $row['cells'] = $c->payments->mapWithKeys(fn ($p) => [
                        $p->due_month->format('Y-m') => ['amount' => (int) $p->amount, 'is_paid' => (bool) $p->is_paid],
                    ]);
                }

                return $row;
            })->values();

            return [
                'id' => $cu->id,
                'customer' => [
                    'id' => $cu->id,
                    'name' => $cu->name,
                    'kana' => $cu->kana,
                    'guardian_name' => $cu->guardian_name,
                    'shop' => $cu->shop?->name,
                ],
                'contracts' => $rows,
                'contract_count' => $rows->count(),
                'principal_total' => (int) $rows->sum('principal'),
                'unpaid_total' => (int) $rows->sum('unpaid_total'),
                'latest_date' => $rows->max('contract_date'),
                'companies' => $rows->map(fn ($r) => $r['company'] ?? '（未定）')->unique()->values(),
                'staffs' => $rows->pluck('staff')->filter()->unique()->values(),
                'active_count' => $rows->where('status', CreditContract::STATUS_ACTIVE)->count(),
                'provisional_count' => $rows->where('is_provisional', true)->count(),
                'needs_check' => $rows->contains('needs_check', true),
            ];
        });

        return Inertia::render('Admin/Credit/Index', [
            'customers' => $customers,
            'contractTotal' => (clone $matched)->count(),
            'months' => $months,
            'thisMonth' => now()->format('Y-m'),
            'filters' => $filters,
            'companies' => $this->companyOptions(),
            'shops' => Shop::query()->orderBy('id')->get(['id', 'name']),
            'provisionalCount' => CreditContract::query()->where('is_provisional', true)->count(),
            'canEdit' => $request->user()->canEditCredit(),
        ]);
    }

    /** 2. 今月の支払確認（自社・現金だけ） */
    public function monthly(Request $request)
    {
        CreditContract::settleAgencyPayments();
        $monthParam = $request->query('month');
        try {
            $month = $monthParam ? Carbon::createFromFormat('Y-m', $monthParam)->startOfMonth() : now()->startOfMonth();
        } catch (\Throwable $e) {
            $month = now()->startOfMonth();
        }

        $base = CreditPayment::query()
            ->with(['contract.customer:id,name,kana,guardian_name', 'contract.company:id,name', 'contract.staff:id,name', 'checker:id,name'])
            ->whereHas('contract', function ($q) {
                $q->where('status', '!=', CreditContract::STATUS_CANCELLED)
                    ->whereHas('company', fn ($c) => $c->where('needs_check', true));
            });

        $row = fn (CreditPayment $p) => [
            'id' => $p->id,
            'due_month' => $p->due_month->format('Y-m'),
            'amount' => $p->amount,
            'is_paid' => $p->is_paid,
            'paid_checked_at' => $p->paid_checked_at?->format('Y-m-d H:i'),
            'checker' => $p->checker?->name,
            'contract_id' => $p->contract->id,
            'customer' => $p->contract->customer ? ['id' => $p->contract->customer->id, 'name' => $p->contract->customer->name, 'guardian_name' => $p->contract->customer->guardian_name] : null,
            'company' => $p->contract->company?->name,
            'item_name' => $p->contract->item_name,
            'staff' => $p->contract->staff_label,
        ];

        $thisMonth = (clone $base)->whereDate('due_month', $month->toDateString())
            ->get()
            ->sortBy(fn ($p) => $p->contract->customer->kana ?? $p->contract->customer->name ?? '')
            ->values()
            ->map($row);

        // 前の月までで未払いのまま残っているもの（遅れ）
        $overdue = (clone $base)->whereDate('due_month', '<', $month->toDateString())
            ->where('is_paid', false)
            ->orderBy('due_month')
            ->get()
            ->map($row);

        return Inertia::render('Admin/Credit/Monthly', [
            'month' => $month->format('Y-m'),
            'payments' => $thisMonth,
            'overdue' => $overdue,
            'canEdit' => $request->user()->canEditCredit(),
        ]);
    }

    /** 支払済チェックを付ける／外す（まとめて） */
    public function markPaid(Request $request)
    {
        $this->ensureCanEdit($request);

        $validated = $request->validate([
            'ids' => 'required|array|min:1',
            'ids.*' => 'integer|exists:credit_payments,id',
            'is_paid' => 'required|boolean',
        ]);

        DB::transaction(function () use ($validated, $request) {
            $payments = CreditPayment::query()->whereIn('id', $validated['ids'])->get();
            foreach ($payments as $p) {
                $p->update([
                    'is_paid' => $validated['is_paid'],
                    'paid_checked_at' => $validated['is_paid'] ? now() : null,
                    'paid_checked_by' => $validated['is_paid'] ? $request->user()->id : null,
                ]);
            }
            CreditContract::query()->whereIn('id', $payments->pluck('credit_contract_id')->unique())
                ->get()->each->refreshStatus();
        });

        return back()->with('success', $validated['is_paid'] ? '支払済にしました。' : '未払いに戻しました。');
    }

    /** 4. 契約登録フォーム */
    public function create(Request $request)
    {
        $this->ensureCanEdit($request);

        $customer = $request->query('customer_id') ? Customer::query()->find($request->query('customer_id'), ['id', 'name', 'kana']) : null;

        return Inertia::render('Admin/Credit/Form', [
            'contract' => null,
            'customer' => $customer,
            'companies' => $this->companyOptions(),
            'staffs' => $this->staffOptions(),
            'defaultStaffId' => $request->user()->id,
        ]);
    }

    public function store(Request $request)
    {
        $this->ensureCanEdit($request);

        $validated = $request->validate(array_merge($this->contractRules($request), [
            'customer_id' => 'nullable|required_without:new_customer_name|exists:customers,id',
            'new_customer_name' => 'nullable|required_without:customer_id|string|max:255',
            'new_customer_kana' => 'nullable|string|max:255',
            'start_month' => 'required|date_format:Y-m',
            'count' => 'required|integer|min:1|max:120',
            'monthly_amount' => 'required|integer|min:0',
            'first_amount' => 'nullable|integer|min:0',
        ]));

        $contract = DB::transaction(function () use ($validated, $request) {
            $customerId = $validated['customer_id'] ?? null;
            if (!$customerId) {
                // 新しい顧客。所属店舗は分からないので空のまま（あとで顧客画面から登録）
                $customerId = Customer::create([
                    'name' => trim(preg_replace('/\s+/u', '　', $validated['new_customer_name'])),
                    'kana' => $validated['new_customer_kana'] ?? null,
                ])->id;
            }

            $contract = CreditContract::create(array_merge($this->contractAttributes($validated), [
                'is_provisional' => (bool) ($validated['is_provisional'] ?? false),
                'provisional_note' => ($validated['is_provisional'] ?? false) ? ($validated['provisional_note'] ?? null) : null,
                'customer_id' => $customerId,
                'status' => CreditContract::STATUS_ACTIVE,
                'created_by' => $request->user()->id,
                'updated_by' => $request->user()->id,
            ]));
            $contract->regenerateSchedule(
                $validated['start_month'].'-01',
                (int) $validated['count'],
                (int) $validated['monthly_amount'],
                isset($validated['first_amount']) ? (int) $validated['first_amount'] : null,
            );

            return $contract;
        });

        return redirect()->route('admin.credits.edit', $contract)
            ->with('success', '契約を登録しました。月ごとの金額はこの画面で直せます。');
    }

    /** 契約の編集（支払予定の表つき） */
    public function edit(Request $request, CreditContract $creditContract)
    {
        CreditContract::settleAgencyPayments();
        $creditContract->load(['customer:id,name,kana', 'payments.checker:id,name', 'confirmer:id,name']);

        return Inertia::render('Admin/Credit/Form', [
            'contract' => array_merge($creditContract->only([
                'id', 'credit_company_id', 'item_name', 'principal', 'fee', 'staff_user_id', 'staff_name', 'status', 'note', 'source_file',
                'is_provisional', 'provisional_note',
            ]), [
                'contract_date' => $creditContract->contract_date?->format('Y-m-d'),
                'confirmed_at' => $creditContract->confirmed_at?->format('Y-m-d H:i'),
                'confirmer' => $creditContract->confirmer?->name,
                'payments' => $creditContract->payments->map(fn ($p) => [
                    'id' => $p->id,
                    'due_month' => $p->due_month->format('Y-m'),
                    'amount' => $p->amount,
                    'is_paid' => $p->is_paid,
                    'checker' => $p->checker?->name,
                    'paid_checked_at' => $p->paid_checked_at?->format('Y-m-d'),
                ]),
            ]),
            'customer' => $creditContract->customer,
            'companies' => $this->companyOptions(),
            'staffs' => $this->staffOptions(),
            'defaultStaffId' => null,
            'canEdit' => $request->user()->canEditCredit(),
        ]);
    }

    public function update(Request $request, CreditContract $creditContract)
    {
        $this->ensureCanEdit($request);

        $validated = $request->validate(array_merge($this->contractRules($request), [
            'status' => 'required|in:'.implode(',', array_keys(CreditContract::STATUS_LABELS)),
            'payments' => 'array',
            'payments.*.id' => 'required|integer',
            'payments.*.amount' => 'required|integer|min:0',
            'payments.*.is_paid' => 'required|boolean',
        ]));

        DB::transaction(function () use ($validated, $request, $creditContract) {
            $creditContract->update(array_merge(
                $this->contractAttributes($validated),
                $this->provisionalAttributes($creditContract, $validated, $request),
                [
                    'status' => $validated['status'],
                    'updated_by' => $request->user()->id,
                ],
            ));

            $existing = $creditContract->payments()->get()->keyBy('id');
            foreach ($validated['payments'] ?? [] as $row) {
                $p = $existing->get($row['id']);
                if (!$p) {
                    continue;
                }
                $changes = ['amount' => (int) $row['amount']];
                if ((bool) $row['is_paid'] !== $p->is_paid) {
                    $changes['is_paid'] = (bool) $row['is_paid'];
                    $changes['paid_checked_at'] = $row['is_paid'] ? now() : null;
                    $changes['paid_checked_by'] = $row['is_paid'] ? $request->user()->id : null;
                }
                $p->update($changes);
            }
            if ($validated['status'] !== CreditContract::STATUS_CANCELLED) {
                $creditContract->refresh()->refreshStatus();
            }
        });

        $confirmed = $creditContract->wasChanged('is_provisional') && ! $creditContract->is_provisional;

        return back()->with('success', $confirmed ? '保存して、仮登録を外しました。' : '保存しました。');
    }

    /** 予定を作り直す（回数・月額から） */
    public function regenerate(Request $request, CreditContract $creditContract)
    {
        $this->ensureCanEdit($request);

        $validated = $request->validate([
            'start_month' => 'required|date_format:Y-m',
            'count' => 'required|integer|min:1|max:120',
            'monthly_amount' => 'required|integer|min:0',
            'first_amount' => 'nullable|integer|min:0',
        ]);

        DB::transaction(function () use ($validated, $creditContract) {
            $creditContract->regenerateSchedule(
                $validated['start_month'].'-01',
                (int) $validated['count'],
                (int) $validated['monthly_amount'],
                isset($validated['first_amount']) ? (int) $validated['first_amount'] : null,
            );
            $creditContract->refreshStatus();
        });

        return back()->with('success', '支払予定を作り直しました。');
    }

    /** 3. 顧客詳細の「クレジット」タブ用（JSON） */
    public function forCustomer(Request $request, Customer $customer)
    {
        CreditContract::settleAgencyPayments();
        $contracts = $this->contractQuery()
            ->with('payments')
            ->where('customer_id', $customer->id)
            ->orderBy('contract_date')
            ->orderBy('id')
            ->get();

        return response()->json([
            'contracts' => $contracts->map(fn ($c) => array_merge($this->contractRow($c), [
                'payments' => $c->payments->map(fn ($p) => [
                    'id' => $p->id,
                    'due_month' => $p->due_month->format('Y-m'),
                    'amount' => $p->amount,
                    'is_paid' => $p->is_paid,
                ]),
            ])),
            'canEdit' => $request->user()->canEditCredit(),
        ]);
    }

    /** 仮登録（確認中）の契約は信販会社が未定のままでも保存できる */
    private function contractRules(Request $request): array
    {
        return [
            'credit_company_id' => ($request->boolean('is_provisional') ? 'nullable' : 'required').'|exists:credit_companies,id',
            'is_provisional' => 'nullable|boolean',
            'provisional_note' => 'nullable|string|max:2000',
            'contract_date' => 'nullable|date',
            'item_name' => 'nullable|string|max:255',
            'principal' => 'required|integer|min:0',
            'fee' => 'nullable|integer|min:0',
            'staff_user_id' => 'nullable|exists:users,id',
            'note' => 'nullable|string|max:2000',
        ];
    }

    private function contractAttributes(array $v): array
    {
        return [
            'credit_company_id' => $v['credit_company_id'] ?? null,
            'contract_date' => $v['contract_date'] ?? null,
            'item_name' => $v['item_name'] ?? null,
            'principal' => (int) $v['principal'],
            'fee' => isset($v['fee']) ? (int) $v['fee'] : null,
            'staff_user_id' => $v['staff_user_id'] ?? null,
            'note' => $v['note'] ?? null,
        ];
    }

    /**
     * 仮登録の付け外し。外したときは確認した人・日時を残し、付け直したら消す。
     * 古い画面などで is_provisional が送られてこなければ今の状態のまま。
     */
    private function provisionalAttributes(CreditContract $c, array $v, Request $request): array
    {
        if (! array_key_exists('is_provisional', $v) || $v['is_provisional'] === null) {
            return [];
        }
        $next = (bool) $v['is_provisional'];
        $attrs = ['is_provisional' => $next, 'provisional_note' => $v['provisional_note'] ?? $c->provisional_note];
        if ($c->is_provisional && ! $next) {
            $attrs['confirmed_at'] = now();
            $attrs['confirmed_by'] = $request->user()->id;
        } elseif (! $c->is_provisional && $next) {
            $attrs['confirmed_at'] = null;
            $attrs['confirmed_by'] = null;
        }

        return $attrs;
    }
}
