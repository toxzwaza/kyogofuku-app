<?php

namespace App\Services\PhotoSlot;

use App\Models\Customer;
use App\Models\PhotoSlot;
use App\Models\PhotoStudio;
use App\Models\Shop;
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
     * お客様をその時刻の空いている席に入れる
     */
    public function assign(Customer $customer, int $studioId, string $date, string $time, array $attrs): PhotoSlot
    {
        $slot = $this->run(function () use ($customer, $studioId, $date, $time, $attrs) {
            $seat = $this->lockFreeSeat($studioId, $date, $time, $this->shopIds($attrs));
            $this->fillBooking($seat, $customer->id, $attrs);

            return $seat;
        });
        $this->schedules->syncFor($slot);

        return $slot;
    }

    /**
     * 予約を別の時刻（または別のスタジオ）へ移す。
     * 元が普通の席なら空きに戻し、詳細未決定の行なら消す。同じ時刻を選んだら、席はそのままで内容だけ直す。
     */
    public function move(PhotoSlot $from, int $studioId, string $date, string $time, array $attrs): PhotoSlot
    {
        $fromId = $from->id;
        [$slot, $vacatedId] = $this->run(function () use ($fromId, $studioId, $date, $time, $attrs) {
            $from = PhotoSlot::lockForUpdate()->findOrFail($fromId);
            if ($from->customer_id === null) {
                throw ValidationException::withMessages(['photo_slot_id' => 'この枠にはお客様が入っていません。']);
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
     */
    public function setUndecided(Customer $customer, array $attrs): PhotoSlot
    {
        return $this->run(function () use ($customer, $attrs) {
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
        });
    }

    /**
     * 予約済みの席・行を詳細未決定に戻す。普通の席だったら、席は空きに戻して新しく未決定の行を作る。
     */
    public function makeUndecided(PhotoSlot $slot, array $attrs): PhotoSlot
    {
        $slotId = $slot->id;
        [$result, $vacatedId] = $this->run(function () use ($slotId, $attrs) {
            $slot = PhotoSlot::lockForUpdate()->findOrFail($slotId);
            if ($slot->details_undecided) {
                $slot->update($this->pickAttrs($attrs));
                $this->syncShops($slot, $this->shopIds($attrs));

                return [$slot, null];
            }
            $customer = Customer::findOrFail($slot->customer_id);
            $this->clearBooking($slot);

            return [$this->setUndecided($customer, $attrs), $slot->id];
        });
        if ($vacatedId) {
            $this->schedules->syncFor(PhotoSlot::find($vacatedId));
        }

        return $result;
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
