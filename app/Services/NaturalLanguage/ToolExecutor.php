<?php

namespace App\Services\NaturalLanguage;

use App\Models\Event;
use App\Models\EventReservation;
use App\Models\EventTimeslot;
use App\Models\Shop;
use App\Models\Venue;
use Carbon\Carbon;
use Illuminate\Support\Str;

class ToolExecutor
{
    /** 書き込み操作の tool 名一覧 */
    private const WRITE_TOOLS = [
        'create_event',
        'update_event',
        'create_timeslots',
        'update_timeslot',
        'adjust_capacity',
        'delete_timeslot',
        'update_reservation_status',
    ];

    /**
     * 指定の tool を実行して結果を返す
     *
     * @return array{success: bool, data?: mixed, error?: string, is_write: bool, needs_confirm?: bool}
     */
    public function execute(string $toolName, array $input, bool $confirmed = false): array
    {
        $isWrite = in_array($toolName, self::WRITE_TOOLS, true);

        // 書き込み操作かつ未確認 → 確認を返す
        if ($isWrite && ! $confirmed) {
            return [
                'success' => false,
                'is_write' => true,
                'needs_confirm' => true,
                'tool' => $toolName,
                'input' => $input,
                'message' => 'この操作はデータを変更します。実行するには confirm=true を付けて再送信してください。',
            ];
        }

        try {
            $result = match ($toolName) {
                'list_events' => $this->listEvents($input),
                'get_event' => $this->getEvent($input),
                'create_event' => $this->createEvent($input),
                'update_event' => $this->updateEvent($input),
                'list_timeslots' => $this->listTimeslots($input),
                'create_timeslots' => $this->createTimeslots($input),
                'update_timeslot' => $this->updateTimeslot($input),
                'adjust_capacity' => $this->adjustCapacity($input),
                'delete_timeslot' => $this->deleteTimeslot($input),
                'list_reservations' => $this->listReservations($input),
                'get_reservation' => $this->getReservation($input),
                'update_reservation_status' => $this->updateReservationStatus($input),
                default => throw new \InvalidArgumentException("Unknown tool: {$toolName}"),
            };

            return ['success' => true, 'is_write' => $isWrite, 'data' => $result];
        } catch (\Throwable $e) {
            return ['success' => false, 'is_write' => $isWrite, 'error' => $e->getMessage()];
        }
    }

    // ── 読み取り操作 ──

    private function listEvents(array $input): array
    {
        $query = Event::with('shops');
        $today = Carbon::today();

        $status = $input['status'] ?? 'すべて';
        if ($status === '公開中') {
            $query->where('is_public', true)
                ->where(fn ($q) => $q->whereNull('end_at')->orWhere('end_at', '>=', $today));
        } elseif ($status === '受付終了') {
            $query->where('is_public', true)->where('end_at', '<', $today);
        } elseif ($status === '非公開') {
            $query->where('is_public', false);
        }

        if (! empty($input['shop_name'])) {
            $query->whereHas('shops', fn ($q) => $q->where('name', 'like', '%' . $input['shop_name'] . '%'));
        }

        return $query->orderByDesc('id')->limit(30)->get()->map(fn (Event $e) => [
            'id' => $e->id,
            'title' => $e->title,
            'form_type' => $e->form_type,
            'is_public' => $e->is_public,
            'start_at' => $e->start_at?->format('Y-m-d'),
            'end_at' => $e->end_at?->format('Y-m-d'),
            'shops' => $e->shops->pluck('name')->toArray(),
        ])->toArray();
    }

    private function getEvent(array $input): array
    {
        $event = null;

        if (! empty($input['event_id'])) {
            $event = Event::find($input['event_id']);
        } elseif (! empty($input['title_keyword'])) {
            $event = Event::where('title', 'like', '%' . $input['title_keyword'] . '%')
                ->orderByDesc('id')
                ->first();
        }

        if (! $event) {
            throw new \RuntimeException('イベントが見つかりませんでした。');
        }

        $event->load(['shops', 'venues', 'timeslots']);
        $reservationCount = $event->reservations()->where('cancel_flg', false)->count();

        return [
            'id' => $event->id,
            'title' => $event->title,
            'form_type' => $event->form_type,
            'is_public' => $event->is_public,
            'start_at' => $event->start_at?->format('Y-m-d'),
            'end_at' => $event->end_at?->format('Y-m-d'),
            'description' => $event->description,
            'shops' => $event->shops->pluck('name')->toArray(),
            'venues' => $event->venues->map(fn ($v) => ['id' => $v->id, 'name' => $v->name])->toArray(),
            'timeslot_count' => $event->timeslots->count(),
            'reservation_count' => $reservationCount,
        ];
    }

