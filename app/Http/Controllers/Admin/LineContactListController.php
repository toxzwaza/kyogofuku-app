<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CustomerLineContact;
use App\Models\Shop;
use Illuminate\Http\Request;
use Inertia\Inertia;

/**
 * LINE 連携一覧（顧客紐付け / 予約紐付け の両方を1画面で表示・解除する管理画面）
 */
class LineContactListController extends Controller
{
    public function index(Request $request)
    {
        $type = (string) $request->input('type', 'all');         // all / customer / reservation / unbound
        $shopId = $request->input('shop_id') ? (int) $request->input('shop_id') : null;
        $keyword = trim((string) $request->input('q', ''));

        $query = CustomerLineContact::query()
            ->with([
                'customer:id,shop_id,name,kana,phone_number',
                'eventReservation:id,event_id,name,furigana,phone,reservation_datetime',
                'eventReservation.event:id,title,slug',
                'shop:id,name',
            ])
            ->orderByDesc('id');

        if ($type === 'customer') {
            $query->whereNotNull('customer_id');
        } elseif ($type === 'reservation') {
            $query->whereNotNull('event_reservation_id');
        } elseif ($type === 'unbound') {
            $query->whereNull('customer_id')->whereNull('event_reservation_id');
        }

        if ($shopId !== null) {
            $query->where('shop_id', $shopId);
        }

        if ($keyword !== '') {
            $like = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $keyword).'%';
            $query->where(function ($q) use ($like) {
                $q->whereHas('customer', function ($c) use ($like) {
                    $c->where('name', 'like', $like)
                        ->orWhere('kana', 'like', $like)
                        ->orWhere('phone_number', 'like', $like);
                })
                    ->orWhereHas('eventReservation', function ($r) use ($like) {
                        $r->where('name', 'like', $like)
                            ->orWhere('furigana', 'like', $like)
                            ->orWhere('phone', 'like', $like);
                    })
                    ->orWhere('line_user_id', 'like', $like)
                    ->orWhere('label', 'like', $like);
            });
        }

        $paginator = $query->paginate(50)->withQueryString();

        $items = $paginator->getCollection()->map(function (CustomerLineContact $c) {
            $kind = $c->customer_id ? 'customer' : ($c->event_reservation_id ? 'reservation' : 'unbound');
            $base = [
                'id' => $c->id,
                'kind' => $kind,
                'label' => $c->label,
                'shop' => $c->shop ? ['id' => $c->shop->id, 'name' => $c->shop->name] : null,
                'line_user_id' => $c->line_user_id,
                'created_at' => $c->created_at?->toIso8601String(),
            ];

            if ($kind === 'customer' && $c->customer) {
                return $base + [
                    'target' => [
                        'kind' => 'customer',
                        'id' => $c->customer->id,
                        'name' => $c->customer->name,
                        'kana' => $c->customer->kana,
                        'phone' => $c->customer->phone_number,
                        'detail_url' => route('admin.customers.show', $c->customer->id),
                    ],
                ];
            }

            if ($kind === 'reservation' && $c->eventReservation) {
                $r = $c->eventReservation;

                return $base + [
                    'target' => [
                        'kind' => 'reservation',
                        'id' => $r->id,
                        'name' => $r->name,
                        'kana' => $r->furigana,
                        'phone' => $r->phone,
                        'event_title' => $r->event?->title,
                        'reservation_datetime' => $r->reservation_datetime?->toIso8601String(),
                        'detail_url' => route('admin.reservations.show', $r->id),
                    ],
                ];
            }

            return $base + ['target' => null];
        })->values();

        return Inertia::render('Admin/LineContacts/Index', [
            'contacts' => [
                'data' => $items,
                'links' => $paginator->linkCollection(),
                'meta' => [
                    'current_page' => $paginator->currentPage(),
                    'last_page' => $paginator->lastPage(),
                    'per_page' => $paginator->perPage(),
                    'total' => $paginator->total(),
                    'from' => $paginator->firstItem(),
                    'to' => $paginator->lastItem(),
                ],
            ],
            'filters' => [
                'type' => $type,
                'shop_id' => $shopId,
                'q' => $keyword,
            ],
            'shops' => Shop::query()->orderBy('id')->get(['id', 'name']),
            'counts' => $this->countsByKind(),
        ]);
    }

    public function destroy(CustomerLineContact $contact)
    {
        $contact->delete();

        return redirect()->back()->with('success', 'LINE 連携を解除しました。');
    }

    private function countsByKind(): array
    {
        $rows = CustomerLineContact::query()
            ->selectRaw("CASE WHEN customer_id IS NOT NULL THEN 'customer' WHEN event_reservation_id IS NOT NULL THEN 'reservation' ELSE 'unbound' END AS kind, COUNT(*) AS cnt")
            ->groupBy('kind')
            ->pluck('cnt', 'kind')
            ->toArray();

        return [
            'all' => array_sum($rows),
            'customer' => $rows['customer'] ?? 0,
            'reservation' => $rows['reservation'] ?? 0,
            'unbound' => $rows['unbound'] ?? 0,
        ];
    }
}
