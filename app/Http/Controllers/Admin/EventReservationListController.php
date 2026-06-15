<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\EventReservation;
use App\Models\Shop;
use Illuminate\Http\Request;
use Inertia\Inertia;

/**
 * イベント予約者一覧（イベント一覧ページ上部のセクション）用 JSON エンドポイント。
 *
 * 担当店舗・フォーム種別・公開状態でイベントを絞り、その候補イベント（複数選択可）に
 * 紐づく予約者を一覧で返す。定型フォーム・form_schema(Blade LP) いずれの予約も
 * 標準カラム（name/phone/reservation_datetime/venue_id）が埋まるため、それらで表示する。
 */
class EventReservationListController extends Controller
{
    /**
     * イベント予約者一覧ページ。データ本体は search() の JSON を AJAX で取得する。
     */
    public function index(Request $request)
    {
        $shops = Shop::where('is_active', true)->orderBy('name')->get(['id', 'name']);

        return Inertia::render('Admin/EventReservation/Index', [
            'shops' => $shops,
        ]);
    }

    public function search(Request $request)
    {
        $user = $request->user();

        // デフォルト担当店舗（main フラグ優先、なければ最小ID）
        $defaultShopId = optional(
            $user?->shops()
                ->where('shops.is_active', true)
                ->orderByDesc('shop_user.main')
                ->orderBy('shops.id')
                ->first()
        )->id;

        // 担当店舗。未指定（初回）はデフォルト店舗、'all' は全店舗（絞り込みなし）。
        $shopParam = $request->has('shop_id') ? $request->input('shop_id') : $defaultShopId;
        $shopId = ($shopParam === 'all' || $shopParam === '' || $shopParam === null)
            ? null
            : (int) $shopParam;

        $formType = $request->input('form_type') ?: null;
        $publicStatus = $request->input('public_status', 'active');

        $eventIds = array_values(array_filter(array_map(
            fn ($v) => is_numeric($v) ? (int) $v : null,
            (array) $request->input('event_ids', [])
        ), fn ($v) => $v !== null));

        // イベント候補（複数選択用）。担当店舗・フォーム種別・公開状態で絞る。
        $eventOptionsQuery = Event::query();
        $this->applyShop($eventOptionsQuery, $shopId);
        if ($formType) {
            $eventOptionsQuery->where('form_type', $formType);
        }
        $this->applyPublicStatus($eventOptionsQuery, $publicStatus);
        $eventOptions = $eventOptionsQuery
            ->orderBy('title')
            ->get(['id', 'title', 'form_type'])
            ->map(fn ($e) => [
                'id' => $e->id,
                'title' => $e->title,
                'form_type' => $e->form_type,
            ])
            ->values();

        // 予約者クエリ。イベント側の条件（店舗・種別・公開状態）を whereHas で適用。
        $reservationsQuery = EventReservation::query()
            ->with([
                'event:id,title,form_type',
                'event.shops:id,name',
                'venue:id,name',
            ])
            ->whereHas('event', function ($q) use ($shopId, $formType, $publicStatus) {
                $this->applyShop($q, $shopId);
                if ($formType) {
                    $q->where('form_type', $formType);
                }
                $this->applyPublicStatus($q, $publicStatus);
            });

        if (! empty($eventIds)) {
            $reservationsQuery->whereIn('event_id', $eventIds);
        }

        $reservations = $reservationsQuery
            ->orderBy('cancel_flg', 'asc')
            ->orderByDesc('created_at')
            ->paginate(20)
            ->withQueryString();

        $rows = $reservations->getCollection()->map(function (EventReservation $r) {
            return [
                'id' => $r->id,
                'event_title' => $r->event?->title,
                'form_type' => $r->event?->form_type,
                'shop_names' => $r->event?->shops->pluck('name')->values() ?? [],
                'name' => $r->name ?: ($r->form_data['name'] ?? ''),
                'furigana' => $r->furigana ?: ($r->form_data['furigana'] ?? ''),
                'phone' => $r->phone ?: ($r->form_data['phone'] ?? ''),
                'reservation_datetime' => $r->reservation_datetime,
                'venue_name' => $r->venue?->name,
                'status' => $r->status,
                'cancel_flg' => (bool) $r->cancel_flg,
                'created_at' => optional($r->created_at)->toIso8601String(),
            ];
        })->values();

        return response()->json([
            'reservations' => [
                'data' => $rows,
                'current_page' => $reservations->currentPage(),
                'last_page' => $reservations->lastPage(),
                'total' => $reservations->total(),
            ],
            'event_options' => $eventOptions,
            'applied' => [
                'shop_id' => $shopId === null ? 'all' : $shopId,
                'form_type' => $formType ?? '',
                'public_status' => $publicStatus,
                'event_ids' => $eventIds,
            ],
        ]);
    }

    /**
     * イベントの担当店舗（多対多）で絞り込む。$shopId が null なら絞らない（全店舗）。
     */
    private function applyShop($query, ?int $shopId): void
    {
        if ($shopId) {
            $query->whereHas('shops', fn ($q) => $q->where('shops.id', $shopId));
        }
    }

    /**
     * イベントの公開状態で絞り込む（イベント一覧と同一ロジック）。
     */
    private function applyPublicStatus($query, ?string $status): void
    {
        $today = now()->startOfDay();

        if ($status === 'active') {
            $query->where('is_public', true)
                ->where(function ($q) use ($today) {
                    $q->whereNull('end_at')->orWhere('end_at', '>=', $today);
                });
        } elseif ($status === 'ended') {
            $query->where('is_public', true)
                ->whereNotNull('end_at')
                ->where('end_at', '<', $today);
        } elseif ($status === 'private') {
            $query->where('is_public', false);
        }
        // 'all' は絞り込みなし
    }
}
