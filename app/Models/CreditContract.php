<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class CreditContract extends Model
{
    use SoftDeletes;

    public const STATUS_ACTIVE = 'active';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_CANCELLED = 'cancelled';

    public const STATUS_LABELS = [
        self::STATUS_ACTIVE => '支払中',
        self::STATUS_COMPLETED => '完済',
        self::STATUS_CANCELLED => '解約・組換',
    ];

    protected $fillable = [
        'customer_id', 'credit_company_id', 'contract_date', 'item_name', 'principal', 'fee',
        'staff_user_id', 'staff_name', 'status', 'note', 'source_file', 'created_by', 'updated_by',
        'is_provisional', 'provisional_note', 'confirmed_at', 'confirmed_by',
    ];

    protected $casts = [
        'contract_date' => 'date:Y-m-d',
        'principal' => 'integer',
        'fee' => 'integer',
        'is_provisional' => 'boolean',
        'confirmed_at' => 'datetime',
    ];

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function company()
    {
        return $this->belongsTo(CreditCompany::class, 'credit_company_id');
    }

    public function staff()
    {
        return $this->belongsTo(User::class, 'staff_user_id');
    }

    public function confirmer()
    {
        return $this->belongsTo(User::class, 'confirmed_by');
    }

    public function payments()
    {
        return $this->hasMany(CreditPayment::class)->orderBy('due_month');
    }

    /** 担当者の表示名（スタッフ登録があればその名前、なければ取り込み時の表記） */
    public function getStaffLabelAttribute(): ?string
    {
        return $this->staff?->name ?? $this->staff_name;
    }

    /**
     * 回数・月額から支払予定を作り直す（既存の予定は消す）。
     * 初回だけ別額（first_amount）も指定できる。
     */
    public function regenerateSchedule(string $startMonth, int $count, int $monthlyAmount, ?int $firstAmount = null): void
    {
        $this->payments()->delete();
        $month = Carbon::parse($startMonth)->startOfMonth();
        for ($i = 0; $i < $count; $i++) {
            $this->payments()->create([
                'due_month' => $month->copy()->addMonthsNoOverflow($i)->toDateString(),
                'amount' => ($i === 0 && $firstAmount !== null) ? $firstAmount : $monthlyAmount,
                'is_paid' => false,
            ]);
        }
    }

    /** 全ての予定が支払済なら完済に、未払いが残っていて完済なら支払中に戻す（解約は触らない） */
    public function refreshStatus(): void
    {
        if ($this->status === self::STATUS_CANCELLED) {
            return;
        }
        $total = $this->payments()->count();
        $unpaid = $this->payments()->where('is_paid', false)->count();
        $next = ($total > 0 && $unpaid === 0) ? self::STATUS_COMPLETED : self::STATUS_ACTIVE;
        if ($this->status !== $next) {
            $this->update(['status' => $next]);
        }
    }

    /**
     * 信販会社の分（毎月の確認が要らない会社）は遅れが出ないので、
     * 今月より前の月を自動で支払済にし、払い終わった契約は完済にする。
     * 何度呼んでも同じ結果になる（画面を開いたとき・夜間バッチで呼ぶ）。
     *
     * @return int 支払済にした件数
     */
    public static function settleAgencyPayments(?Carbon $today = null): int
    {
        $before = ($today ?? now())->copy()->startOfMonth()->toDateString();

        $targets = CreditPayment::query()
            ->where('is_paid', false)
            ->where('due_month', '<', $before)
            ->whereHas('contract', fn ($c) => $c
                ->where('status', '!=', self::STATUS_CANCELLED)
                ->whereHas('company', fn ($q) => $q->where('needs_check', false)));

        $contractIds = (clone $targets)->distinct()->pluck('credit_contract_id');
        if ($contractIds->isEmpty()) {
            return 0;
        }

        $count = $targets->update(['is_paid' => true, 'paid_checked_at' => now(), 'paid_checked_by' => null]);
        self::query()->whereIn('id', $contractIds)->get()->each->refreshStatus();

        return $count;
    }
}
