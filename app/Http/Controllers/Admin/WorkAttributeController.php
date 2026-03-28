<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\WorkAttribute;
use App\Models\WorkAttributePatternTime;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class WorkAttributeController extends Controller
{
    private function ensureAttendanceManager(Request $request): void
    {
        if (!$request->user()->isAttendanceManager()) {
            abort(403);
        }
    }

    public function index(Request $request)
    {
        $this->ensureAttendanceManager($request);

        $attributes = WorkAttribute::query()
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        return Inertia::render('Admin/WorkAttribute/Index', [
            'workAttributes' => $attributes,
        ]);
    }

    public function create(Request $request)
    {
        $this->ensureAttendanceManager($request);

        return Inertia::render('Admin/WorkAttribute/Create');
    }

    public function store(Request $request)
    {
        $this->ensureAttendanceManager($request);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'sort_order' => 'nullable|integer|min:0|max:65535',
        ]);

        $wa = WorkAttribute::query()->create([
            'name' => $validated['name'],
            'sort_order' => $validated['sort_order'] ?? 0,
        ]);

        return redirect()->route('admin.work-attributes.edit', $wa)
            ->with('success', '勤務属性を登録しました。パターン別の業務時間を設定してください。');
    }

    public function edit(Request $request, WorkAttribute $workAttribute)
    {
        $this->ensureAttendanceManager($request);

        $workAttribute->load('patternTimes');
        $matrix = $this->buildPatternMatrix($workAttribute);

        return Inertia::render('Admin/WorkAttribute/Edit', [
            'workAttribute' => $workAttribute,
            'patternMatrix' => $matrix,
        ]);
    }

    public function update(Request $request, WorkAttribute $workAttribute)
    {
        $this->ensureAttendanceManager($request);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'sort_order' => 'nullable|integer|min:0|max:65535',
            'pattern_times' => 'required|array',
            'pattern_times.*.pattern' => 'required|in:A,B,C',
            'pattern_times.*.day_type' => 'required|in:weekday,weekend',
            'pattern_times.*.work_start_time' => 'nullable|string|max:8',
            'pattern_times.*.work_end_time' => 'nullable|string|max:8',
        ]);

        DB::transaction(function () use ($workAttribute, $validated) {
            $workAttribute->update([
                'name' => $validated['name'],
                'sort_order' => $validated['sort_order'] ?? 0,
            ]);

            foreach ($validated['pattern_times'] as $row) {
                $start = $this->normalizeTimeInput($row['work_start_time'] ?? null);
                $end = $this->normalizeTimeInput($row['work_end_time'] ?? null);

                WorkAttributePatternTime::query()->updateOrCreate(
                    [
                        'work_attribute_id' => $workAttribute->id,
                        'pattern' => $row['pattern'],
                        'day_type' => $row['day_type'],
                    ],
                    [
                        'work_start_time' => $start,
                        'work_end_time' => $end,
                    ]
                );
            }
        });

        return redirect()->route('admin.work-attributes.edit', $workAttribute)
            ->with('success', '勤務属性を更新しました。');
    }

    public function destroy(Request $request, WorkAttribute $workAttribute)
    {
        $this->ensureAttendanceManager($request);

        if ($workAttribute->users()->exists()) {
            return redirect()->route('admin.work-attributes.index')
                ->with('error', 'この勤務属性が設定されているスタッフがいるため削除できません。');
        }

        DB::transaction(function () use ($workAttribute) {
            $workAttribute->patternTimes()->delete();
            $workAttribute->delete();
        });

        return redirect()->route('admin.work-attributes.index')
            ->with('success', '勤務属性を削除しました。');
    }

    /**
     * @return list<array{pattern: string, day_type: string, work_start_time: string|null, work_end_time: string|null}>
     */
    private function buildPatternMatrix(WorkAttribute $workAttribute): array
    {
        $map = [];
        foreach ($workAttribute->patternTimes as $pt) {
            $key = $pt->pattern.'|'.$pt->day_type;
            $map[$key] = $pt;
        }

        $matrix = [];
        foreach (WorkAttribute::PATTERNS as $p) {
            foreach ([WorkAttribute::DAY_TYPE_WEEKDAY, WorkAttribute::DAY_TYPE_WEEKEND] as $dt) {
                $key = $p.'|'.$dt;
                $row = $map[$key] ?? null;
                $matrix[] = [
                    'pattern' => $p,
                    'day_type' => $dt,
                    'work_start_time' => $row ? $this->formatTimeForInput($row->work_start_time) : null,
                    'work_end_time' => $row ? $this->formatTimeForInput($row->work_end_time) : null,
                ];
            }
        }

        return $matrix;
    }

    private function normalizeTimeInput(?string $value): ?string
    {
        if ($value === null || trim($value) === '') {
            return null;
        }
        $value = trim($value);
        // 「9:00」「09:00」「09:00:30」などテキスト入力に対応
        if (preg_match('/^(\d{1,2}):(\d{2})(?::(\d{2}))?$/', $value, $m)) {
            $h = (int) $m[1];
            $min = (int) $m[2];
            $s = isset($m[3]) ? (int) $m[3] : 0;
            if ($h < 0 || $h > 23 || $min < 0 || $min > 59 || $s < 0 || $s > 59) {
                return null;
            }

            return sprintf('%02d:%02d:%02d', $h, $min, $s);
        }

        return null;
    }

    private function formatTimeForInput(mixed $time): ?string
    {
        if ($time === null || $time === '') {
            return null;
        }
        if ($time instanceof \DateTimeInterface) {
            return $time->format('H:i');
        }

        return substr((string) $time, 0, 5);
    }
}
