<?php

namespace App\Services\PhotoSlot;

use App\Models\Customer;
use App\Models\PhotoSlot;
use App\Models\PhotoStudio;
use App\Models\Shop;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * 前撮りの予約（入れる・移す・取り消す・詳細未決定）を1か所にまとめる。
 * 席は「スタジオ・日付・時刻」で選び、どの席に入れるかはここで決める（空いている一番小さい席）。
 * 予定（StaffSchedule）とカレンダーは、トランザクションのあとで PhotoSlotScheduleService が合わせる。
 *
 * attrs：shop_id（担当店舗。一覧からは shop_ids の配列）・user_id・plan_id・assignment_label・remarks
 */
class PhotoSlotBookingService
{
    private const ATTR_KEYS = ['assignment_label', 'user_id', 'plan_id', 'remarks'];

    /** 処理中は PhotoSlotObserver が予定を触らない（最後にまとめて合わせるため） */
    private static int $suspended = 0;

    public function __construct(private PhotoSlotScheduleService $schedules)
    {
    }

    public static function isSuspended(): bool
    {
        return self::$suspended > 0;
    }

    /**
     * お客様をその時刻の空いている席に入れる。
     * もう前撮りの登録があれば止める（二重登録の防止）。$replaceId に今の登録の ID を付けたときだけ、そこから移す（元の枠は空きに戻す）。
     * $user を渡すと、ほかの店舗グループの登録は動かさない。
     */
    public function assign(Customer $customer, int $studioId, string $date, string $time, array $attrs, ?int $replaceId = null, ?User $user = null): PhotoSlot
    {
        $target = ['studio_id' => $studioId, 'date' => Carbon::parse($date)->toDateString(), 'time' => substr($time, 0, 5)];
        [$slot, $vacatedId] = $this->run(function () use ($customer, $studioId, $date, $time, $attrs, $replaceId, $user, $target) {
            $current = $this->guardDuplicate($customer, $target, $replaceId, $user);

            $seat = $this->lockFreeSeat($studioId, $date, $time, $this->shopIds($attrs));
            $this->fillBooking($seat, $customer->id, $attrs);

            return [$seat, $this->vacate($current)];
        });
        $this->schedules->syncFor($slot);
        if ($vacatedId) {
            $this->schedules->syncFor(PhotoSlot::find($vacatedId));
        }

        return $slot;
    }

    /**
     * 予約を別の時刻（または別のスタジオ）へ移す。
     * 元が普通の席なら空きに戻し、詳細未決定の行なら消す。同じ時刻を選んだら、席はそのままで内容だけ直す。
     */
    public function move(PhotoSlot $from, int $studioId, string $date, string $time, array $attrs): PhotoSlot
    {
        $fromId = $from->id;
        $customerId = $from->customer_id;
        [$slot, $vacatedId] = $this->run(function () use ($fromId, $customerId, $studioId, $date, $time, $attrs) {
            // ロックの順番を assign とそろえる（お客様の行 → 元の行 → 入れる先の席）
            if ($customerId) {
                Customer::query()->whereKey($customerId)->lockForUpdate()->first();
            }
            $from = PhotoSlot::lockForUpdate()->findOrFail($fromId);
            if ($from->customer_id === null) {
                throw ValidationException::withMessages(['photo_slot_id' => 'この枠にはお客様が入っていません。']);
            }
            // 数えない行（撮影日の過ぎた枠）を今日以降へ移すと、ほかの登録と二重になるので止める。
            // 数える行どうしの移動は、前からの二重登録でもこれまでどおりできる
            if (Carbon::parse($date)->gte(Carbon::today())) {
                $this->assertNoOtherBooking($from);
            }

            $sameTime = ! $from->details_undecided
                && (int) $from->photo_studio_id === $studioId
                && Carbon::parse($from->shoot_date)->toDateString() === Carbon::parse($date)->toDateString()
                && substr($from->shoot_time, 0, 5) === substr($time, 0, 5);
            if ($sameTime) {
                $this->assertShopInGroup(PhotoStudio::findOrFail($studioId), $this->shopIds($attrs));
                $this->fillBooking($from, $from->customer_id, $attrs);

                return [$from, null];
            }

            $seat = $this->lockFreeSeat($studioId, $date, $time, $this->shopIds($attrs));
            $this->fillBooking($seat, $from->customer_id, $attrs);

            if ($from->details_undecided) {
                $from->delete();

                return [$seat, null];
            }
            $this->clearBooking($from);

            return [$seat, $from->id];
        });

        $this->schedules->syncFor($slot);
        if ($vacatedId) {
            $this->schedules->syncFor(PhotoSlot::find($vacatedId));
        }

        return $slot;
    }

