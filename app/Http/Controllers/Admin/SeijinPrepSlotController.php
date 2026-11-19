<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\SeijinPrepSlot;
use App\Models\SeijinPrepVenue;
use App\Models\Shop;
use App\Services\PhotoSlot\PhotoStudioHours;
use App\Services\SeijinPrep\SeijinPrepService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

/**
 * 成人式お支度の枠：一覧（場所ごとの集計・時刻未定の人）・月の表・枠に入れる・CSV。
 * 権限は今回設けない（docs/20261119_権限機能の整備_権限を設ける場所.md）。
 */
class SeijinPrepSlotController extends Controller
{
    public function __construct(private SeijinPrepService $service)
    {
    }

    public function index(Request $request)
    {
        $user = $request->user();
        $venues = SeijinPrepVenue::query()
            ->visibleToUser($user)
            ->withCount([
                'slots as capacity' => fn ($q) => $q->seats(),
                'slots as booked' => fn ($q) => $q->seats()->whereNotNull('customer_id'),
                'slots as unscheduled' => fn ($q) => $q->unscheduled(),
            ])
            ->orderByDesc('is_active')
            ->ordered()
            ->get()
            ->map(fn (SeijinPrepVenue $v) => SeijinPrepVenueController::present($v) + [
                'capacity' => (int) $v->capacity,
                'booked' => (int) $v->booked,
                'unscheduled' => (int) $v->unscheduled,
            ]);

        // 時刻の枠に入っていない人（場所だけ・他店・未定）
        $unscheduled = SeijinPrepSlot::query()
            ->with(['customer:id,name,kana,phone_number', 'venue:id,name,shop_group', 'shop:id,name'])
            ->unscheduled()
            ->visibleToUser($user)
            ->get()
            ->sortBy(fn (SeijinPrepSlot $r) => [$r->venue?->name ?? 'ん', $r->customer?->kana ?? $r->customer?->name])
            ->values()
            ->map(fn (SeijinPrepSlot $r) => [
                'id' => $r->id,
                'customer' => $r->customer ? ['id' => $r->customer->id, 'name' => $r->customer->name, 'phone_number' => $r->customer->phone_number] : null,
                'venue' => $r->venue ? ['id' => $r->venue->id, 'name' => $r->venue->name] : null,
                'is_other_store' => (bool) $r->is_other_store,
                'preferred_time' => $r->preferred_time,
                'remarks' => $r->remarks,
                'shop' => $r->shop?->name,
                'shop_id' => $r->shop_id,
            ]);

        $otherStore = $unscheduled->where('is_other_store', true)->count();
        $undecided = $unscheduled->filter(fn ($r) => ! $r['is_other_store'] && ! $r['venue'])->count();

        return Inertia::render('Admin/SeijinPrepSlot/Index', [
            'venues' => $venues,
            'unscheduled' => $unscheduled,
            'counts' => ['other_store' => $otherStore, 'undecided' => $undecided],
            'shops' => Shop::query()->where('is_active', true)->orderBy('id')->get(['id', 'name', 'group_key']),
            'guides' => SeijinPrepService::REMARKS_GUIDES,
            'groupLabels' => PhotoStudioController::GROUP_LABELS,
            'kindLabels' => SeijinPrepVenue::KIND_LABELS,
            'maxCapacity' => SeijinPrepVenue::MAX_CAPACITY,
        ]);
    }

