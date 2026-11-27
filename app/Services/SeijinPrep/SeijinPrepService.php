<?php

namespace App\Services\SeijinPrep;

use App\Models\ActivityLog;
use App\Models\Customer;
use App\Models\SeijinPrepSlot;
use App\Models\SeijinPrepVenue;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * お客様の成人式お支度の登録（場所だけ／時刻の枠／他店お支度／未定）・移動・取り消し。
 * 席は lockForUpdate で押さえてから入れるので、定員を超えて入ることはない。
 *
 * ロックの順番（同時に操作してもロック待ちで止まらないように、どの処理もこの順にそろえる）：
 *   1. お客様の行（同じお客様の登録・取り消しを1つずつ進める。登録がまだないときも効く）
 *   2. お支度場所の行（番号の小さい順。同じ場所の席の操作を1つずつ進める。SeijinPrepSeatController も同じ）
 *   3. 席の行
 * 見える範囲（ほかの店舗グループの登録か）の確認は、1 のあと同じトランザクションの中で行う。
 *
 * 福井の場所（SeijinPrepVenue::isNumbered）は時刻を持たない「日＋番号」の枠。番号は入った順（若い番号＝早く成約した人）で、
 * 抜けた人がいると後ろの人の番号を詰める（renumber）。番号枠の行は prep_time が空なので一意制約に掛からず、
 * 同じ場所の行のロックで直列にしている。
 */
class SeijinPrepService
{
    /** 備考欄の案内（店舗グループごと）。お客様の詳細と予約の画面に出す */
    public const REMARKS_GUIDES = [
        'okayama' => [
            'other_store' => '他店お支度：着物の送り先（ご自宅／美容室）と、美容室の名前・住所・電話を書いてください。',
            'default' => '着付けの注意・ご家族の付き添い・持ち物など、当日の担当に伝えることを書いてください。',
        ],
        'fukui' => [
            'other_store' => '他店お支度：着物の受け取りの希望日と、お支度をする美容室の名前を書いてください。',
            'default' => '美容室に伝えること（希望時刻の幅・取れなかったときの第2希望など）を書いてください。',
        ],
    ];

    /**
     * お客様の登録を作る・変える。
     *
     * @param  array{venue_id:?int,is_other_store:bool,prep_date:?string,prep_time:?string,preferred_time:?string,remarks:?string,shop_id:?int,has_makeup?:?bool}  $data
     */
    public function save(Customer $customer, array $data, ?User $user): SeijinPrepSlot
    {
        $venue = $data['venue_id'] ? SeijinPrepVenue::find($data['venue_id']) : null;
        if ($data['venue_id'] && (! $venue || ! $venue->isVisibleTo($user))) {
            throw ValidationException::withMessages(['venue_id' => 'このお支度場所は選べません。']);
        }
        if ($data['is_other_store'] && $venue) {
            throw ValidationException::withMessages(['venue_id' => '他店お支度のときは、お支度場所は選べません。']);
        }
        $numbered = (bool) $venue?->isNumbered();
        // 番号枠（福井）は日だけで入れる。時刻の枠（岡山）は日と時刻
        $wantsSeat = ! empty($data['prep_date']) && ($numbered || ! empty($data['prep_time']));
        if (! empty($data['prep_date']) && ! empty($data['prep_time']) && ! $venue) {
            throw ValidationException::withMessages(['venue_id' => '時刻の枠に入れるときは、お支度場所を選んでください。']);
        }
        $hasMakeup = $data['has_makeup'] ?? null;
        if ($numbered && $hasMakeup === null) {
            throw ValidationException::withMessages(['has_makeup' => 'メイク有無を選んでください。']);
        }

        $this->assertShopAllowed($data['shop_id'] ?? null, $venue, $user);
        // 福井では希望時刻を入力しない（今の値は残す）
        $keepPreferredTime = $this->isFukuiEntry($venue, $data['shop_id'] ?? null);

        $fields = [
            'is_other_store' => (bool) $data['is_other_store'],
            'has_makeup' => $hasMakeup === null ? null : (bool) $hasMakeup,
            'preferred_time' => $data['preferred_time'] ?? null,
            'remarks' => $data['remarks'] ?? null,
            'shop_id' => $data['shop_id'] ?? null,
        ];

        [$row, $before] = DB::transaction(function () use ($customer, $venue, $wantsSeat, $numbered, $data, $fields, $user, $keepPreferredTime) {
            $current = $this->lockCurrent($customer, $user, $wantsSeat ? $venue : null);
            $before = $current ? $this->describe($current) : null;
            if ($keepPreferredTime) {
                $fields['preferred_time'] = $current?->preferred_time;
            }

            if ($wantsSeat) {
                $time = $numbered ? null : substr($data['prep_time'], 0, 5);
                $seats = $this->seatsQuery($venue, $data['prep_date'], $time)->orderBy('seat_no')->lockForUpdate()->get();
                $field = $numbered ? 'prep_date' : 'prep_time';
                $when = $numbered ? $data['prep_date'] : "{$data['prep_date']} {$time}";
                if ($seats->isEmpty()) {
                    throw ValidationException::withMessages([$field => ($numbered ? 'この日には枠がありません。' : 'この時刻には枠がありません。').'先に枠を作ってください。']);
                }
                // もうその枠の席に入っているなら、席はそのまま。番号枠は詰めてあるので、空きの一番小さい番号＝最後尾
                $target = $current && $seats->contains('id', $current->id) ? $current : $seats->whereNull('customer_id')->first();
                if (! $target) {
                    throw ValidationException::withMessages([$field => "{$when} は満員です（定員 {$seats->count()}人）。定員を増やしてから入れてください。"]);
                }
                if ($current && $current->id !== $target->id) {
                    $this->vacateOrDelete($current);
                }
                $target->update(array_merge($fields, ['customer_id' => $customer->id]));

                return [$target->fresh(), $before];
            }

            // 時刻の枠に入れない登録（場所だけ／他店／未定）
            $values = array_merge($fields, [
                'seijin_prep_venue_id' => $venue?->id,
                'prep_date' => null,
                'prep_time' => null,
                'seat_no' => 1,
            ]);
            if ($current && ! $current->isSeat()) {
                $current->update($values);

                return [$current->fresh(), $before];
            }
            if ($current) {
                $this->vacateOrDelete($current);
            }

            return [SeijinPrepSlot::create(array_merge($values, ['customer_id' => $customer->id])), $before];
        });

        $this->log($user, $before ? 'update' : 'create', $row->id,
            "成人式お支度を".($before ? '変更' : '登録')."：{$customer->name}（".$this->describe($row->load('venue')).'）',
            $before, $this->describe($row));

        return $row;
    }