    private function listTimeslots(array $input): array
    {
        $event = Event::findOrFail($input['event_id']);
        $query = $event->timeslots()->with('venue')->orderBy('start_at');

        if (! empty($input['date'])) {
            $query->whereDate('start_at', $input['date']);
        }

        if (! empty($input['venue_name'])) {
            $query->whereHas('venue', fn ($q) => $q->where('name', 'like', '%' . $input['venue_name'] . '%'));
        }

        return $query->get()->map(function (EventTimeslot $ts) use ($event) {
            $reserved = $event->reservations()
                ->where('cancel_flg', false)
                ->where('reservation_datetime', $ts->start_at->format('Y-m-d H:i:s'))
                ->when($ts->venue_id, fn ($q) => $q->where('venue_id', $ts->venue_id))
                ->count();

            return [
                'id' => $ts->id,
                'date' => $ts->start_at->format('Y-m-d'),
                'time' => $ts->start_at->format('H:i'),
                'capacity' => $ts->capacity,
                'reserved' => $reserved,
                'remaining' => $ts->capacity - $reserved,
                'is_active' => $ts->is_active,
                'venue' => $ts->venue?->name,
            ];
        })->toArray();
    }

    private function listReservations(array $input): array
    {
        $query = EventReservation::with(['event'])->where('cancel_flg', false);

        if (! empty($input['event_id'])) {
            $query->where('event_id', $input['event_id']);
        }
        if (! empty($input['status'])) {
            $query->where('status', $input['status']);
        }
        if (! empty($input['date'])) {
            $query->whereDate('reservation_datetime', $input['date']);
        }

        $limit = min($input['limit'] ?? 20, 50);

        return $query->orderByDesc('created_at')->limit($limit)->get()->map(fn ($r) => [
            'id' => $r->id,
            'name' => $r->name,
            'email' => $r->email,
            'phone' => $r->phone,
            'status' => $r->status,
            'event_name' => $r->event?->title,
            'reservation_datetime' => $r->reservation_datetime,
            'admin_assignee' => $r->admin_assignee,
            'created_at' => $r->created_at->format('Y-m-d H:i'),
        ])->toArray();
    }

    private function getReservation(array $input): array
    {
        $r = EventReservation::with(['event', 'venue', 'customer'])->findOrFail($input['reservation_id']);

        return [
            'id' => $r->id,
            'name' => $r->name,
            'email' => $r->email,
            'phone' => $r->phone,
            'status' => $r->status,
            'cancel_flg' => $r->cancel_flg,
            'event_name' => $r->event?->title,
            'venue_name' => $r->venue?->name,
            'reservation_datetime' => $r->reservation_datetime,
            'admin_assignee' => $r->admin_assignee,
            'considering_plans' => $r->considering_plans,
            'customer_name' => $r->customer?->name,
            'created_at' => $r->created_at->format('Y-m-d H:i'),
        ];
    }

    // ── 書き込み操作 ──

    private function createEvent(array $input): array
    {
        $slug = 'event_' . Str::random(8);

        $event = Event::create([
            'title' => $input['title'],
            'form_type' => $input['form_type'],
            'slug' => $slug,
            'start_at' => ! empty($input['start_at']) ? Carbon::parse($input['start_at']) : null,
            'end_at' => ! empty($input['end_at']) ? Carbon::parse($input['end_at']) : null,
            'is_public' => $input['is_public'] ?? true,
            'description' => $input['description'] ?? null,
        ]);

        if (! empty($input['shop_names'])) {
            $shopIds = Shop::whereIn('name', $input['shop_names'])->pluck('id')->toArray();
            $event->shops()->sync($shopIds);
        }

        return ['id' => $event->id, 'title' => $event->title, 'slug' => $event->slug];
    }

