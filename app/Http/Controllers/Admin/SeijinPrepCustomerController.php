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

/**
 * お客様の詳細画面の「成人式お支度」パネル：見る・登録/変更・取り消し。
 */
class SeijinPrepCustomerController extends Controller
{
    public function __construct(private SeijinPrepService $service)
    {
    }

    public function show(Request $request, Customer $customer)
    {
        $user = $request->user();
        $row = SeijinPrepSlot::with('venue')->where('customer_id', $customer->id)->first();
        $hidden = $row && ! SeijinPrepSlot::query()->visibleToUser($user)->whereKey($row->id)->exists();

        $venues = SeijinPrepVenue::query()->visibleToUser($user)->active()->ordered()->get();
        if ($row?->venue && ! $hidden && ! $venues->contains('id', $row->venue->id)) {
            $venues->push($row->venue); // 無効にした場所に入っている人も表示できるように
        }

        return response()->json([
            'prep' => $hidden ? null : ($row ? $this->present($row) : null),
            'hidden' => $hidden, // ほかの店舗グループで登録がある
            'venues' => $venues->map(fn (SeijinPrepVenue $v) => [
                'id' => $v->id,
                'name' => $v->name,
                'kind' => $v->kind,
                'kind_label' => SeijinPrepVenue::KIND_LABELS[$v->kind] ?? $v->kind,
                'shop_group' => $v->shop_group,
                'interval' => $v->interval(),
                'numbered' => $v->isNumbered(), // 福井：時刻なしの番号枠
                'is_active' => (bool) $v->is_active,
            ])->values(),
            'shops' => Shop::query()->where('is_active', true)->orderBy('id')->get(['id', 'name', 'group_key']),
            'guides' => SeijinPrepService::REMARKS_GUIDES,
        ]);
    }

    /**
     * 時刻の空き（場所と日を選んだときに、選べる時刻を出す）。番号枠（福井）はその日の枠を 1 つ（time は null）で返す
     */
    public function times(Request $request, Customer $customer)
    {
        $v = $request->validate([
            'venue_id' => 'required|integer',
            'prep_date' => 'required|date_format:Y-m-d',
        ]);
        $venue = SeijinPrepVenue::find($v['venue_id']);
        abort_unless($venue && $venue->isVisibleTo($request->user()), 404);

        $times = SeijinPrepSlot::query()
            ->where('seijin_prep_venue_id', $venue->id)
            ->whereDate('prep_date', $v['prep_date'])
            ->seats()
            ->orderBy('prep_time')->orderBy('seat_no')
            ->when($venue->isNumbered(), fn ($q) => $q->whereNull('prep_time'), fn ($q) => $q->whereNotNull('prep_time'))
            ->get(['id', 'prep_time', 'customer_id'])
            ->groupBy(fn ($s) => PhotoStudioHours::hm($s->prep_time) ?? '')
            ->map(fn ($g, $time) => [
                'time' => $time === '' ? null : $time,
                'capacity' => $g->count(),
                'booked' => $g->whereNotNull('customer_id')->count(),
                'mine' => $g->contains('customer_id', $customer->id),
            ])
            ->values();

        return response()->json(['times' => $times]);
    }

    /** 枠のある日だけを返す（日の候補） */
    public function dates(Request $request, Customer $customer)
    {
        $v = $request->validate(['venue_id' => 'required|integer']);
        $venue = SeijinPrepVenue::find($v['venue_id']);
        abort_unless($venue && $venue->isVisibleTo($request->user()), 404);

        $dates = SeijinPrepSlot::query()
            ->where('seijin_prep_venue_id', $venue->id)
            ->seats()
            ->where('prep_date', '>=', Carbon::today()->toDateString())
            ->orderBy('prep_date')
            ->pluck('prep_date')
            ->map(fn ($d) => Carbon::parse($d)->toDateString())
            ->unique()
            ->values();

        return response()->json(['dates' => $dates]);
    }