    /** お客様の登録を取り消す（席に入っていれば席は空きに戻す） */
    public function cancel(Customer $customer, ?User $user): void
    {
        $before = DB::transaction(function () use ($customer, $user) {
            $current = $this->lockCurrent($customer, $user, null);
            if (! $current) {
                return null;
            }
            $before = $this->describe($current);
            $this->vacateOrDelete($current);

            return [$current->id, $before];
        });
        if ($before) {
            $this->log($user, 'delete', $before[0], "成人式お支度を取り消し：{$customer->name}（{$before[1]}）", $before[1], null);
        }
    }

    /**
     * お客様の行 → 関係するお支度場所の行 → 今の登録、の順に押さえて、今の登録を返す。
     * 今の登録がほかの店舗グループのものなら止める（確認と変更のあいだに入れ替わらないように、トランザクションの中で確かめる）。
     */
    private function lockCurrent(Customer $customer, ?User $user, ?SeijinPrepVenue $targetVenue): ?SeijinPrepSlot
    {
        Customer::whereKey($customer->id)->lockForUpdate()->first();
        $current = SeijinPrepSlot::where('customer_id', $customer->id)->first();
        if ($current && ! SeijinPrepSlot::query()->visibleToUser($user)->whereKey($current->id)->exists()) {
            throw ValidationException::withMessages(['error' => 'このお客様は、ほかの店舗グループでお支度の登録があるため変えられません。']);
        }
        $venueIds = collect([$current?->seijin_prep_venue_id, $targetVenue?->id])->filter()->unique()->sort()->values();
        if ($venueIds->isNotEmpty()) {
            SeijinPrepVenue::whereIn('id', $venueIds)->orderBy('id')->lockForUpdate()->get();
        }

        return $current ? SeijinPrepSlot::with('venue')->whereKey($current->id)->lockForUpdate()->first() : null;
    }

    /** 福井の登録か：お支度場所が福井、または場所がなく（他店・未定）担当店舗が福井の店 */
    public function isFukuiEntry(?SeijinPrepVenue $venue, ?int $shopId): bool
    {
        if ($venue) {
            return $venue->isNumbered();
        }
        $group = $shopId ? Shop::withoutGlobalScope('visibleGroup')->whereKey($shopId)->value('group_key') : null;

        return SeijinPrepVenue::isNumberedGroup($group);
    }

