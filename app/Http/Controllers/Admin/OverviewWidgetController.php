<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CustomerLineMessage;
use App\Models\EventReservation;
use Illuminate\Http\Request;

/**
 * オーバービュー系のウィジェット向け JSON エンドポイント。
 *
 * - LINE受信ウィジェット（全管理画面に常駐する右下の通知アイコン）
 * - 直近の予約（過去へ遡るページング）
 *
 * いずれもログインユーザーの所属店舗に絞って返す。
 */
class OverviewWidgetController extends Controller
{
    /** ログインユーザーの所属店舗ID */
    private function userShopIds(Request $request): array
    {
        $user = $request->user();

        return $user
            ? $user->shops()->where('shops.is_active', true)->pluck('shops.id')->toArray()
            : [];
    }

    /**
     * LINE受信（お客様単位でグループ化）。
     * 未読は全件（古い見落とし防止・上限300）、既読は直近のみ（上限200）。
     */
    public function lineInbox(Request $request)
    {
        $userShopIds = $this->userShopIds($request);
        $result = ['groups' => [], 'unread_total' => 0, 'total' => 0];

        if (empty($userShopIds)) {
            return response()->json($result);
        }

        $contactInShop = fn ($q) => $q->whereIn('shop_id', $userShopIds);
        $withContact = [
            'contact' => fn ($q) => $q->select('id', 'customer_id', 'event_reservation_id', 'label', 'shop_id'),
            'contact.customer' => fn ($q) => $q->select('id', 'name'),
            'contact.eventReservation' => fn ($q) => $q->select('id', 'name'),
        ];

        $unreadMessages = CustomerLineMessage::query()
            ->with($withContact)
            ->where('direction', CustomerLineMessage::DIRECTION_INBOUND)
            ->whereNull('admin_read_at')
            ->whereHas('contact', $contactInShop)
            ->orderByDesc('id')
            ->limit(300)
            ->get();

        $readMessages = CustomerLineMessage::query()
            ->with($withContact)
            ->where('direction', CustomerLineMessage::DIRECTION_INBOUND)
            ->whereNotNull('admin_read_at')
            ->whereHas('contact', $contactInShop)
            ->orderByDesc('id')
            ->limit(200)
            ->get();

        // マージして id 降順（最新が先頭）に整列
        $allMessages = $unreadMessages->concat($readMessages)->sortByDesc('id')->values();

        $groups = [];
        foreach ($allMessages as $m) {
            $contact = $m->contact;
            if (! $contact) {
                continue;
            }
            $key = $contact->id;
            if (! isset($groups[$key])) {
                $isReservation = $contact->customer_id === null && $contact->event_reservation_id !== null;
                $displayName = $isReservation
                    ? ($contact->eventReservation?->name ?? '予約者')
                    : ($contact->customer?->name ?? '顧客');
                $groups[$key] = [
                    'contact_id'     => $contact->id,
                    'name'           => $displayName,
                    'label'          => $contact->label ?? 'お客様',
                    'link_kind'      => $isReservation ? 'reservation' : 'customer',
                    'customer_id'    => $contact->customer_id,
                    'reservation_id' => $contact->event_reservation_id,
                    'unread_count'   => 0,
                    'total_count'    => 0,
                    'messages'       => [],
                ];
            }
            $isImage = $m->message_type !== null && $m->message_type !== 'text';
            $isUnread = $m->admin_read_at === null;
            $groups[$key]['messages'][] = [
                'id'         => $m->id,
                'text'       => (string) ($m->text ?? ''),
                'is_image'   => $isImage,
                'is_unread'  => $isUnread,
                'created_at' => $m->created_at?->toIso8601String(),
            ];
            $groups[$key]['total_count']++;
            if ($isUnread) {
                $groups[$key]['unread_count']++;
            }
        }

        // 展開時は時系列（古い順）で読めるように並べ替え
        foreach ($groups as &$g) {
            $g['messages'] = array_reverse($g['messages']);
        }
        unset($g);

        return response()->json([
            'groups'       => array_values($groups), // 最新受信のお客様が先頭
            'unread_total' => $unreadMessages->count(),
            'total'        => $allMessages->count(),
        ]);
    }

    /**
     * 直近の予約（新しい順）。offset で過去へ遡れる。
     */
    public function recentReservations(Request $request)
    {
        $userShopIds = $this->userShopIds($request);
        $offset = max(0, (int) $request->query('offset', 0));
        $limit  = min(50, max(1, (int) $request->query('limit', 8)));

        $shopEventIds = \DB::table('event_shop')
            ->whereIn('shop_id', $userShopIds)
            ->distinct()
            ->pluck('event_id');

        $rows = self::recentReservationsQuery($shopEventIds)
            ->offset($offset)
            ->limit($limit + 1) // +1件で「まだ先がある」を判定
            ->get(['id', 'event_id', 'venue_id', 'name', 'furigana', 'reservation_datetime', 'status', 'created_at', 'utm_source']);

        $hasMore = $rows->count() > $limit;
        $items = $rows->take($limit)->map(fn ($r) => self::mapReservation($r))->values();

        return response()->json([
            'items'    => $items,
            'has_more' => $hasMore,
        ]);
    }

    /**
     * 直近の予約リストの共通クエリ（キャンセル除外・所属店舗絞り込み・LINE連携有無の集計）。
     * オーバービュー初期表示（OverviewController）と本ウィジェットで共用する。
     */
    public static function recentReservationsQuery($shopEventIds)
    {
        return EventReservation::with(['event:id,title', 'venue:id,name'])
            // LINE連携の有無：line_user_id が入った連携済みコンタクトが1件でもあれば連携済み
            ->withCount(['lineContacts as line_linked_count' => fn ($q) => $q->whereNotNull('line_user_id')])
            ->where('cancel_flg', false)
            ->whereIn('event_id', $shopEventIds)
            ->orderByDesc('created_at');
    }

    /** 直近の予約1件を表示用配列へ（LINE連携有無・流入導線を含む） */
    public static function mapReservation($r): array
    {
        return [
            'id'                   => $r->id,
            'name'                 => $r->name,
            'status'               => $r->status,
            'reservation_datetime' => $r->reservation_datetime,
            'created_at'           => $r->created_at?->toIso8601String(),
            'utm_source'           => $r->utm_source,
            'line_linked'          => (int) ($r->line_linked_count ?? 0) > 0,
            'event'                => $r->event ? ['title' => $r->event->title] : null,
            'venue'                => $r->venue ? ['name' => $r->venue->name] : null,
        ];
    }
}
