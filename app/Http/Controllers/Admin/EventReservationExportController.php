<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CsvExportLog;
use App\Models\Event;
use App\Models\EventReservation;
use App\Models\Shop;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class EventReservationExportController extends Controller
{
    private const STATUSES = ['未対応', '確認中', '返信待ち', '対応完了済み', 'キャンセル済み'];

    private const COLUMN_LABELS = [
        'id' => '予約ID',
        'status' => 'ステータス',
        'name' => '氏名',
        'furigana' => 'フリガナ',
        'email' => 'メール',
        'phone' => '電話番号',
        'reservation_datetime' => '予約日時',
        'event_title' => 'イベント名',
        'venue_name' => '会場',
        'birth_date' => '生年月日',
        'seijin_year' => '成人年',
        'postal_code' => '郵便番号',
        'address' => '住所',
        'has_visited_before' => '来店経験有無',
        'referred_by_name' => '紹介者名',
        'school_name' => '学校名',
        'koichi_furisode_used' => '古石振袖使用',
        'graduation_ceremony_year' => '卒業式年',
        'graduation_ceremony_month' => '卒業式月',
        'graduation_ceremony_date' => '卒業式日',
        'visitor_count' => '来店人数',
        'companion_types' => 'お連れ様区分',
        'companion_hakama_usage' => 'お連れ様袴着用',
        'visit_reasons' => '来店理由',
        'parking_usage' => '駐車場利用',
        'parking_car_count' => '駐車台数',
        'considering_plans' => '検討中プラン',
        'heard_from' => '情報源',
        'inquiry_message' => '問い合わせ内容',
        'staff_name' => 'スタッフ名',
        'admin_assignee' => '管理担当者',
        'entrance_ticket_send_status' => '入場券送付',
        'request_method' => '申込方法',
        'privacy_agreed' => '個人情報同意',
        'cancel_flg' => 'キャンセルフラグ',
        'utm_source' => '流入元',
        'created_at' => '登録日時',
    ];

    private const DEFAULT_COLUMNS = [
        'status',
        'name',
        'furigana',
        'email',
        'phone',
        'reservation_datetime',
        'event_title',
        'venue_name',
    ];

    public function index(Request $request): Response
    {
        $filters = $this->parseFilters($request);

        $shops = Shop::orderBy('name')->get(['id', 'name']);

        $events = Event::with('shops:id')
            ->orderByDesc('start_at')
            ->orderByDesc('id')
            ->get(['id', 'title', 'form_type', 'start_at', 'end_at'])
            ->map(function ($event) {
                return [
                    'id' => $event->id,
                    'title' => $event->title,
                    'form_type' => $event->form_type,
                    'start_date' => optional($event->start_at)->format('Y-m-d'),
                    'end_date' => optional($event->end_at)->format('Y-m-d'),
                    'shop_ids' => $event->shops->pluck('id')->all(),
                ];
            });

        $reservations = null;
        $summary = null;
        $utmSources = [];

        if (!empty($filters['event_ids'])) {
            // UTM sources for the selected events (dynamic options)
            $utmSources = EventReservation::query()
                ->whereIn('event_id', $filters['event_ids'])
                ->selectRaw('utm_source, COUNT(*) as cnt')
                ->groupBy('utm_source')
                ->orderByDesc('cnt')
                ->get()
                ->map(fn ($row) => [
                    'value' => $row->utm_source,
                    'label' => $row->utm_source ?? '未設定',
                    'count' => (int) $row->cnt,
                ])
                ->values();

            $baseQuery = $this->buildQuery($filters);

            // Status summary (ignores status filter to always show the breakdown)
            $statusSummaryQuery = $this->buildQuery($filters, ignoreStatus: true);
            $statusSummary = $statusSummaryQuery
                ->selectRaw('status, COUNT(*) as cnt')
                ->groupBy('status')
                ->pluck('cnt', 'status')
                ->toArray();

            // Venue summary (respects all filters)
            $venueSummaryRaw = (clone $baseQuery)
                ->selectRaw('venue_id, COUNT(*) as cnt')
                ->groupBy('venue_id')
                ->orderByDesc('cnt')
                ->get();
            $venueNames = \App\Models\Venue::whereIn('id', $venueSummaryRaw->pluck('venue_id')->filter())->pluck('name', 'id');
            $venueSummary = $venueSummaryRaw->map(fn ($row) => [
                'venue_id' => $row->venue_id,
                'name' => $row->venue_id ? ($venueNames[$row->venue_id] ?? '不明') : '未設定',
                'count' => (int) $row->cnt,
            ])->values();

            $summary = [
                'total' => (int) (clone $baseQuery)->count(),
                'status_counts' => array_map(fn ($s) => (int) ($statusSummary[$s] ?? 0), self::STATUSES),
                'venues' => $venueSummary,
            ];

            $reservations = $baseQuery
                ->with(['event:id,title', 'venue:id,name'])
                ->orderByDesc('reservation_datetime')
                ->orderByDesc('id')
                ->paginate($filters['per_page'])
                ->withQueryString();
        }

        return Inertia::render('Admin/Event/ReservationExport', [
            'shops' => $shops,
            'events' => $events,
            'statuses' => self::STATUSES,
            'columnOptions' => self::COLUMN_LABELS,
            'defaultColumns' => self::DEFAULT_COLUMNS,
            'utmSources' => $utmSources,
            'summary' => $summary,
            'reservations' => $reservations,
            'filters' => $filters,
        ]);
    }

    public function csv(Request $request): StreamedResponse
    {
        $validated = $request->validate([
            'event_ids' => 'required|array|min:1',
            'event_ids.*' => 'integer',
            'statuses' => 'nullable|array',
            'statuses.*' => 'string',
            'utm_sources' => 'nullable|array',
            'shop_ids' => 'nullable|array',
            'shop_ids.*' => 'integer',
            'search' => 'nullable|string|max:255',
            'columns' => 'required|array|min:1',
            'columns.*' => 'string',
        ]);

        $filters = [
            'event_ids' => $validated['event_ids'],
            'statuses' => $validated['statuses'] ?? self::STATUSES,
            'utm_sources' => $validated['utm_sources'] ?? null,
            'shop_ids' => $validated['shop_ids'] ?? [],
            'search' => $validated['search'] ?? null,
        ];
        $columns = array_values(array_intersect($validated['columns'], array_keys(self::COLUMN_LABELS)));
        if (empty($columns)) {
            abort(422, '出力カラムが不正です');
        }

        $fileName = 'event_reservations_' . Carbon::now()->format('Ymd_His') . '.csv';

        $query = $this->buildQuery($filters)->with(['event:id,title', 'venue:id,name']);
        $rowCount = (clone $query)->count();

        // Record export log (before streaming, so we capture user even if client disconnects mid-stream)
        CsvExportLog::create([
            'user_id' => $request->user()->id,
            'target' => 'event_reservations',
            'filters' => $filters,
            'columns' => $columns,
            'row_count' => $rowCount,
            'file_name' => $fileName,
        ]);

        return new StreamedResponse(function () use ($query, $columns) {
            $stream = fopen('php://output', 'w');
            fprintf($stream, chr(0xEF) . chr(0xBB) . chr(0xBF));

            fputcsv($stream, array_map(fn ($c) => self::COLUMN_LABELS[$c] ?? $c, $columns));

            $query->orderByDesc('reservation_datetime')->orderByDesc('id')
                ->chunk(500, function ($rows) use ($stream, $columns) {
                    foreach ($rows as $row) {
                        fputcsv($stream, array_map(fn ($c) => $this->formatCell($row, $c), $columns));
                    }
                });

            fclose($stream);
        }, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $fileName . '"',
            'Cache-Control' => 'no-store, no-cache',
        ]);
    }

    private function parseFilters(Request $request): array
    {
        $eventIds = array_values(array_filter(array_map('intval', (array) $request->input('event_ids', []))));
        $shopIds = array_values(array_filter(array_map('intval', (array) $request->input('shop_ids', []))));
        $statuses = array_values(array_intersect((array) $request->input('statuses', []), self::STATUSES));
        $utmInput = $request->input('utm_sources');
        $utmSources = $utmInput === null ? null : (array) $utmInput;

        return [
            'shop_ids' => $shopIds,
            'event_ids' => $eventIds,
            'statuses' => $statuses,
            'utm_sources' => $utmSources,
            'search' => $request->input('search') ?: null,
            'per_page' => min(200, max(10, (int) $request->input('per_page', 50))),
        ];
    }

    /**
     * @param array<string,mixed> $filters
     */
    private function buildQuery(array $filters, bool $ignoreStatus = false)
    {
        $query = EventReservation::query()
            ->whereIn('event_id', $filters['event_ids']);

        if (!$ignoreStatus && !empty($filters['statuses'])) {
            $query->whereIn('status', $filters['statuses']);
        }

        if (isset($filters['utm_sources']) && is_array($filters['utm_sources'])) {
            $hasNull = in_array(null, $filters['utm_sources'], true)
                || in_array('', $filters['utm_sources'], true)
                || in_array('__null__', $filters['utm_sources'], true);
            $others = array_values(array_filter(
                $filters['utm_sources'],
                fn ($v) => $v !== null && $v !== '' && $v !== '__null__'
            ));
            $query->where(function ($q) use ($hasNull, $others) {
                if ($hasNull) {
                    $q->orWhereNull('utm_source');
                }
                if (!empty($others)) {
                    $q->orWhereIn('utm_source', $others);
                }
                if (!$hasNull && empty($others)) {
                    // All utm filters unchecked => no rows
                    $q->whereRaw('1 = 0');
                }
            });
        }

        if (!empty($filters['search'])) {
            $s = $filters['search'];
            $query->where(function ($q) use ($s) {
                $q->where('name', 'like', "%{$s}%")
                    ->orWhere('furigana', 'like', "%{$s}%")
                    ->orWhere('phone', 'like', "%{$s}%")
                    ->orWhere('email', 'like', "%{$s}%")
                    ->orWhere('referred_by_name', 'like', "%{$s}%");
            });
        }

        return $query;
    }

    private function formatCell(EventReservation $row, string $column): string
    {
        switch ($column) {
            case 'event_title':
                return (string) optional($row->event)->title;
            case 'venue_name':
                return (string) optional($row->venue)->name;
            case 'reservation_datetime':
                return $row->reservation_datetime
                    ? Carbon::parse($row->reservation_datetime)->format('Y-m-d H:i')
                    : '';
            case 'created_at':
                return $row->created_at ? $row->created_at->format('Y-m-d H:i') : '';
            case 'birth_date':
            case 'graduation_ceremony_date':
                $v = $row->{$column};
                return $v ? Carbon::parse($v)->format('Y-m-d') : '';
            case 'has_visited_before':
            case 'koichi_furisode_used':
            case 'companion_hakama_usage':
            case 'privacy_agreed':
            case 'cancel_flg':
                $v = $row->{$column};
                if ($v === null) return '';
                return $v ? 'はい' : 'いいえ';
            case 'companion_types':
            case 'visit_reasons':
            case 'considering_plans':
                $v = $row->{$column};
                if (!is_array($v)) return '';
                return implode('; ', $v);
            default:
                $v = $row->{$column} ?? '';
                return is_scalar($v) ? (string) $v : '';
        }
    }
}