    /**
     * 予約を取り消す（席は空きとして残す）。詳細未決定の行は席ではないので消す。
     */
    public function release(PhotoSlot $slot): void
    {
        $slotId = $slot->id;
        $deleted = $this->run(function () use ($slotId) {
            $slot = PhotoSlot::lockForUpdate()->findOrFail($slotId);
            if ($slot->customer_id === null) {
                throw ValidationException::withMessages(['error' => 'この前撮り枠には顧客が紐づいていません。']);
            }
            if ($slot->details_undecided) {
                $slot->delete();

                return true;
            }
            $this->clearBooking($slot);

            return false;
        });

        if (! $deleted) {
            $this->schedules->syncFor(PhotoSlot::find($slotId));
        }
    }

    /**
     * 詳細未決定（会場・日時が未定）の行を作る。予定は作らない。
     * 二重登録の扱いは assign と同じ（$replaceId を付けたときだけ、今の枠を空きに戻して未定にする）。
     */
    public function setUndecided(Customer $customer, array $attrs, ?int $replaceId = null, ?User $user = null): PhotoSlot
    {
        [$slot, $vacatedId] = $this->run(function () use ($customer, $attrs, $replaceId, $user) {
            $current = $this->guardDuplicate($customer, null, $replaceId, $user);
            $vacatedId = $this->vacate($current);

            return [$this->createUndecided($customer, $attrs), $vacatedId];
        });
        if ($vacatedId) {
            $this->schedules->syncFor(PhotoSlot::find($vacatedId));
        }

        return $slot;
    }

    /**
     * 予約済みの席・行を詳細未決定に戻す。普通の席だったら、席は空きに戻して新しく未決定の行を作る。
     */
    public function makeUndecided(PhotoSlot $slot, array $attrs): PhotoSlot
    {
        $slotId = $slot->id;
        $customerId = $slot->customer_id;
        [$result, $vacatedId] = $this->run(function () use ($slotId, $customerId, $attrs) {
            if ($customerId) {
                Customer::query()->whereKey($customerId)->lockForUpdate()->first();
            }
            $slot = PhotoSlot::lockForUpdate()->findOrFail($slotId);
            if ($slot->customer_id) {
                $this->assertNoOtherBooking($slot);
            }
            if ($slot->details_undecided) {
                $slot->update($this->pickAttrs($attrs));
                $this->syncShops($slot, $this->shopIds($attrs));

                return [$slot, null];
            }
            $customer = Customer::findOrFail($slot->customer_id);
            $this->clearBooking($slot);

            return [$this->createUndecided($customer, $attrs), $slot->id];
        });
        if ($vacatedId) {
            $this->schedules->syncFor(PhotoSlot::find($vacatedId));
        }

        return $result;
    }

    /**
     * 二重登録を数える行：日時未定の行と、撮影日が今日以降の席。
     * 取り消した予約は customer_id が空になる（席は空きに戻り、未定の行は消える）ので、ここには入らない。
     */
    public static function countedBookingsQuery(int $customerId)
    {
        return PhotoSlot::query()
            ->where('customer_id', $customerId)
            ->where(fn ($q) => $q->where('details_undecided', true)
                ->orWhereDate('shoot_date', '>=', Carbon::today()->toDateString()));
    }