    public function save(Request $request, Customer $customer)
    {
        $v = $request->validate([
            'mode' => 'required|in:venue,other,undecided',
            'venue_id' => 'nullable|required_if:mode,venue|integer',
            'prep_date' => 'nullable|date_format:Y-m-d|required_with:prep_time',
            'prep_time' => 'nullable|date_format:H:i',
            'has_makeup' => 'nullable|boolean',
            'preferred_time' => 'nullable|string|max:50',
            'remarks' => 'nullable|string|max:2000',
            'shop_id' => 'nullable|integer|exists:shops,id',
        ], [
            'venue_id.required_if' => 'お支度場所を選んでください。',
            'prep_date.required_with' => '時刻を選ぶときは日も選んでください。',
        ]);
        $isVenue = $v['mode'] === 'venue';
        $venue = $isVenue ? SeijinPrepVenue::find($v['venue_id']) : null;
        $numbered = (bool) $venue?->isNumbered();
        if ($numbered) {
            $v['prep_time'] = null; // 福井は時刻を持たない
        } elseif (! empty($v['prep_date']) && empty($v['prep_time'])) {
            throw ValidationException::withMessages(['prep_time' => '日を選ぶときは時刻も選んでください。']);
        }
        if ($isVenue && ! empty($v['prep_date'])) {
            $current = SeijinPrepSlot::where('customer_id', $customer->id)->first();
            // 過去の日は、今いる席（場所・日・時刻が同じ）のまま保存するときだけ通す
            $sameSeat = $current && $current->isSeat()
                && (int) $current->seijin_prep_venue_id === (int) $v['venue_id']
                && Carbon::parse($current->prep_date)->toDateString() === $v['prep_date']
                && PhotoStudioHours::hm($current->prep_time) === ($v['prep_time'] ?? null);
            if (! $sameSeat && Carbon::parse($v['prep_date'])->lt(Carbon::today())) {
                throw ValidationException::withMessages(['prep_date' => '過去の日の枠には入れられません。']);
            }
        }

        $this->service->save($customer, [
            'venue_id' => $isVenue ? (int) $v['venue_id'] : null,
            'is_other_store' => $v['mode'] === 'other',
            'prep_date' => $isVenue ? ($v['prep_date'] ?? null) : null,
            'prep_time' => $isVenue ? ($v['prep_time'] ?? null) : null,
            // メイク有無は福井の場所に登録するときだけ（必須。確かめるのはサービス）
            'has_makeup' => $numbered && array_key_exists('has_makeup', $v) && $v['has_makeup'] !== null ? (bool) $v['has_makeup'] : null,
            'preferred_time' => $v['preferred_time'] ?? null,
            'remarks' => $v['remarks'] ?? null,
            'shop_id' => $v['shop_id'] ?? $customer->shop_id,
        ], $request->user());

        return $this->done($request, '成人式お支度を保存しました。');
    }

    public function destroy(Request $request, Customer $customer)
    {
        // ほかの店舗グループの登録かどうかは、サービスのトランザクションの中で確かめる
        $this->service->cancel($customer, $request->user());

        return $this->done($request, '成人式お支度の登録を取り消しました。');
    }

    private function present(SeijinPrepSlot $row): array
    {
        return [
            'id' => $row->id,
            'mode' => $row->is_other_store ? 'other' : ($row->seijin_prep_venue_id ? 'venue' : 'undecided'),
            'venue_id' => $row->seijin_prep_venue_id,
            'venue_name' => $row->venue?->name,
            'prep_date' => $row->prep_date ? Carbon::parse($row->prep_date)->toDateString() : null,
            'prep_time' => PhotoStudioHours::hm($row->prep_time),
            'seat_no' => $row->isSeat() ? $row->seat_no : null,
            'numbered' => (bool) $row->venue?->isNumbered(),
            'has_makeup' => $row->has_makeup,
            'original_prep_time' => PhotoStudioHours::hm($row->original_prep_time),
            'preferred_time' => $row->preferred_time,
            'remarks' => $row->remarks,
            'shop_id' => $row->shop_id,
            'label' => $this->service->describe($row),
        ];
    }

    private function done(Request $request, string $message)
    {
        if ($request->expectsJson()) {
            return response()->json(['success' => true, 'message' => $message]);
        }

        return back()->with('success', $message);
    }
}
