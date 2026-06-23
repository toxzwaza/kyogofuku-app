<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AttendancePayrollSetting;
use Illuminate\Http\Request;
use App\Http\Controllers\Concerns\ResolvesUiView;
use Inertia\Inertia;

class AttendancePayrollSettingController extends Controller
{
    use ResolvesUiView;

    private function ensureAttendanceManager(Request $request): void
    {
        if (!$request->user()->isAttendanceManager()) {
            abort(403);
        }
    }

    public function edit(Request $request)
    {
        $this->ensureAttendanceManager($request);

        $setting = AttendancePayrollSetting::query()->orderBy('id')->firstOrFail();

        return Inertia::render($this->viewFor('Admin/Attendance/PayrollSettings'), [
            'setting' => $setting,
        ]);
    }

    public function update(Request $request)
    {
        $this->ensureAttendanceManager($request);

        $validated = $request->validate([
            'start_early_threshold_minutes' => 'required|integer|min:0|max:720',
            'start_rounding_unit_minutes' => 'required|integer|min:1|max:480',
            'overtime_rounding_unit_minutes' => 'required|integer|min:1|max:480',
        ]);

        $setting = AttendancePayrollSetting::query()->orderBy('id')->firstOrFail();
        $setting->update([
            'start_early_threshold_minutes' => $validated['start_early_threshold_minutes'],
            'start_rounding_unit_minutes' => $validated['start_rounding_unit_minutes'],
            'overtime_rounding_unit_minutes' => $validated['overtime_rounding_unit_minutes'],
        ]);

        return redirect()->route('admin.attendance.payroll-settings.edit')
            ->with('success', '給与計算閾値を保存しました。');
    }
}
