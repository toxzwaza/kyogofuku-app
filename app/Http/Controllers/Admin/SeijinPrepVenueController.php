<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PhotoStudio;
use App\Models\SeijinPrepVenue;
use App\Services\PhotoSlot\PhotoStudioHours;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

/**
 * 成人式のお支度場所のマスタ（自社会場／提携美容室）。
 * 権限は今回設けない（全員が登録・変更できる）。権限機能の整備で「成人式お支度の管理」権限を付ける予定
 * （docs/20261119_権限機能の整備_権限を設ける場所.md）。権限を付けるのは store／update／destroy。
 */
class SeijinPrepVenueController extends Controller
{
    public function index(Request $request)
    {
        $venues = SeijinPrepVenue::query()
            ->visibleToUser($request->user())
            ->withCount([
                'slots as capacity' => fn ($q) => $q->seats(),
                'slots as booked' => fn ($q) => $q->seats()->whereNotNull('customer_id'),
                'slots as unscheduled' => fn ($q) => $q->unscheduled(),
            ])
            ->orderByDesc('is_active')
            ->ordered()
            ->get()
            ->map(fn (SeijinPrepVenue $v) => $this->present($v) + [
                'capacity' => (int) $v->capacity,
                'booked' => (int) $v->booked,
                'unscheduled' => (int) $v->unscheduled,
            ]);

        return Inertia::render('Admin/SeijinPrepVenue/Index', [
            'venues' => $venues,
            'groupLabels' => PhotoStudioController::GROUP_LABELS,
            'kindLabels' => SeijinPrepVenue::KIND_LABELS,
        ]);
    }

    public function create(Request $request)
    {
        return Inertia::render('Admin/SeijinPrepVenue/Create', $this->formProps($request));
    }

    public function store(Request $request)
    {
        SeijinPrepVenue::create($this->validated($request));

        return redirect()->route('admin.seijin-prep-venues.index')->with('success', 'お支度場所を追加しました。');
    }

    public function edit(Request $request, SeijinPrepVenue $seijinPrepVenue)
    {
        abort_unless($seijinPrepVenue->isVisibleTo($request->user()), 404);

        return Inertia::render('Admin/SeijinPrepVenue/Edit', $this->formProps($request) + [
            'venue' => $this->present($seijinPrepVenue),
        ]);
    }

    public function update(Request $request, SeijinPrepVenue $seijinPrepVenue)
    {
        abort_unless($seijinPrepVenue->isVisibleTo($request->user()), 404);
        $seijinPrepVenue->update($this->validated($request));

        return redirect()->route('admin.seijin-prep-venues.index')->with('success', 'お支度場所を更新しました。');
    }

    public function destroy(Request $request, SeijinPrepVenue $seijinPrepVenue)
    {
        abort_unless($seijinPrepVenue->isVisibleTo($request->user()), 404);
        if ($seijinPrepVenue->slots()->exists()) {
            return redirect()->route('admin.seijin-prep-venues.index')
                ->with('error', 'このお支度場所には枠か登録があるため削除できません。使わなくなった場合は「無効」にしてください。');
        }
        $seijinPrepVenue->delete();

        return redirect()->route('admin.seijin-prep-venues.index')->with('success', 'お支度場所を削除しました。');
    }

    private function formProps(Request $request): array
    {
        return [
            'groupOptions' => $this->groupOptions($request),
            'kindOptions' => SeijinPrepVenue::KIND_LABELS,
            'intervals' => SeijinPrepVenue::INTERVALS,
            'maxCapacity' => SeijinPrepVenue::MAX_CAPACITY,
        ];
    }

    private function validated(Request $request): array
    {
        $v = $request->validate([
            'name' => 'required|string|max:255',
            'kind' => ['required', Rule::in(array_keys(SeijinPrepVenue::KIND_LABELS))],
            'shop_group' => ['required', Rule::in(array_keys($this->groupOptions($request)))],
            'is_active' => 'boolean',
            'sort_order' => 'nullable|integer|min:0|max:9999',
            'address' => 'nullable|string|max:255',
            'phone' => 'nullable|string|max:50',
            'remarks' => 'nullable|string',
            'slot_interval_minutes' => ['required', 'integer', Rule::in(SeijinPrepVenue::INTERVALS)],
            'default_start_time' => 'nullable|date_format:H:i|required_with:default_end_time',
            'default_end_time' => 'nullable|date_format:H:i|required_with:default_start_time',
            'default_capacity' => 'required|integer|min:1|max:'.SeijinPrepVenue::MAX_CAPACITY,
        ], [], [
            'name' => '名前',
            'kind' => '種類',
            'shop_group' => '店舗グループ',
            'slot_interval_minutes' => '枠の刻み',
            'default_start_time' => '開始時刻',
            'default_end_time' => '終了時刻',
            'default_capacity' => '既定の定員',
        ]);

        $interval = (int) $v['slot_interval_minutes'];
        $errors = [];
        foreach (['default_start_time', 'default_end_time'] as $f) {
            if (! empty($v[$f]) && PhotoStudioHours::toMinutes($v[$f]) % $interval !== 0) {
                $errors[$f] = "時刻は{$interval}分刻みにしてください。";
            }
        }
        if (! empty($v['default_start_time']) && ! empty($v['default_end_time'])
            && PhotoStudioHours::toMinutes($v['default_start_time']) >= PhotoStudioHours::toMinutes($v['default_end_time'])) {
            $errors['default_end_time'] = '終了時刻は開始時刻より後にしてください。';
        }
        if ($errors) {
            throw ValidationException::withMessages($errors);
        }

        return [
            'name' => trim($v['name']),
            'kind' => $v['kind'],
            'shop_group' => $v['shop_group'],
            'is_active' => (bool) ($v['is_active'] ?? true),
            'sort_order' => (int) ($v['sort_order'] ?? 0),
            'address' => $v['address'] ?? null,
            'phone' => $v['phone'] ?? null,
            'remarks' => $v['remarks'] ?? null,
            'slot_interval_minutes' => $interval,
            'default_start_time' => $v['default_start_time'] ?? null,
            'default_end_time' => $v['default_end_time'] ?? null,
            'default_capacity' => (int) $v['default_capacity'],
        ];
    }

    /** 選べる店舗グループ（自分のグループだけ。システム管理者は全部） */
    private function groupOptions(Request $request): array
    {
        $groups = PhotoStudio::visibleGroupsFor($request->user());
        if ($groups === null) {
            return PhotoStudioController::GROUP_LABELS;
        }

        return array_intersect_key(PhotoStudioController::GROUP_LABELS, array_flip($groups));
    }

    public static function present(SeijinPrepVenue $v): array
    {
        return [
            'id' => $v->id,
            'name' => $v->name,
            'kind' => $v->kind,
            'shop_group' => $v->shop_group,
            'is_active' => (bool) $v->is_active,
            'sort_order' => (int) $v->sort_order,
            'address' => $v->address,
            'phone' => $v->phone,
            'remarks' => $v->remarks,
            'slot_interval_minutes' => $v->interval(),
            'default_start_time' => PhotoStudioHours::hm($v->default_start_time),
            'default_end_time' => PhotoStudioHours::hm($v->default_end_time),
            'default_capacity' => (int) $v->default_capacity,
        ];
    }
}
