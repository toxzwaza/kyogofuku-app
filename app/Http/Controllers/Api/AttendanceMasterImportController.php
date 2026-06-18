<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CompanyCalendarDay;
use App\Models\WorkAttribute;
use App\Models\WorkAttributePatternTime;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * 勤怠マスタ（勤務属性・会社カレンダー）取込API
 *
 * 用途：ローカルで作成したマスタを本番環境へ反映する。
 * 認証：X-Api-Key ヘッダー または ?token= で ATTENDANCE_MASTER_IMPORT_SECRET を送信。
 * 冪等：再実行しても二重登録されない。dry_run=true で書き込まず件数のみ確認できる。
 */
class AttendanceMasterImportController extends Controller
{
    /**
     * 現状のマスタを返す（read-only。事前差分確認用）
     * GET /api/attendance/master-import?token=...&month=YYYY-MM
     */
    public function current(Request $request): JsonResponse
    {
        if ($resp = $this->guard($request)) {
            return $resp;
        }

        $workAttributes = WorkAttribute::with('patternTimes')
            ->orderBy('sort_order')->orderBy('id')->get()
            ->map(fn (WorkAttribute $w) => [
                'id' => $w->id,
                'name' => $w->name,
                'sort_order' => $w->sort_order,
                'pattern_times' => $w->patternTimes
                    ->sortBy([['pattern', 'asc'], ['day_type', 'desc']])
                    ->map(fn (WorkAttributePatternTime $p) => [
                        'pattern' => $p->pattern,
                        'day_type' => $p->day_type,
                        'work_start_time' => substr((string) $p->work_start_time, 0, 5),
                        'work_end_time' => substr((string) $p->work_end_time, 0, 5),
                    ])->values(),
            ])->values();

        $calQuery = CompanyCalendarDay::query()->orderBy('calendar_date');
        if ($month = $request->query('month')) {
            $start = Carbon::parse($month.'-01')->startOfMonth();
            $calQuery->whereBetween('calendar_date', [$start->format('Y-m-d'), $start->copy()->endOfMonth()->format('Y-m-d')]);
        }
        $calendar = $calQuery->get()->map(fn (CompanyCalendarDay $r) => [
            'calendar_date' => $r->calendar_date instanceof Carbon ? $r->calendar_date->format('Y-m-d') : (string) $r->calendar_date,
            'pattern' => $r->pattern,
        ])->values();

        return response()->json([
            'success' => true,
            'work_attributes' => $workAttributes,
            'company_calendar' => $calendar,
        ], 200, [], JSON_UNESCAPED_UNICODE);
    }

    /**
     * 取込（upsert）。POST /api/attendance/master-import
     */
    public function import(Request $request): JsonResponse
    {
        if ($resp = $this->guard($request)) {
            return $resp;
        }

        $validated = $request->validate([
            'dry_run' => 'sometimes|boolean',
            'work_attributes' => 'sometimes|array',
            'work_attributes.*.name' => 'required|string|max:255',
            'work_attributes.*.sort_order' => 'nullable|integer|min:0|max:65535',
            'work_attributes.*.pattern_times' => 'sometimes|array',
            'work_attributes.*.pattern_times.*.pattern' => 'required|in:A,B,C',
            'work_attributes.*.pattern_times.*.day_type' => 'required|in:weekday,weekend',
            'work_attributes.*.pattern_times.*.work_start_time' => 'required|date_format:H:i',
            'work_attributes.*.pattern_times.*.work_end_time' => 'required|date_format:H:i',
            'company_calendar' => 'sometimes|array',
            'company_calendar.*.calendar_date' => 'required|date_format:Y-m-d',
            'company_calendar.*.pattern' => 'nullable|in:A,B,C',
        ]);

        $dryRun = (bool) ($validated['dry_run'] ?? false);
        $waInput = $validated['work_attributes'] ?? [];
        $calInput = $validated['company_calendar'] ?? [];

        $summary = [
            'work_attributes' => ['created' => 0, 'updated' => 0, 'pattern_time_rows' => 0],
            'company_calendar' => ['created' => 0, 'updated' => 0, 'deleted' => 0],
        ];

        DB::beginTransaction();
        try {
            foreach ($waInput as $wa) {
                $existing = WorkAttribute::where('name', $wa['name'])->first();
                if ($existing) {
                    $existing->update(['sort_order' => $wa['sort_order'] ?? $existing->sort_order]);
                    $model = $existing;
                    $summary['work_attributes']['updated']++;
                } else {
                    $model = WorkAttribute::create([
                        'name' => $wa['name'],
                        'sort_order' => $wa['sort_order'] ?? 0,
                    ]);
                    $summary['work_attributes']['created']++;
                }

                // パターン別時間はペイロードを正本として全置換
                $model->patternTimes()->delete();
                foreach (($wa['pattern_times'] ?? []) as $pt) {
                    WorkAttributePatternTime::create([
                        'work_attribute_id' => $model->id,
                        'pattern' => $pt['pattern'],
                        'day_type' => $pt['day_type'],
                        'work_start_time' => $pt['work_start_time'],
                        'work_end_time' => $pt['work_end_time'],
                    ]);
                    $summary['work_attributes']['pattern_time_rows']++;
                }
            }

            foreach ($calInput as $c) {
                $date = $c['calendar_date'];
                $pattern = $c['pattern'] ?? null;
                $row = CompanyCalendarDay::where('calendar_date', $date)->first();

                if ($pattern === null) {
                    if ($row) {
                        $row->delete();
                        $summary['company_calendar']['deleted']++;
                    }
                    continue;
                }

                if ($row) {
                    $row->update(['pattern' => $pattern]);
                    $summary['company_calendar']['updated']++;
                } else {
                    CompanyCalendarDay::create(['calendar_date' => $date, 'pattern' => $pattern]);
                    $summary['company_calendar']['created']++;
                }
            }

            if ($dryRun) {
                DB::rollBack();
            } else {
                DB::commit();
            }
        } catch (\Throwable $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => '取込に失敗しました: '.$e->getMessage(),
            ], 500, [], JSON_UNESCAPED_UNICODE);
        }

        return response()->json([
            'success' => true,
            'dry_run' => $dryRun,
            'work_attributes' => $summary['work_attributes'],
            'company_calendar' => $summary['company_calendar'],
        ], 200, [], JSON_UNESCAPED_UNICODE);
    }

    /**
     * X-Api-Key / token 認証。OKなら null、NGならレスポンスを返す。
     */
    private function guard(Request $request): ?JsonResponse
    {
        $secret = config('services.attendance_master_import_secret');
        if (empty($secret)) {
            return response()->json([
                'success' => false,
                'message' => 'エンドポイントが未設定です（ATTENDANCE_MASTER_IMPORT_SECRET 未設定）',
            ], 503, [], JSON_UNESCAPED_UNICODE);
        }

        $token = $request->header('X-Api-Key') ?? $request->query('token');
        if (!is_string($token) || !hash_equals((string) $secret, $token)) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
            ], 401, [], JSON_UNESCAPED_UNICODE);
        }

        return null;
    }
}