    /** 担当店舗は、操作する人に見える店で、お支度場所に店舗グループがあればそのグループの店だけ */
    private function assertShopAllowed(?int $shopId, ?SeijinPrepVenue $venue, ?User $user): void
    {
        if (! $shopId) {
            return;
        }
        $shop = Shop::withoutGlobalScope('visibleGroup')->find($shopId);
        $visible = $shop && (! $user || in_array($shop->id, $user->visibleShopIds(), true));
        if (! $visible) {
            throw ValidationException::withMessages(['shop_id' => 'この担当店舗は選べません。']);
        }
        if ($venue?->shop_group && $shop->group_key !== $venue->shop_group) {
            throw ValidationException::withMessages(['shop_id' => '担当店舗は、お支度場所と同じ店舗グループの店を選んでください。']);
        }
    }

    /** 席なら空きに戻し、席でない登録なら行を消す。番号枠なら後ろの人の番号を詰める */
    public function vacateOrDelete(SeijinPrepSlot $row): void
    {
        if ($row->isSeat()) {
            $row->update([
                'customer_id' => null, 'is_other_store' => false, 'has_makeup' => null, 'preferred_time' => null,
                'remarks' => null, 'shop_id' => null, 'original_prep_time' => null,
            ]);
            if ($row->isNumberedSeat()) {
                $this->renumber((int) $row->seijin_prep_venue_id, Carbon::parse($row->prep_date)->toDateString());
            }
        } else {
            $row->delete();
        }
    }

    /** その場所・日（時刻の枠なら時刻も）の席。番号枠は時刻が空の行 */
    public function seatsQuery(SeijinPrepVenue $venue, string $date, ?string $time)
    {
        $query = SeijinPrepSlot::query()->where('seijin_prep_venue_id', $venue->id)->whereDate('prep_date', $date);

        return $time === null ? $query->whereNull('prep_time') : $query->where('prep_time', substr($time, 0, 5).':00');
    }

    /**
     * 番号枠の番号を振り直す：入っている人を今の番号の順に 1 から、空き席はその後ろ。
     * 呼ぶ側で、その場所の行を押さえておくこと。
     */
    public function renumber(int $venueId, string $date): void
    {
        $rows = SeijinPrepSlot::query()
            ->where('seijin_prep_venue_id', $venueId)
            ->whereDate('prep_date', $date)
            ->whereNull('prep_time')
            ->orderBy('seat_no')->orderBy('id')
            ->lockForUpdate()
            ->get();
        $ordered = $rows->whereNotNull('customer_id')->concat($rows->whereNull('customer_id'))->values();
        foreach ($ordered as $i => $r) {
            if ($r->seat_no !== $i + 1) {
                $r->update(['seat_no' => $i + 1]);
            }
        }
    }

    public function describe(SeijinPrepSlot $row): string
    {
        $row->loadMissing('venue');
        if ($row->is_other_store) {
            return '他店お支度';
        }
        if (! $row->venue) {
            return '未定';
        }
        if (! $row->isSeat()) {
            return $row->venue->isNumbered() ? "{$row->venue->name}・日未定" : "{$row->venue->name}・時刻未定";
        }
        $date = Carbon::parse($row->prep_date)->toDateString();
        if ($row->isNumberedSeat()) {
            return "{$row->venue->name}・{$date}・番号{$row->seat_no}";
        }

        return "{$row->venue->name}・{$date} ".substr($row->prep_time, 0, 5)."・レーン{$row->seat_no}";
    }

    public function log(?User $user, string $action, ?int $id, string $description, $old, $new, array $extra = []): void
    {
        if (! $user) {
            return;
        }
        try {
            $request = request();
            ActivityLog::create([
                'user_id' => $user->id,
                'shop_id' => $user->shops()->first()?->id,
                'action_type' => $action,
                'resource_type' => 'SeijinPrepSlot',
                'resource_id' => $id,
                'route_name' => optional($request->route())->getName(),
                'url' => $request->fullUrl(),
                'method' => $request->method(),
                'description' => $description,
                'old_values' => $old === null ? null : (is_array($old) ? $old : ['お支度' => $old]),
                'new_values' => $new === null ? ($extra ?: null) : array_merge(is_array($new) ? $new : ['お支度' => $new], $extra),
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);
        } catch (\Throwable $e) {
            Log::warning('成人式お支度の操作ログを残せませんでした: '.$e->getMessage());
        }
    }
}
