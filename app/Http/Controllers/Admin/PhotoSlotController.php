<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\ResolvesUiView;
use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\PhotoSlot;
use App\Models\PhotoStudio;
use App\Models\Plan;
use App\Models\Shop;
use App\Models\StaffSchedule;
use App\Models\User;
use App\Services\PhotoSlot\PhotoSlotBookingService;
use App\Services\PhotoSlot\PhotoStudioHours;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

/**
 * 前撮り：一覧・表・予約（入れる・移す・取り消す）。
 * 枠そのものの管理（まとめて作る・1枠足す・定員・空き枠を消す）は PhotoSlotBulkController／PhotoSlotSeatController。
 */
class PhotoSlotController extends Controller
{
    use ResolvesUiView;

    /**
     * 前撮り枠一覧。自分の店舗グループの枠だけ、既定は今日以降（詳細未決定の行は期間に関係なく出す）
     */
    public function index(Request $request)
    {
        $validated = $request->validate([
            'from' => 'nullable|date_format:Y-m-d',
            'to' => 'nullable|date_format:Y-m-d',
        ]);
        $from = $validated['from'] ?? Carbon::today()->toDateString();
        $to = $validated['to'] ?? null;
        $user = $request->user();

        $photoSlots = PhotoSlot::query()
            ->visibleToUser($user)
            ->with(['studio', 'customer', 'shops', 'user', 'plan'])
            ->where(function ($q) use ($from, $to) {
                $q->where(function ($d) use ($from, $to) {
                    $d->where('shoot_date', '>=', $from);
                    if ($to) {
                        $d->where('shoot_date', '<=', $to);
                    }
                })->orWhereNull('shoot_date');
            })
            ->orderBy('shoot_date')
            ->orderBy('shoot_time')
            ->orderBy('seat_no')
            ->get();

        $isLegacyView = $this->viewFor('Admin/PhotoSlot/Index') !== 'Admin/PhotoSlot/Index';

        $props = [
            'photoSlots' => $photoSlots,
            'photoStudios' => PhotoStudio::query()->visibleToUser($user)->orderBy('name')->get(),
            'shops' => Shop::where('is_active', true)->orderBy('name')->get(),
            'plans' => Plan::orderBy('name')->get(),
            'filters' => ['from' => $from, 'to' => $to],
            'maxCapacity' => PhotoStudio::MAX_CAPACITY,
        ];

        if ($isLegacyView) {
            // 旧の画面（IndexLegacy）は、空き枠の一覧と予定の有無を使う
            $props['users'] = User::orderBy('name')->get();
            $props['availablePhotoSlots'] = $photoSlots->whereNull('customer_id')->values();
            $props['scheduledSlotIds'] = StaffSchedule::whereIn('photo_slot_id', $photoSlots->pluck('id'))->pluck('photo_slot_id')->all();
        }

        return Inertia::render($this->viewFor('Admin/PhotoSlot/Index'), $props);
    }