    /**
     * 月の表（JSON）。形は前撮りの表（PhotoSlotController::grid）と同じにして、画面の部品を使い回す。
     * 列の studio にお支度場所を入れる。
     */
    public function grid(Request $request)
    {
        $validated = $request->validate([
            'studio_ids' => 'required|array|min:1',
            'studio_ids.*' => 'integer',
            'month' => 'nullable|date_format:Y-m',
        ]);
        $venues = SeijinPrepVenue::query()
            ->visibleToUser($request->user())
            ->whereIn('id', $validated['studio_ids'])
            ->ordered()
            ->get();
        $venueIds = $venues->pluck('id')->all();

        $start = Carbon::createFromFormat('Y-m-d', ($validated['month'] ?? Carbon::today()->format('Y-m')).'-01')->startOfDay();
        $end = $start->copy()->endOfMonth();
        $today = Carbon::today()->toDateString();

        $slots = SeijinPrepSlot::query()
            ->with(['customer:id,name', 'shop:id,name'])
            ->whereIn('seijin_prep_venue_id', $venueIds)
            ->seats()
            ->whereBetween('prep_date', [$start->toDateString(), $end->toDateString()])
            ->orderBy('prep_date')->orderBy('prep_time')->orderBy('seat_no')
            ->get();

        $cells = [];
        $times = [];
        foreach ($slots as $slot) {
            $key = Carbon::parse($slot->prep_date)->toDateString().'|'.$slot->seijin_prep_venue_id;
            $time = PhotoStudioHours::hm($slot->prep_time);
            $times[$time] = true;
            $cells[$key][$time][] = [
                'id' => $slot->id,
                'seat_no' => $slot->seat_no,
                'customer' => $slot->customer ? ['id' => $slot->customer->id, 'name' => $slot->customer->name] : null,
                'shops' => $slot->shop ? [$slot->shop->name] : [],
                'user' => null,
                'plan' => $slot->preferred_time ? "希望 {$slot->preferred_time}" : null,
                'remarks' => $slot->remarks,
            ];
        }
        ksort($times);

        $info = $venues->mapWithKeys(fn (SeijinPrepVenue $v) => [$v->id => [
            'id' => $v->id,
            'name' => $v->name,
            'shop_group' => $v->shop_group,
            'default_capacity' => (int) $v->default_capacity,
            'is_active' => (bool) $v->is_active,
        ]]);
        $order = array_flip($venueIds);
        $keys = array_keys($cells);
        usort($keys, function ($a, $b) use ($order) {
            [$da, $va] = explode('|', $a);
            [$db, $vb] = explode('|', $b);

            return [$da, $order[$va]] <=> [$db, $order[$vb]];
        });

        $columns = [];
        $cellList = [];
        foreach ($keys as $key) {
            [$date, $venueId] = explode('|', $key);
            $columns[] = [
                'key' => $key,
                'date' => $date,
                'weekday' => Carbon::parse($date)->dayOfWeek,
                'is_today' => $date === $today,
                'is_past' => $date < $today,
                'studio' => $info[(int) $venueId],
            ];
            foreach ($cells[$key] as $time => $seats) {
                $cellList[$key][$time] = [
                    'capacity' => count($seats),
                    'booked' => count(array_filter($seats, fn ($s) => $s['customer'] !== null)),
                    'seats' => $seats,
                ];
            }
        }

        $months = SeijinPrepSlot::query()
            ->whereIn('seijin_prep_venue_id', $venueIds)
            ->seats()
            ->where('prep_date', '>=', Carbon::today()->startOfMonth()->toDateString())
            ->get(['prep_date', 'customer_id'])
            ->groupBy(fn ($s) => Carbon::parse($s->prep_date)->format('Y-m'))
            ->map(fn ($g, $ym) => ['month' => $ym, 'capacity' => $g->count(), 'booked' => $g->whereNotNull('customer_id')->count()])
            ->sortKeys()
            ->values();

        return response()->json([
            'studios' => $info->values(),
            'month' => $start->format('Y-m'),
            'prev_month' => $start->copy()->subMonthNoOverflow()->format('Y-m'),
            'next_month' => $start->copy()->addMonthNoOverflow()->format('Y-m'),
            'months' => $months,
            'columns' => $columns,
            'times' => array_keys($times),
            'cells' => (object) $cellList,
            'max_capacity' => SeijinPrepVenue::MAX_CAPACITY,
        ]);
    }

