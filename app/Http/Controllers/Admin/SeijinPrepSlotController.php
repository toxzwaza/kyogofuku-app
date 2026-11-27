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
    /** 月の表で、番号枠（福井）の席を入れる行の名前（時刻の代わり） */
    public const NUMBERED_ROW = '番号';

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
            'maxNumberedCapacity' => SeijinPrepVenue::MAX_NUMBERED_CAPACITY,
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
        $hasNumbered = false;
        foreach ($slots as $slot) {
            $key = Carbon::parse($slot->prep_date)->toDateString().'|'.$slot->seijin_prep_venue_id;
            $numbered = $slot->isNumberedSeat();
            // 番号枠（福井）の席は「番号」の行にまとめる
            $time = $numbered ? self::NUMBERED_ROW : PhotoStudioHours::hm($slot->prep_time);
            $numbered ? $hasNumbered = true : $times[$time] = true;
            $cells[$key][$time][] = [
                'id' => $slot->id,
                'seat_no' => $slot->seat_no,
                'no' => $numbered ? $slot->seat_no : null,
                'customer' => $slot->customer ? ['id' => $slot->customer->id, 'name' => $slot->customer->name] : null,
                'shops' => $slot->shop ? [$slot->shop->name] : [],
                'user' => null,
                'plan' => $numbered
                    ? ($slot->customer ? ($slot->has_makeup === null ? null : ($slot->has_makeup ? 'メイクあり' : 'メイクなし')) : null)
                    : ($slot->preferred_time ? "希望 {$slot->preferred_time}" : null),
                'remarks' => $slot->remarks,
            ];
        }
        ksort($times);
        if ($hasNumbered) {
            $times[self::NUMBERED_ROW] = true; // 時刻の行の後ろ
        }

        $info = $venues->mapWithKeys(fn (SeijinPrepVenue $v) => [$v->id => [
            'id' => $v->id,
            'name' => $v->name,
            'shop_group' => $v->shop_group,
            'default_capacity' => (int) $v->default_capacity,
            'numbered' => $v->isNumbered(),
            'max_capacity' => $v->maxCapacity(),
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
            'numbered_row' => self::NUMBERED_ROW,
        ]);
    }

    /**
     * その場所・日・時刻の空き席にお客様を入れる（もう登録があれば、そこから移す）。
     * 番号枠（福井）は時刻なしで、その日の次の番号に入る。メイク有無が必須
     */
    public function book(Request $request)
    {
        $venue = SeijinPrepVenue::find((int) $request->input('seijin_prep_venue_id'));
        $numbered = (bool) $venue?->isNumbered();
        $v = $request->validate([
            'customer_id' => 'required|integer|exists:customers,id',
            'seijin_prep_venue_id' => 'required|integer',
            'prep_date' => 'required|date_format:Y-m-d',
            'prep_time' => $numbered ? 'nullable' : 'required|date_format:H:i',
            'has_makeup' => 'nullable|boolean',
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
            'prep_time' => $numbered ? null : $v['prep_time'],
            'has_makeup' => $numbered && isset($v['has_makeup']) ? (bool) $v['has_makeup'] : null,
            // 入力がなければ、今の登録の値を引き継ぐ（福井の希望時刻はサービスが今の値を残す）
            'preferred_time' => $v['preferred_time'] ?? $current?->preferred_time,
            'remarks' => $v['remarks'] ?? $current?->remarks,
            'shop_id' => $v['shop_id'] ?? $current?->shop_id ?? $customer->shop_id,
        ], $request->user());

        $message = $numbered
            ? "{$customer->name} さんを {$v['prep_date']} の番号{$row->seat_no}に入れました。"
            : "{$customer->name} さんを {$v['prep_date']} {$v['prep_time']}（レーン{$row->seat_no}）に入れました。";
        if ($request->expectsJson()) {
            return response()->json(['success' => true, 'message' => $message]);
        }

        return back()->with('success', $message);
    }

    /**
     * CSV（美容室・着付け師に渡す名簿）。venue_id を省くと見える全部、other なら他店お支度・未定。
     * 福井の場所（番号枠）の CSV は美容室に渡す形（番号・住所・種別・メイク有無・出る会場）。
     * 岡山の場所の CSV は今までの形。全部の CSV は今までの形に、福井の行があればメイク有無を足す
     */
    public function csv(Request $request)
    {
        $venueId = $request->query('venue_id');
        $query = SeijinPrepSlot::query()
            ->with([
                'customer:id,name,kana,phone_number,postal_code,address,ceremony_area_id',
                'customer.ceremonyArea:id,name',
                'customer.contracts' => fn ($q) => $q->with('plan:id,name')->orderByDesc('contract_date')->orderByDesc('id'),
                'venue:id,name,shop_group',
                'shop:id,name',
            ])
            ->whereNotNull('customer_id')
            ->visibleToUser($request->user());
        $venue = null;
        if ($venueId === 'other') {
            $query->whereNull('seijin_prep_venue_id');
        } elseif ($venueId) {
            $query->where('seijin_prep_venue_id', (int) $venueId);
            $venue = SeijinPrepVenue::query()->visibleToUser($request->user())->find((int) $venueId);
        }
        $rows = $query->get()->sortBy(fn (SeijinPrepSlot $r) => [
            $r->venue?->name ?? 'ん',
            $r->prep_date ? Carbon::parse($r->prep_date)->toDateString() : '9999',
            $r->prep_time ?? '99',
            $r->seat_no,
            $r->customer?->kana ?? '',
        ]);

        $filename = 'seijin_prep_'.Carbon::now()->format('Ymd_His').'.csv';
        if ($venue?->isNumbered()) {
            [$header, $line] = $this->numberedCsv();
        } else {
            $withMakeup = $rows->contains(fn (SeijinPrepSlot $r) => (bool) $r->venue?->isNumbered());
            [$header, $line] = $this->timedCsv($withMakeup);
        }

        return response()->streamDownload(function () use ($rows, $header, $line) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, $header);
            foreach ($rows as $r) {
                fputcsv($out, $line($r));
            }
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /** 今までの形（岡山・全部・他店/未定）。$withMakeup なら最後にメイク有無 */
    private function timedCsv(bool $withMakeup): array
    {
        $header = ['お支度場所', '日', '時刻', 'レーン', 'お客様', 'ふりがな', '電話', '担当店舗', '他店お支度', '希望時刻', '備考'];

        return [
            $withMakeup ? array_merge($header, ['メイク有無']) : $header,
            function (SeijinPrepSlot $r) use ($withMakeup) {
                $numbered = $r->isNumberedSeat();
                $line = [
                    $r->venue?->name ?? ($r->is_other_store ? '（他店お支度）' : '（未定）'),
                    $r->prep_date ? Carbon::parse($r->prep_date)->toDateString() : '',
                    $numbered ? '番号'.$r->seat_no : ($r->prep_time ? substr($r->prep_time, 0, 5) : '時刻未定'),
                    $r->prep_time ? $r->seat_no : '',
                    $r->customer?->name,
                    $r->customer?->kana,
                    $r->customer?->phone_number,
                    $r->shop?->name,
                    $r->is_other_store ? 'あり' : '',
                    $r->preferred_time,
                    $r->remarks,
                ];

                return $withMakeup ? array_merge($line, [self::makeupLabel($r->has_makeup)]) : $line;
            },
        ];
    }

    /** 福井の美容室に渡す形 */
    private function numberedCsv(): array
    {
        return [
            ['お支度場所', '日', '番号', 'お客様', 'ふりがな', '電話', '郵便番号', '住所', '種別', 'メイク有無', '出る会場', '担当店舗', '備考', '元の時刻'],
            function (SeijinPrepSlot $r) {
                $c = $r->customer;

                return [
                    $r->venue?->name,
                    $r->prep_date ? Carbon::parse($r->prep_date)->toDateString() : '日未定',
                    $r->isNumberedSeat() ? $r->seat_no : '',
                    $c?->name,
                    $c?->kana,
                    $c?->phone_number,
                    $c?->postal_code,
                    $c?->address,
                    $c?->contracts->first()?->plan?->name, // 種別：最新の契約のプラン（レンタル／ママ振 など）
                    self::makeupLabel($r->has_makeup),
                    $c?->ceremonyArea?->name, // 出る会場（成人式エリア）
                    $r->shop?->name,
                    $r->remarks,
                    PhotoStudioHours::hm($r->original_prep_time), // 番号枠に移す前の時刻
                ];
            },
        ];
    }

    private static function makeupLabel(?bool $hasMakeup): string
    {
        return $hasMakeup === null ? '' : ($hasMakeup ? 'あり' : 'なし');
    }
}