    /**
     * 表の画面のデータ：選んだスタジオ（複数）の1か月。登録済みの枠がある日・時刻だけを出す。
     * 列は「日付×スタジオ」（その日に枠のあるスタジオだけ）、行は枠のある時刻だけ。
     */
    public function grid(Request $request)
    {
        $validated = $request->validate([
            'studio_ids' => 'required|array|min:1',
            'studio_ids.*' => 'integer',
            'month' => 'nullable|date_format:Y-m',
        ]);
        $studios = PhotoStudio::query()
            ->visibleToUser($request->user())
            ->whereIn('id', $validated['studio_ids'])
            ->orderBy('name')
            ->get();
        $studioIds = $studios->pluck('id')->all();

        $start = Carbon::createFromFormat('Y-m-d', ($validated['month'] ?? Carbon::today()->format('Y-m')).'-01')->startOfDay();
        $end = $start->copy()->endOfMonth();
        $today = Carbon::today()->toDateString();

        $slots = PhotoSlot::query()
            ->with(['customer:id,name', 'shops:id,name', 'user:id,name', 'plan:id,name'])
            ->whereIn('photo_studio_id', $studioIds)
            ->where('details_undecided', false)
            ->whereBetween('shoot_date', [$start->toDateString(), $end->toDateString()])
            ->whereNotNull('shoot_time')
            ->orderBy('shoot_date')
            ->orderBy('shoot_time')
            ->orderBy('seat_no')
            ->get();

        $cells = [];
        $times = [];
        foreach ($slots as $slot) {
            $key = Carbon::parse($slot->shoot_date)->toDateString().'|'.$slot->photo_studio_id;
            $time = PhotoStudioHours::hm($slot->shoot_time);
            $times[$time] = true;
            $cells[$key][$time][] = [
                'id' => $slot->id,
                'seat_no' => $slot->seat_no,
                'customer' => $slot->customer ? ['id' => $slot->customer->id, 'name' => $slot->customer->name] : null,
                'shops' => $slot->shops->pluck('name')->values(),
                'user' => $slot->user?->name,
                'plan' => $slot->plan?->name,
                'remarks' => $slot->remarks,
            ];
        }
        ksort($times);

        // 列：日付順、同じ日はスタジオの名前順
        $studioInfo = $studios->mapWithKeys(fn (PhotoStudio $s) => [$s->id => [
            'id' => $s->id,
            'name' => trim($s->name),
            'shop_group' => $s->shop_group,
            'default_capacity' => (int) $s->default_capacity,
            'is_active' => (bool) $s->is_active,
        ]]);
        $order = array_flip($studioIds);
        $keys = array_keys($cells);
        usort($keys, function ($a, $b) use ($order) {
            [$da, $sa] = explode('|', $a);
            [$db, $sb] = explode('|', $b);

            return [$da, $order[$sa]] <=> [$db, $order[$sb]];
        });

        $columns = [];
        $cellList = [];
        foreach ($keys as $key) {
            [$date, $studioId] = explode('|', $key);
            $d = Carbon::parse($date);
            $columns[] = [
                'key' => $key,
                'date' => $date,
                'weekday' => $d->dayOfWeek,
                'is_today' => $date === $today,
                'is_past' => $date < $today,
                'studio' => $studioInfo[(int) $studioId],
            ];
            foreach ($cells[$key] as $time => $seats) {
                $cellList[$key][$time] = [
                    'capacity' => count($seats),
                    'booked' => count(array_filter($seats, fn ($s) => $s['customer'] !== null)),
                    'seats' => $seats,
                ];
            }
        }

        // 今月以降で枠のある月（すぐ移れるように）
        $months = PhotoSlot::query()
            ->whereIn('photo_studio_id', $studioIds)
            ->where('details_undecided', false)
            ->where('shoot_date', '>=', Carbon::today()->startOfMonth()->toDateString())
            ->whereNotNull('shoot_time')
            ->get(['shoot_date', 'customer_id'])
            ->groupBy(fn ($s) => Carbon::parse($s->shoot_date)->format('Y-m'))
            ->map(fn ($g, $ym) => ['month' => $ym, 'capacity' => $g->count(), 'booked' => $g->whereNotNull('customer_id')->count()])
            ->sortKeys()
            ->values();

        return response()->json([
            'studios' => $studioInfo->values(),
            'month' => $start->format('Y-m'),
            'prev_month' => $start->copy()->subMonthNoOverflow()->format('Y-m'),
            'next_month' => $start->copy()->addMonthNoOverflow()->format('Y-m'),
            'months' => $months,
            'columns' => $columns,
            'times' => array_keys($times),
            'cells' => (object) $cellList,
            'max_capacity' => PhotoStudio::MAX_CAPACITY,
        ]);
    }

    /**
     * スタジオの空き状況（顧客詳細・一覧の編集のモーダル用）：今日から180日の、日付ごと・時刻ごとの「空き／定員」
     * exclude_slot：いま編集しているお客様の席（自分の席は空きとして数える）
     */
    public function availability(Request $request)
    {
        $validated = $request->validate([
            'studio' => 'required|integer',
            'exclude_slot' => 'nullable|integer',
        ]);
        $studio = PhotoStudio::find($validated['studio']);
        if (! $studio || ! $studio->isVisibleTo($request->user())) {
            abort(404);
        }
        $excludeId = $validated['exclude_slot'] ?? 0;

        $rows = PhotoSlot::query()
            ->where('photo_studio_id', $studio->id)
            ->where('details_undecided', false)
            ->whereBetween('shoot_date', [Carbon::today()->toDateString(), Carbon::today()->addDays(180)->toDateString()])
            ->whereNotNull('shoot_time')
            ->selectRaw('shoot_date, shoot_time, COUNT(*) as capacity, SUM(CASE WHEN customer_id IS NULL OR id = ? THEN 1 ELSE 0 END) as free', [$excludeId])
            ->groupBy('shoot_date', 'shoot_time')
            ->orderBy('shoot_date')
            ->orderBy('shoot_time')
            ->get();

        $dates = [];
        foreach ($rows as $r) {
            $date = Carbon::parse($r->shoot_date)->toDateString();
            $dates[$date][] = [
                'time' => substr($r->shoot_time, 0, 5),
                'capacity' => (int) $r->capacity,
                'free' => (int) $r->free,
            ];
        }

        return response()->json([
            'dates' => collect($dates)->map(fn ($times, $date) => ['date' => $date, 'times' => $times])->values(),
        ]);
    }