    private function updateEvent(array $input): array
    {
        $event = Event::findOrFail($input['event_id']);
        $updates = [];

        if (array_key_exists('title', $input)) {
            $updates['title'] = $input['title'];
        }
        if (array_key_exists('end_at', $input)) {
            $updates['end_at'] = $input['end_at'] !== null ? Carbon::parse($input['end_at']) : null;
        }
        if (array_key_exists('is_public', $input)) {
            $updates['is_public'] = $input['is_public'];
        }
        if (array_key_exists('description', $input)) {
            $updates['description'] = $input['description'];
        }

        if (! empty($updates)) {
            $event->update($updates);
        }

        return ['id' => $event->id, 'title' => $event->title, 'updated_fields' => array_keys($updates)];
    }

    private function createTimeslots(array $input): array
    {
        $event = Event::findOrFail($input['event_id']);
        $date = $input['date'];
        $times = $input['times'];
        $capacity = $input['capacity'];

        $venueId = null;
        if (! empty($input['venue_name'])) {
            $venue = Venue::where('name', 'like', '%' . $input['venue_name'] . '%')->first();
            $venueId = $venue?->id;
        }

        $created = [];
        foreach ($times as $time) {
            $startAt = Carbon::parse("{$date} {$time}");

            // 重複チェック
            $exists = $event->timeslots()
                ->where('start_at', $startAt)
                ->where('venue_id', $venueId)
                ->exists();

            if ($exists) {
                $created[] = ['time' => $time, 'status' => 'skipped (duplicate)'];
                continue;
            }

            $ts = EventTimeslot::create([
                'event_id' => $event->id,
                'venue_id' => $venueId,
                'start_at' => $startAt,
                'capacity' => $capacity,
                'is_active' => true,
            ]);
            $created[] = ['id' => $ts->id, 'time' => $time, 'status' => 'created'];
        }

        return ['event_id' => $event->id, 'date' => $date, 'slots' => $created];
    }

    private function updateTimeslot(array $input): array
    {
        $ts = EventTimeslot::findOrFail($input['timeslot_id']);
        $updates = [];

        if (array_key_exists('capacity', $input)) {
            $updates['capacity'] = $input['capacity'];
        }
        if (array_key_exists('is_active', $input)) {
            $updates['is_active'] = $input['is_active'];
        }

        $ts->update($updates);

        return ['id' => $ts->id, 'updated_fields' => array_keys($updates), 'capacity' => $ts->capacity];
    }

    private function adjustCapacity(array $input): array
    {
        $ts = EventTimeslot::findOrFail($input['timeslot_id']);
        $adjustment = $input['adjustment'];
        $oldCapacity = $ts->capacity;
        $newCapacity = max(0, $oldCapacity + $adjustment);
        $ts->update(['capacity' => $newCapacity]);

        return [
            'id' => $ts->id,
            'old_capacity' => $oldCapacity,
            'new_capacity' => $newCapacity,
            'adjustment' => $adjustment,
        ];
    }

    private function deleteTimeslot(array $input): array
    {
        $ts = EventTimeslot::findOrFail($input['timeslot_id']);
        $event = Event::find($ts->event_id);

        $reservedCount = $event
            ? $event->reservations()
                ->where('cancel_flg', false)
                ->where('reservation_datetime', $ts->start_at->format('Y-m-d H:i:s'))
                ->count()
            : 0;

        if ($reservedCount > 0) {
            throw new \RuntimeException("この枠には{$reservedCount}件の予約があるため削除できません。");
        }

        $ts->delete();

        return ['id' => $input['timeslot_id'], 'deleted' => true];
    }

    private function updateReservationStatus(array $input): array
    {
        $r = EventReservation::findOrFail($input['reservation_id']);
        $oldStatus = $r->status;
        $newStatus = $input['status'];

        if ($newStatus === 'キャンセル済み') {
            $r->load('schedule');
            if ($r->schedule) {
                $r->schedule->delete();
            }
            $r->update([
                'status' => $newStatus,
                'cancel_flg' => true,
            ]);
        } else {
            $r->update(['status' => $newStatus]);
        }

        return [
            'id' => $r->id,
            'name' => $r->name,
            'old_status' => $oldStatus,
            'new_status' => $newStatus,
        ];
    }
}
