<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\EventReservation;
use App\Services\QuestionnaireService;
use App\Support\PhotoOwner;
use Illuminate\Http\Request;

/**
 * 予約詳細「写真・アンケート」タブの振袖アンケート操作。
 *
 * 予約が顧客に紐付いている場合は顧客のアンケートを直接操作し、
 * 未紐付けなら予約自身に紐づけて保存する（PhotoOwner::forReservation が解決）。
 */
class ReservationQuestionnaireController extends Controller
{
    public function __construct(
        private QuestionnaireService $questionnaires,
    ) {
    }

    public function print(Request $request, EventReservation $reservation)
    {
        return view('admin.questionnaire.print', $this->questionnaires->printData(
            PhotoOwner::forReservation($reservation),
            (string) $request->query('mode'),
            $request->boolean('blank'),
        ));
    }

    public function storeScan(Request $request, EventReservation $reservation)
    {
        $validated = $request->validate([
            'page' => 'required|integer|in:1,2',
            'photo' => 'required|file|mimes:jpeg,png,jpg|max:10240',
        ]);

        $page = (int) $validated['page'];

        try {
            $this->questionnaires->storeScan(PhotoOwner::forReservation($reservation), $request->file('photo'), $page);
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('admin.reservations.show', $reservation)
            ->with('success', "アンケート{$page}ページ目を取り込みました。");
    }

    public function updatePlacements(Request $request, EventReservation $reservation)
    {
        $validated = $request->validate([
            'page' => 'nullable|integer|in:1,2',
            'placements' => 'nullable|array',
            'placements.*.type' => 'nullable|in:photo,text',
            'placements.*.customer_photo_id' => 'nullable|integer',
            'placements.*.text' => 'nullable|string|max:500',
            'placements.*.left' => 'required|numeric',
            'placements.*.top' => 'required|numeric',
            'placements.*.angle' => 'required|numeric',
            'placements.*.scale' => 'nullable|numeric',
            'placements.*.scale_x' => 'nullable|numeric',
            'placements.*.scale_y' => 'nullable|numeric',
            'placements.*.font_size' => 'nullable|numeric',
            'placements.*.width' => 'nullable|numeric',
            'composed_image' => 'required|file|mimes:jpeg,png,jpg,webp|max:20480',
        ]);

        $page = (int) ($validated['page'] ?? 2);

        try {
            $composedUrl = $this->questionnaires->updatePlacements(
                PhotoOwner::forReservation($reservation),
                $validated['placements'] ?? [],
                $request->file('composed_image'),
                $page,
            );
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], $e->getCode() ?: 500);
        }

        return response()->json([
            'message' => '写真添付欄を保存しました。',
            "composed_page{$page}_url" => $composedUrl,
        ]);
    }

    public function destroyScan(EventReservation $reservation, int $page)
    {
        abort_unless(in_array($page, [1, 2], true), 404);

        try {
            $this->questionnaires->destroyScan(PhotoOwner::forReservation($reservation), $page);
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('admin.reservations.show', $reservation)
            ->with('success', "アンケート{$page}ページ目のスキャンを削除しました。");
    }
}
