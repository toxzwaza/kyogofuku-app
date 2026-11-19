<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\ResolvesUiView;
use App\Http\Controllers\Controller;
use App\Models\PhotoStudio;
use App\Services\PhotoSlot\PhotoStudioHours;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

/**
 * 前撮りスタジオのマスタ（店舗グループ・営業時間・既定の定員）。
 * 権限は今回設けない（全員が登録・変更できる）。権限機能の整備で「前撮りの管理」権限を付ける予定
 * （docs/20261119_権限機能の整備_権限を設ける場所.md）。権限を付けるのは store／update／destroy。
 */
class PhotoStudioController extends Controller
{
    use ResolvesUiView;

    public const GROUP_LABELS = ['okayama' => '岡山', 'fukui' => '福井'];

    public function __construct(private PhotoStudioHours $hours)
    {
    }

    public function index(Request $request)
    {
        $photoStudios = PhotoStudio::with('weekdayHours')
            ->visibleToUser($request->user())
            ->orderByDesc('is_active')
            ->orderBy('name')
            ->paginate(20);

        $photoStudios->getCollection()->transform(fn (PhotoStudio $s) => $this->present($s));

        return Inertia::render($this->viewFor('Admin/PhotoStudio/Index'), [
            'photoStudios' => $photoStudios,
            'groupLabels' => self::GROUP_LABELS,
        ]);
    }

