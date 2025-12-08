<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\EventReservation;
use App\Models\ReservationNote;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ReservationController extends Controller
{
    /**
     * イベント別予約一覧を表示
     */
    public function index(Event $event)
    {
        $reservations = EventReservation::with('venue')
            ->where('event_id', $event->id)
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        // 予約枠の統計情報と日付別グループ化（予約フォームの場合のみ）
        $timeslotStats = null;
        $timeslotsWithReservations = null;
        
        if ($event->form_type === 'reservation') {
            $timeslots = $event->timeslots()->where('is_active', true)->orderBy('start_at', 'asc')->get();
            $totalCapacity = $timeslots->sum('capacity');
            $totalReserved = 0;
            
            // 各予約枠に予約情報を紐付け
            $timeslotsWithReservations = $timeslots->map(function ($timeslot) use ($event, &$totalReserved) {
                $timeslotReservations = $event->reservations()
                    ->with('venue')
                    ->where('reservation_datetime', $timeslot->start_at->format('Y-m-d H:i:s'))
                    ->orderBy('created_at', 'asc')
                    ->get();
                
                $reservedCount = $timeslotReservations->count();
                $totalReserved += $reservedCount;
                
                return [
                    'id' => $timeslot->id,
                    'start_at' => $timeslot->start_at->format('Y-m-d H:i:s'),
                    'capacity' => $timeslot->capacity,
                    'remaining_capacity' => max(0, $timeslot->capacity - $reservedCount),
                    'reservations' => $timeslotReservations->map(function ($reservation) {
                        return [
                            'id' => $reservation->id,
                            'name' => $reservation->name,
                            'email' => $reservation->email,
                            'phone' => $reservation->phone,
                            'venue' => $reservation->venue ? [
                                'id' => $reservation->venue->id,
                                'name' => $reservation->venue->name,
                            ] : null,
                            'furigana' => $reservation->furigana,
                            'has_visited_before' => $reservation->has_visited_before,
                            'address' => $reservation->address,
                            'birth_date' => $reservation->birth_date,
                            'seijin_year' => $reservation->seijin_year,
                            'school_name' => $reservation->school_name,
                            'parking_usage' => $reservation->parking_usage,
                            'parking_car_count' => $reservation->parking_car_count,
                            'considering_plans' => $reservation->considering_plans,
                            'referred_by_name' => $reservation->referred_by_name,
                            'inquiry_message' => $reservation->inquiry_message,
                            'created_at' => $reservation->created_at->format('Y-m-d H:i:s'),
                        ];
                    })->values(),
                ];
            })->values();
            
            $timeslotStats = [
                'total_capacity' => $totalCapacity,
                'total_reserved' => $totalReserved,
                'remaining' => max(0, $totalCapacity - $totalReserved),
                'occupancy_rate' => $totalCapacity > 0 ? round(($totalReserved / $totalCapacity) * 100, 1) : 0,
            ];
        }

        return Inertia::render('Admin/Reservation/Index', [
            'event' => $event,
            'reservations' => $reservations,
            'timeslotStats' => $timeslotStats,
            'timeslotsWithReservations' => $timeslotsWithReservations,
        ]);
    }

    /**
     * 予約詳細を表示
     */
    public function show(EventReservation $reservation)
    {
        $reservation->load(['event', 'venue', 'notes.user']);

        return Inertia::render('Admin/Reservation/Show', [
            'reservation' => $reservation,
            'event' => $reservation->event,
            'venues' => $reservation->event->venues()->where('is_active', true)->get(),
            'notes' => $reservation->notes()->with('user')->orderBy('created_at', 'desc')->get(),
        ]);
    }

    /**
     * 予約編集フォームを表示
     */
    public function edit(EventReservation $reservation)
    {
        $reservation->load(['event', 'venue']);
        $event = $reservation->event;

        // 予約フォームの場合、利用可能な予約枠を取得
        $timeslots = [];
        if ($event->form_type === 'reservation') {
            $timeslots = $event->timeslots()
                ->where('is_active', true)
                ->orderBy('start_at', 'asc')
                ->get()
                ->map(function ($timeslot) use ($event, $reservation) {
                    $reservationCount = $event->reservations()
                        ->where('reservation_datetime', $timeslot->start_at->format('Y-m-d H:i:s'))
                        ->where('id', '!=', $reservation->id) // 現在編集中の予約を除外
                        ->count();
                    $timeslot->remaining_capacity = max(0, $timeslot->capacity - $reservationCount);
                    return $timeslot;
                });
        }

        return Inertia::render('Admin/Reservation/Edit', [
            'reservation' => $reservation,
            'event' => $event,
            'venues' => $event->venues()->where('is_active', true)->get(),
            'timeslots' => $timeslots,
        ]);
    }

    /**
     * 予約を更新
     */
    public function update(Request $request, EventReservation $reservation)
    {
        $reservation->load('event');
        $event = $reservation->event;
        
        // フォーム種別に応じたバリデーション
        $rules = [
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'phone' => 'required|string|max:255',
        ];
        
        // 資料請求フォームの場合
        if ($event->form_type === 'document') {
            $rules['request_method'] = 'required|in:郵送,デジタルカタログ';
            $rules['postal_code'] = 'nullable|string|max:10';
            $rules['privacy_agreed'] = 'nullable|boolean';
        }
        
        // 予約フォームの場合
        if ($event->form_type === 'reservation') {
            $rules['reservation_datetime'] = 'nullable|string';
            $rules['venue_id'] = 'nullable|exists:venues,id';
            $rules['has_visited_before'] = 'boolean';
            $rules['seijin_year'] = 'nullable|integer|min:2000|max:2100';
            $rules['referred_by_name'] = 'nullable|string|max:255';
            $rules['school_name'] = 'nullable|string|max:255';
            $rules['parking_usage'] = 'nullable|string|max:255';
            $rules['parking_car_count'] = 'nullable|integer';
            $rules['considering_plans'] = 'nullable|array';
            $rules['considering_plans.*'] = 'in:振袖レンタルプラン,振袖購入プラン,ママ振りフォトプラン,フォトレンタルプラン';
        }
        
        // 共通項目
        $rules['furigana'] = 'nullable|string|max:255';
        $rules['birth_date'] = 'nullable|date';
        $rules['address'] = 'nullable|string|max:255';
        $rules['inquiry_message'] = 'nullable|string';
        
        // heard_fromのバリデーション（フォーム種別によって異なる）
        if ($event->form_type === 'contact') {
            // お問い合わせフォームの場合、「メール」「電話」のみ許可
            $rules['heard_from'] = 'nullable|in:メール,電話';
        } else {
            // その他のフォームの場合
            $rules['heard_from'] = 'nullable|string|max:255';
        }
        
        $validated = $request->validate($rules);

        $reservation->update($validated);

        return redirect()->route('admin.reservations.show', $reservation->id)
            ->with('success', '予約を更新しました。');
    }

    /**
     * 予約を削除
     */
    public function destroy(EventReservation $reservation)
    {
        $eventId = $reservation->event_id;
        $reservation->delete();

        return redirect()->route('admin.events.reservations.index', $eventId)
            ->with('success', '予約を削除しました。');
    }

    /**
     * メモを追加
     */
    public function storeNote(Request $request, EventReservation $reservation)
    {
        $validated = $request->validate([
            'content' => 'required|string|max:10000',
        ]);

        $note = ReservationNote::create([
            'user_id' => auth()->id(),
            'event_reservation_id' => $reservation->id,
            'content' => $validated['content'],
        ]);

        $note->load('user');

        return redirect()->back()->with('success', 'メモを追加しました。');
    }

    /**
     * メモを削除
     */
    public function destroyNote(ReservationNote $note)
    {
        $note->delete();

        return redirect()->back()->with('success', 'メモを削除しました。');
    }
}

