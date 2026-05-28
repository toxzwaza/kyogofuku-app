<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;

/**
 * 業務マニュアル（顧客・前撮り・イベント予約・周辺機能）の表示。
 * 勤怠マニュアル（AttendanceController@manual）と同じく Vue ページにレンダリングする。
 */
class AdminManualController extends Controller
{
    public function index(Request $request)
    {
        return Inertia::render('Admin/Manuals/Index', [
            'currentUser' => $this->currentUserPayload($request),
        ]);
    }

    public function customer(Request $request)
    {
        return Inertia::render('Admin/Manuals/Customer', [
            'currentUser' => $this->currentUserPayload($request),
        ]);
    }

    public function photoSlot(Request $request)
    {
        return Inertia::render('Admin/Manuals/PhotoSlot', [
            'currentUser' => $this->currentUserPayload($request),
        ]);
    }

    public function event(Request $request)
    {
        return Inertia::render('Admin/Manuals/Event', [
            'currentUser' => $this->currentUserPayload($request),
        ]);
    }

    public function peripheral(Request $request)
    {
        return Inertia::render('Admin/Manuals/Peripheral', [
            'currentUser' => $this->currentUserPayload($request),
        ]);
    }

    /**
     * 2026-05-29 版の簡易マニュアル。
     * 旧マニュアル（顧客・前撮り・イベント予約・周辺機能）は参考用に残し、
     * 新しく書き起こすマニュアルはこちらに集約する。
     */
    public function simple20260529(Request $request)
    {
        return Inertia::render('Admin/Manuals/SimpleManual20260529', [
            'currentUser' => $this->currentUserPayload($request),
        ]);
    }

    protected function currentUserPayload(Request $request): array
    {
        $user = $request->user();

        return [
            'id' => $user?->id,
            'name' => $user?->name,
        ];
    }
}
