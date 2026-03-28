<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CompanyCalendarDay;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class CompanyCalendarController extends Controller
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

        $yearMonth = $request->input('year_month', Carbon::now()->format('Y-m'));
        try {
            $start = Carbon::createFromFormat('Y-m-d', $yearMonth.'-01')->startOfMonth();
        } catch (\Throwable) {
            $start = Carbon::now()->startOfMonth();
            $yearMonth = $start->format('Y-m');
        }
        $end = $start->copy()->endOfMonth();

        $existing = CompanyCalendarDay::query()
            ->whereBetween('calendar_date', [$start->format('Y-m-d'), $end->format('Y-m-d')])
            ->get()
            ->keyBy(fn ($row) => Carbon::parse($row->calendar_date)->format('Y-m-d'));

        $days = [];
        for ($d = $start->copy(); $d->lte($end); $d->addDay()) {
            $key = $d->format('Y-m-d');
            $days[] = [
                'calendar_date' => $key,
                'weekday_label' => $d->isoFormat('ddd'),
                'pattern' => $existing[$key]->pattern ?? null,
            ];
        }

        return Inertia::render('Admin/Attendance/CompanyCalendar', [
            'yearMonth' => $yearMonth,
            'days' => $days,
        ]);
    }

    public function update(Request $request)
    {
        $this->ensureAttendanceManager($request);

        $days = $request->input('days', []);
        $normalized = [];
        foreach ($days as $row) {
            $p = $row['pattern'] ?? null;
            if ($p === '' || $p === 'null') {
                $p = null;
            }
            $normalized[] = [
                'calendar_date' => $row['calendar_date'] ?? null,
                'pattern' => $p,
            ];
        }
        $request->merge(['days' => $normalized]);

        $validated = $request->validate([
            'year_month' => 'nullable|date_format:Y-m',
            'days' => 'required|array',
            'days.*.calendar_date' => 'required|date_format:Y-m-d',
            'days.*.pattern' => 'nullable|in:A,B,C',
        ]);

        DB::transaction(function () use ($validated) {
            foreach ($validated['days'] as $row) {
                $date = $row['calendar_date'];
                $pattern = $row['pattern'] ?? null;
                if ($pattern === null || $pattern === '') {
                    CompanyCalendarDay::query()->where('calendar_date', $date)->delete();
                } else {
                    CompanyCalendarDay::query()->updateOrCreate(
                        ['calendar_date' => $date],
                        ['pattern' => $pattern]
                    );
                }
            }
        });

        $ym = $validated['year_month']
            ?? (!empty($validated['days'][0]['calendar_date'])
                ? Carbon::parse($validated['days'][0]['calendar_date'])->format('Y-m')
                : Carbon::now()->format('Y-m'));

        return redirect()->route('admin.attendance.company-calendar.index', [
            'year_month' => $ym,
        ])->with('success', '会社カレンダーを保存しました。');
    }
}