    /**
     * 表の画面から予約を入れる（空いている一番小さい席に入る）
     */
    public function book(Request $request, PhotoSlotBookingService $booking)
    {
        $validated = $request->validate([
            'customer_id' => 'required|exists:customers,id',
            'photo_studio_id' => 'required|integer',
            'shoot_date' => 'required|date_format:Y-m-d',
            'shoot_time' => 'required|date_format:H:i',
            'shop_id' => 'nullable|exists:shops,id',
            'assignment_label' => 'nullable|string|max:255',
            'user_id' => 'nullable|exists:users,id',
            'plan_id' => 'nullable|exists:plans,id',
            'remarks' => 'nullable|string',
            // もう登録のある人を移すと確認したとき、その登録の ID（なければ二重登録として止める）
            'replace_photo_slot_id' => 'nullable|integer',
        ], [
            'customer_id.required' => 'お客様を選んでください。',
        ]);

        $studio = PhotoStudio::find($validated['photo_studio_id']);
        if (! $studio || ! $studio->isVisibleTo($request->user())) {
            return response()->json(['message' => 'このスタジオは選べません。', 'errors' => ['photo_studio_id' => ['このスタジオは選べません。']]], 422);
        }

        $slot = $booking->assign(
            Customer::findOrFail($validated['customer_id']),
            $studio->id,
            $validated['shoot_date'],
            $validated['shoot_time'],
            [
                'shop_id' => $validated['shop_id'] ?? null,
                'assignment_label' => $validated['assignment_label'] ?? null,
                'user_id' => $validated['user_id'] ?? null,
                'plan_id' => $validated['plan_id'] ?? null,
                'remarks' => $validated['remarks'] ?? null,
            ],
            isset($validated['replace_photo_slot_id']) ? (int) $validated['replace_photo_slot_id'] : null,
            $request->user()
        );

        return response()->json(['success' => true, 'photo_slot_id' => $slot->id]);
    }

    /**
     * 一覧の編集：担当・プラン・備考を直す。お客様の入っている枠は、選んだ時刻の空き席へ移せる。
     * 移す先は「スタジオ・日付・時刻」（新しい画面）か「枠の ID」（旧の画面）で受け取る。
     */
    public function update(Request $request, PhotoSlot $photoSlot, PhotoSlotBookingService $booking)
    {
        $this->ensureVisible($request, $photoSlot);

        $validated = $request->validate([
            'photo_slot_id' => 'nullable|required_without:shoot_time|exists:photo_slots,id',
            'photo_studio_id' => 'nullable|required_without:photo_slot_id|integer',
            'shoot_date' => 'nullable|required_without:photo_slot_id|date_format:Y-m-d',
            'shoot_time' => 'nullable|required_without:photo_slot_id|date_format:H:i',
            'shop_ids' => 'nullable|array',
            'shop_ids.*' => 'exists:shops,id',
            'assignment_label' => 'nullable|string|max:255',
            'user_id' => 'nullable|exists:users,id',
            'plan_id' => 'nullable|exists:plans,id',
            'remarks' => 'nullable|string',
        ]);

        if (! empty($validated['shoot_time'])) {
            $studioId = (int) $validated['photo_studio_id'];
            $date = $validated['shoot_date'];
            $time = $validated['shoot_time'];
            $isSameSeat = $photoSlot->photo_studio_id === $studioId
                && $photoSlot->shoot_date && Carbon::parse($photoSlot->shoot_date)->toDateString() === $date
                && PhotoStudioHours::hm($photoSlot->shoot_time) === $time;
        } else {
            $target = PhotoSlot::findOrFail($validated['photo_slot_id']);
            if ($target->details_undecided || ! $target->shoot_time) {
                return back()->withErrors(['photo_slot_id' => '日時の決まった枠を選んでください。']);
            }
            $studioId = (int) $target->photo_studio_id;
            $date = Carbon::parse($target->shoot_date)->toDateString();
            $time = PhotoStudioHours::hm($target->shoot_time);
            $isSameSeat = $target->id === $photoSlot->id;
        }

        $attrs = [
            'shop_ids' => $validated['shop_ids'] ?? [],
            'assignment_label' => $validated['assignment_label'] ?? null,
            'user_id' => $validated['user_id'] ?? null,
            'plan_id' => $validated['plan_id'] ?? null,
            'remarks' => $validated['remarks'] ?? null,
        ];

        if ($photoSlot->customer_id === null) {
            // 空き枠：移すものがないので、内容だけ直す
            if (! $isSameSeat) {
                return back()->withErrors(['photo_slot_id' => 'お客様が入っていない枠は移せません。']);
            }
            $photoSlot->update(collect($attrs)->except('shop_ids')->all());
            $photoSlot->shops()->sync($attrs['shop_ids']);
        } else {
            $studio = PhotoStudio::find($studioId);
            if (! $studio || ! $studio->isVisibleTo($request->user())) {
                return back()->withErrors(['photo_studio_id' => 'このスタジオは選べません。']);
            }
            // 選んだ時刻の空いている席へ移す（同じ時刻ならそのまま）。元の席は空きに戻る
            $booking->move($photoSlot, $studioId, $date, $time, $attrs);
        }

        return back()->with('success', '前撮り枠を更新しました。');
    }