    public function create(Request $request)
    {
        return Inertia::render($this->viewFor('Admin/PhotoStudio/Create'), [
            'groupOptions' => $this->groupOptions($request),
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);

        DB::transaction(function () use ($data) {
            $studio = PhotoStudio::create($data['studio']);
            $this->syncWeekdayHours($studio, $data['weekday_hours']);
        });

        return redirect()->route('admin.photo-studios.index')
            ->with('success', 'スタジオを追加しました。');
    }

    public function edit(Request $request, PhotoStudio $photoStudio)
    {
        abort_unless($photoStudio->isVisibleTo($request->user()), 404);
        $photoStudio->load('weekdayHours');

        return Inertia::render($this->viewFor('Admin/PhotoStudio/Edit'), [
            'photoStudio' => $this->present($photoStudio),
            'groupOptions' => $this->groupOptions($request),
        ]);
    }

    public function update(Request $request, PhotoStudio $photoStudio)
    {
        abort_unless($photoStudio->isVisibleTo($request->user()), 404);
        $data = $this->validated($request);

        DB::transaction(function () use ($photoStudio, $data) {
            $photoStudio->update($data['studio']);
            $this->syncWeekdayHours($photoStudio, $data['weekday_hours']);
        });

        return redirect()->route('admin.photo-studios.index')
            ->with('success', 'スタジオを更新しました。');
    }

    public function destroy(Request $request, PhotoStudio $photoStudio)
    {
        abort_unless($photoStudio->isVisibleTo($request->user()), 404);

        // 前撮り枠が紐づいている場合は削除不可（無効にしてもらう）
        if ($photoStudio->photoSlots()->exists()) {
            return redirect()->route('admin.photo-studios.index')
                ->with('error', 'このスタジオには前撮り枠があるため削除できません。使わなくなった場合は「無効」にしてください。');
        }

        $photoStudio->delete();

        return redirect()->route('admin.photo-studios.index')
            ->with('success', 'スタジオを削除しました。');
    }

    /**
     * 入力を確かめる。時刻は30分刻み、開始 < 終了。既定の営業時間は開始・終了の両方か、どちらも空。
     *
     * @return array{studio:array,weekday_hours:array}
     */
    private function validated(Request $request): array
    {
        $timeRule = ['nullable', 'date_format:H:i', function ($attr, $value, $fail) {
            if ($value !== null && ! PhotoStudioHours::isOnStep($value)) {
                $fail('時刻は30分単位（:00 か :30）で入力してください。');
            }
        }];

        $v = $request->validate([
            'name' => 'required|string|max:255',
            'address' => 'nullable|string|max:255',
            'remarks' => 'nullable|string',
            'shop_group' => ['required', Rule::in(array_keys($this->groupOptions($request)))],
            'is_active' => 'boolean',
            'default_start_time' => array_merge($timeRule, ['required_with:default_end_time']),
            'default_end_time' => array_merge($timeRule, ['required_with:default_start_time']),
            'default_capacity' => 'required|integer|min:1|max:'.PhotoStudio::MAX_CAPACITY,
            'weekday_hours' => 'array|max:7',
            'weekday_hours.*.weekday' => 'required|integer|between:0,6|distinct',
            'weekday_hours.*.start_time' => array_merge(['required'], array_slice($timeRule, 1)),
            'weekday_hours.*.end_time' => array_merge(['required'], array_slice($timeRule, 1)),
        ], [], [
            'name' => 'スタジオ名',
            'shop_group' => '店舗グループ',
            'default_start_time' => '開始時刻',
            'default_end_time' => '終了時刻',
            'default_capacity' => '既定の定員',
            'weekday_hours.*.start_time' => '曜日の開始時刻',
            'weekday_hours.*.end_time' => '曜日の終了時刻',
        ]);

        $errors = [];
        if (! empty($v['default_start_time']) && ! empty($v['default_end_time'])
            && PhotoStudioHours::toMinutes($v['default_start_time']) >= PhotoStudioHours::toMinutes($v['default_end_time'])) {
            $errors['default_end_time'] = '終了時刻は開始時刻より後にしてください。';
        }
        foreach ($v['weekday_hours'] ?? [] as $i => $row) {
            if (PhotoStudioHours::toMinutes($row['start_time']) >= PhotoStudioHours::toMinutes($row['end_time'])) {
                $errors["weekday_hours.$i.end_time"] = '終了時刻は開始時刻より後にしてください。';
            }
        }
        if ($errors) {
            throw ValidationException::withMessages($errors);
        }

        return [
            'studio' => [
                'name' => $v['name'],
                'address' => $v['address'] ?? null,
                'remarks' => $v['remarks'] ?? null,
                'shop_group' => $v['shop_group'],
                'is_active' => (bool) ($v['is_active'] ?? true),
                'default_start_time' => $v['default_start_time'] ?? null,
                'default_end_time' => $v['default_end_time'] ?? null,
                'default_capacity' => (int) $v['default_capacity'],
            ],
            'weekday_hours' => $v['weekday_hours'] ?? [],
        ];
    }

    private function syncWeekdayHours(PhotoStudio $studio, array $rows): void
    {
        $studio->weekdayHours()->delete();
        foreach ($rows as $row) {
            $studio->weekdayHours()->create([
                'weekday' => (int) $row['weekday'],
                'start_time' => $row['start_time'],
                'end_time' => $row['end_time'],
            ]);
        }
    }

    /** 選べる店舗グループ（自分のグループだけ。システム管理者は全部） */
    private function groupOptions(Request $request): array
    {
        $groups = PhotoStudio::visibleGroupsFor($request->user());
        if ($groups === null) {
            return self::GROUP_LABELS;
        }

        return array_intersect_key(self::GROUP_LABELS, array_flip($groups));
    }

    private function present(PhotoStudio $s): array
    {
        return [
            'id' => $s->id,
            'name' => $s->name,
            'address' => $s->address,
            'remarks' => $s->remarks,
            'shop_group' => $s->shop_group,
            'is_active' => (bool) $s->is_active,
            'default_start_time' => PhotoStudioHours::hm($s->default_start_time),
            'default_end_time' => PhotoStudioHours::hm($s->default_end_time),
            'default_capacity' => (int) $s->default_capacity,
            'weekday_hours' => $s->weekdayHours->map(fn ($h) => [
                'weekday' => (int) $h->weekday,
                'start_time' => PhotoStudioHours::hm($h->start_time),
                'end_time' => PhotoStudioHours::hm($h->end_time),
            ])->values(),
            'summary' => $this->hours->summary($s),
            'has_hours' => $s->hasHours() || $s->weekdayHours->isNotEmpty(),
        ];
    }
}