    /** その場所・日・時刻の空き席にお客様を入れる（もう登録があれば、そこから移す） */
    public function book(Request $request)
    {
        $v = $request->validate([
            'customer_id' => 'required|integer|exists:customers,id',
            'seijin_prep_venue_id' => 'required|integer',
            'prep_date' => 'required|date_format:Y-m-d',
            'prep_time' => 'required|date_format:H:i',
            'shop_id' => 'nullable|integer|exists:shops,id',
            'preferred_time' => 'nullable|string|max:50',
            'remarks' => 'nullable|string|max:2000',
        ]);
        $customer = Customer::findOrFail($v['customer_id']);
        // 今の値の引き継ぎ用に読むだけ。ほかの店舗グループの登録かどうかはサービスのトランザクションの中で確かめる
        $current = SeijinPrepSlot::query()->visibleToUser($request->user())->where('customer_id', $customer->id)->first();
        if (Carbon::parse($v['prep_date'])->lt(Carbon::today())) {
            throw ValidationException::withMessages(['prep_date' => '過去の日の枠には入れられません。']);
        }

        $row = $this->service->save($customer, [
            'venue_id' => (int) $v['seijin_prep_venue_id'],
            'is_other_store' => false,
            'prep_date' => $v['prep_date'],
            'prep_time' => $v['prep_time'],
            // 入力がなければ、今の登録の値を引き継ぐ
            'preferred_time' => $v['preferred_time'] ?? $current?->preferred_time,
            'remarks' => $v['remarks'] ?? $current?->remarks,
            'shop_id' => $v['shop_id'] ?? $current?->shop_id ?? $customer->shop_id,
        ], $request->user());

        $message = "{$customer->name} さんを {$v['prep_date']} {$v['prep_time']}（レーン{$row->seat_no}）に入れました。";
        if ($request->expectsJson()) {
            return response()->json(['success' => true, 'message' => $message]);
        }

        return back()->with('success', $message);
    }

    /** CSV（美容室・着付け師に渡す名簿）。venue_id を省くと見える全部、other なら他店お支度・未定 */
    public function csv(Request $request)
    {
        $venueId = $request->query('venue_id');
        $query = SeijinPrepSlot::query()
            ->with(['customer:id,name,kana,phone_number', 'venue:id,name', 'shop:id,name'])
            ->whereNotNull('customer_id')
            ->visibleToUser($request->user());
        if ($venueId === 'other') {
            $query->whereNull('seijin_prep_venue_id');
        } elseif ($venueId) {
            $query->where('seijin_prep_venue_id', (int) $venueId);
        }
        $rows = $query->get()->sortBy(fn (SeijinPrepSlot $r) => [
            $r->venue?->name ?? 'ん',
            $r->prep_date ? Carbon::parse($r->prep_date)->toDateString() : '9999',
            $r->prep_time ?? '99',
            $r->seat_no,
            $r->customer?->kana ?? '',
        ]);

        $filename = 'seijin_prep_'.Carbon::now()->format('Ymd_His').'.csv';

        return response()->streamDownload(function () use ($rows) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['お支度場所', '日', '時刻', 'レーン', 'お客様', 'ふりがな', '電話', '担当店舗', '他店お支度', '希望時刻', '備考']);
            foreach ($rows as $r) {
                fputcsv($out, [
                    $r->venue?->name ?? ($r->is_other_store ? '（他店お支度）' : '（未定）'),
                    $r->prep_date ? Carbon::parse($r->prep_date)->toDateString() : '',
                    $r->prep_time ? substr($r->prep_time, 0, 5) : '時刻未定',
                    $r->prep_time ? $r->seat_no : '',
                    $r->customer?->name,
                    $r->customer?->kana,
                    $r->customer?->phone_number,
                    $r->shop?->name,
                    $r->is_other_store ? 'あり' : '',
                    $r->preferred_time,
                    $r->remarks,
                ]);
            }
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