    /**
     * 空き枠をまとめて別の日・スタジオへ動かす（一覧の日付の「編集」）。お客様の入っている枠は動かさない。
     */
    public function bulkUpdate(Request $request)
    {
        $validated = $request->validate([
            'slot_ids' => 'required|array|min:1',
            'slot_ids.*' => 'integer|exists:photo_slots,id',
            'shoot_date' => 'required|date_format:Y-m-d',
            'photo_studio_id' => 'required|exists:photo_studios,id',
            'shop_ids' => 'nullable|array',
            'shop_ids.*' => 'exists:shops,id',
        ]);

        $studio = PhotoStudio::find($validated['photo_studio_id']);
        if (! $studio || ! $studio->isVisibleTo($request->user())) {
            return back()->withErrors(['photo_studio_id' => 'このスタジオは選べません。']);
        }

        $slotIds = array_values(array_unique(array_map('intval', $validated['slot_ids'])));
        $slots = PhotoSlot::query()->visibleToUser($request->user())->whereKey($slotIds)->get();

        $updatedCount = 0;
        $errors = [];

        foreach ($slots as $photoSlot) {
            // 席を押さえて読み直す（読んだ後に予約が入った席は動かさない）
            DB::transaction(function () use ($photoSlot, $studio, $validated, $slotIds, &$updatedCount, &$errors) {
                $photoSlot = PhotoSlot::whereKey($photoSlot->id)->lockForUpdate()->first();
                if (! $photoSlot) {
                    return;
                }
                $label = $photoSlot->shoot_time ? PhotoStudioHours::hm($photoSlot->shoot_time).'（'.$photoSlot->seat_no.'席目）' : 'ID '.$photoSlot->id;
                if ($photoSlot->customer_id !== null) {
                    $errors[] = "{$label} はお客様が入っているため動かしませんでした。";
                    return;
                }
                if ($photoSlot->details_undecided || ! $photoSlot->shoot_time) {
                    $errors[] = "{$label} は日時が決まっていないため動かしませんでした。";
                    return;
                }

                // 移す先に同じ時刻の枠が（今回いっしょに動かす枠以外で）あれば飛ばす。席が混ざって定員が変わらないように
                $exists = PhotoSlot::where('photo_studio_id', $studio->id)
                    ->whereDate('shoot_date', $validated['shoot_date'])
                    ->where('shoot_time', $photoSlot->shoot_time)
                    ->whereNotIn('id', $slotIds)
                    ->exists();
                if ($exists) {
                    $errors[] = "{$label} は移す先に同じ時刻の枠があるため動かしませんでした。";
                    return;
                }

                $photoSlot->update([
                    'shoot_date' => $validated['shoot_date'],
                    'photo_studio_id' => $studio->id,
                ]);
                $photoSlot->shops()->sync($validated['shop_ids'] ?? []);
                $updatedCount++;
            });
        }

        $skipped = count($slotIds) - $updatedCount;
        $message = "{$updatedCount}件の前撮り枠を更新しました。";
        if ($skipped > 0) {
            $message .= "（{$skipped}件はそのまま）";
        }

        $redirect = back()->with('success', $message);
        if ($errors) {
            $redirect->with('slotErrors', $errors);
        }

        return $redirect;
    }

    /**
     * 予約を取り消す。席は空きとして残し、予定とカレンダーの予定を消す（詳細未決定の行は消す）
     */
    public function releaseCustomer(Request $request, PhotoSlot $photoSlot, PhotoSlotBookingService $booking)
    {
        $this->ensureVisible($request, $photoSlot);
        if ($photoSlot->customer_id === null) {
            return back()->withErrors(['error' => 'この前撮り枠には顧客が紐づいていません。']);
        }

        $booking->release($photoSlot);

        return back()->with('success', '顧客の紐付けを解除しました。');
    }

    /**
     * 自分の店舗グループの枠でなければ 404
     */
    private function ensureVisible(Request $request, PhotoSlot $slot): void
    {
        if (! PhotoSlot::query()->visibleToUser($request->user())->whereKey($slot->id)->exists()) {
            abort(404);
        }
    }
}