    /**
     * 二重登録を止める。トランザクションの中で呼ぶ。
     * お客様の行をロックしてから今の登録を読むので、同じお客様を同時に2つの画面から入れても、2件目は待たされたうえで止まる。
     * ロックの順番：お客様の行 → 今の登録の行 → 入れる先の席（lockFreeSeat）。
     *
     * @param  array{studio_id:int, date:string, time:string}|null  $target  入れる先（null は日時未定）
     * @return PhotoSlot|null 移す元（ロック済み）。登録がなければ null
     */
    private function guardDuplicate(Customer $customer, ?array $target, ?int $replaceId, ?User $user): ?PhotoSlot
    {
        Customer::query()->whereKey($customer->id)->lockForUpdate()->first();

        $current = self::countedBookingsQuery($customer->id)
            ->with('studio:id,name')
            ->orderBy('id')
            ->lockForUpdate()
            ->get();
        if ($current->isEmpty()) {
            return null;
        }

        $list = $current->map(fn (PhotoSlot $s) => self::describe($s))->values()->all();
        if ($current->count() > 1) {
            $labels = implode('・', array_column($list, 'label'));
            throw new DuplicateBookingException('multiple', "この方はすでに{$current->count()}件登録があります（{$labels}）。どちらかを取り消してから登録してください。", $list);
        }

        $slot = $current->first();
        $label = $list[0]['label'];
        if ($user && ! PhotoSlot::query()->visibleToUser($user)->whereKey($slot->id)->exists()) {
            throw new DuplicateBookingException('other_group', 'この方はほかの店舗グループの前撮りで登録済みです。そちらの店舗で取り消してから登録してください。', []);
        }

        $isSame = $target === null
            ? $slot->details_undecided
            : (! $slot->details_undecided
                && (int) $slot->photo_studio_id === $target['studio_id']
                && Carbon::parse($slot->shoot_date)->toDateString() === $target['date']
                && substr((string) $slot->shoot_time, 0, 5) === $target['time']);
        if ($isSame) {
            throw new DuplicateBookingException('same', "この方はすでに {$label} で登録済みです。", $list);
        }

        if ($replaceId !== $slot->id) {
            throw new DuplicateBookingException('confirm', "この方は {$label} に登録済みです。", $list);
        }

        return $slot;
    }

    /**
     * 数えない行（撮影日の過ぎた枠）を今日以降・日時未定へ動かすとき、ほかに数える登録があれば止める。
     * 数える行どうしの移動は、前からの二重登録でもこれまでどおりできる。
     */
    private function assertNoOtherBooking(PhotoSlot $from): void
    {
        $counted = self::countedBookingsQuery($from->customer_id)->with('studio:id,name')->get();
        if ($counted->isEmpty() || $counted->contains('id', $from->id)) {
            return;
        }
        $list = $counted->map(fn (PhotoSlot $s) => self::describe($s))->values()->all();
        $labels = implode('・', array_column($list, 'label'));
        throw new DuplicateBookingException('multiple', "この方はほかに登録があります（{$labels}）。そちらを移すか取り消してください。", $list);
    }

    /** 移す元を空ける。席なら空きに戻して ID を返し（予定を合わせるため）、日時未定の行なら消す */
    private function vacate(?PhotoSlot $slot): ?int
    {
        if (! $slot) {
            return null;
        }
        if ($slot->details_undecided) {
            $slot->delete();

            return null;
        }
        $this->clearBooking($slot);

        return $slot->id;
    }

    private function createUndecided(Customer $customer, array $attrs): PhotoSlot
    {
        $slot = PhotoSlot::create($this->pickAttrs($attrs) + [
            'customer_id' => $customer->id,
            'details_undecided' => true,
            'photo_studio_id' => null,
            'shoot_date' => null,
            'shoot_time' => null,
            'seat_no' => 1, // 列は NOT NULL（既定 1）。日時が NULL なので一意制約にはかからない
        ]);
        $this->syncShops($slot, $this->shopIds($attrs));

        return $slot;
    }

