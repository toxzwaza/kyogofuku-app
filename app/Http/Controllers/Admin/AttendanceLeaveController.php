<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AttendanceLeave;
use App\Models\User;
use App\Services\AttendanceScopeService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AttendanceLeaveController extends Controller
{
    /**
     * 休暇（有給/特別休暇/欠勤）を登録・更新する。
     * 同一スタッフ・同一日付は1区分（updateOrCreate）。
     */
    public function store(Request $request)
    {
        $user = $request->user();
        if (!AttendanceScopeService::canAccessManagement($user)) {
            abort(403);
        }

        $validated = $request->validate([
            'user_id' => 'required|integer|exists:users,id',
            'date' => 'required|date',
            'leave_type' => ['required', Rule::in(AttendanceLeave::TYPES)],
            'note' => 'nullable|string|max:255',
        ]);

        // 店舗管理者は自店舗スタッフのみ操作可
        $this->authorizeTargetUser($user, (int) $validated['user_id']);

        AttendanceLeave::updateOrCreate(
            ['user_id' => $validated['user_id'], 'date' => $validated['date']],
            [
                'leave_type' => $validated['leave_type'],
                'note' => $validated['note'] ?? null,
                'created_by' => $user->id,
            ]
        );

        return redirect()->back()->with('success', '休暇を登録しました。');
    }

    /**
     * 休暇を削除する。
     */
    public function destroy(Request $request, AttendanceLeave $leave)
    {
        $user = $request->user();
        if (!AttendanceScopeService::canAccessManagement($user)) {
            abort(403);
        }
        $this->authorizeTargetUser($user, (int) $leave->user_id);

        $leave->delete();

        return redirect()->back()->with('success', '休暇を削除しました。');
    }

    /**
     * 勤怠管理者は全員、店舗管理者は自店舗スタッフのみ操作可。
     */
    private function authorizeTargetUser(User $actor, int $targetUserId): void
    {
        if ($actor->isAttendanceManager()) {
            return;
        }
        if ($actor->isShopManager()) {
            $shopIds = $actor->shops()->pluck('shops.id');
            $ok = User::where('id', $targetUserId)
                ->whereHas('shops', fn ($q) => $q->whereIn('shops.id', $shopIds))
                ->exists();
            if ($ok) {
                return;
            }
        }
        abort(403);
    }
}