    /** 確認の文に出す今の登録（例：2027/1/10（日）8:30 本店スタジオ／日時未定） */
    public static function describe(PhotoSlot $slot): array
    {
        $date = $slot->shoot_date ? Carbon::parse($slot->shoot_date) : null;
        $time = $slot->shoot_time ? substr($slot->shoot_time, 0, 5) : null;
        $studio = $slot->studio?->name;
        if ($slot->details_undecided || ! $date) {
            $label = '日時未定';
        } else {
            $week = ['日', '月', '火', '水', '木', '金', '土'][$date->dayOfWeek];
            $label = $date->format('Y/n/j')."（{$week}）".Carbon::parse($time)->format('G:i').($studio ? " {$studio}" : '');
        }

        return [
            'id' => $slot->id,
            'studio' => $studio,
            'date' => $date?->toDateString(),
            'time' => $time,
            'undecided' => (bool) $slot->details_undecided,
            'label' => $label,
        ];
    }

    /**
     * その時刻の席の行をロックして、お客様のいない一番小さい席を返す
     */
    private function lockFreeSeat(int $studioId, string $date, string $time, array $shopIds): PhotoSlot
    {
        $studio = PhotoStudio::find($studioId);
        if (! $studio) {
            throw ValidationException::withMessages(['photo_studio_id' => 'スタジオが見つかりません。']);
        }
        $this->assertShopInGroup($studio, $shopIds);

        $seats = PhotoSlot::query()
            ->where('photo_studio_id', $studioId)
            ->whereDate('shoot_date', Carbon::parse($date)->toDateString())
            ->where('shoot_time', substr($time, 0, 5).':00')
            ->where('details_undecided', false)
            ->orderBy('seat_no')
            ->orderBy('id')
            ->lockForUpdate()
            ->get();

        if ($seats->isEmpty()) {
            throw ValidationException::withMessages(['shoot_time' => 'この時刻の枠がありません。']);
        }
        $free = $seats->first(fn (PhotoSlot $s) => $s->customer_id === null);
        if (! $free) {
            throw ValidationException::withMessages(['shoot_time' => 'この時刻は満員です（定員 '.$seats->count().'人）。ほかの時刻を選んでください。']);
        }

        return $free;
    }

    /**
     * 担当店舗が、スタジオの店舗グループに属するか確かめる（スタジオのグループが未設定なら確かめない）
     */
    private function assertShopInGroup(PhotoStudio $studio, array $shopIds): void
    {
        if (! $shopIds || $studio->shop_group === null) {
            return;
        }
        $groups = Shop::withoutGlobalScope('visibleGroup')->whereKey($shopIds)->pluck('group_key');
        foreach ($groups as $group) {
            if ($group !== null && $group !== $studio->shop_group) {
                throw ValidationException::withMessages(['shop_id' => 'この担当店舗は、このスタジオの店舗グループではありません。']);
            }
        }
    }

    private function fillBooking(PhotoSlot $slot, int $customerId, array $attrs): void
    {
        $slot->update($this->pickAttrs($attrs) + ['customer_id' => $customerId]);
        $this->syncShops($slot, $this->shopIds($attrs));
    }

    private function clearBooking(PhotoSlot $slot): void
    {
        $slot->update([
            'customer_id' => null,
            'assignment_label' => null,
            'user_id' => null,
            'plan_id' => null,
            'remarks' => null,
        ]);
        $slot->shops()->detach();
    }

    private function syncShops(PhotoSlot $slot, array $shopIds): void
    {
        $shopIds ? $slot->shops()->sync($shopIds) : $slot->shops()->detach();
    }

    /** @return int[] */
    private function shopIds(array $attrs): array
    {
        $ids = array_key_exists('shop_ids', $attrs) ? (array) $attrs['shop_ids'] : [$attrs['shop_id'] ?? null];

        return array_values(array_unique(array_map('intval', array_filter($ids))));
    }

    private function pickAttrs(array $attrs): array
    {
        $out = [];
        foreach (self::ATTR_KEYS as $key) {
            $out[$key] = $attrs[$key] ?? null;
        }

        return $out;
    }

    private function run(callable $fn)
    {
        self::$suspended++;
        try {
            return DB::transaction($fn);
        } finally {
            self::$suspended--;
        }
    }
}
