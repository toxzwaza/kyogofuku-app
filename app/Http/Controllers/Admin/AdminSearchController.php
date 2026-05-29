<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\EventReservation;
use Illuminate\Http\Request;

/**
 * 管理画面グローバル検索API（コマンドパレット・トップバー検索用）
 *
 * GET /admin/search?q=xxx
 *   -> { reservations: [...], customers: [...] }
 */
class AdminSearchController extends Controller
{
    public function __invoke(Request $request)
    {
        $q = trim((string) $request->query('q', ''));
        if ($q === '' || mb_strlen($q) < 1) {
            return response()->json([
                'reservations' => [],
                'customers'    => [],
                'query'        => $q,
            ]);
        }

        $like = '%' . $q . '%';

        $reservations = EventReservation::with(['event:id,title', 'venue:id,name'])
            ->where('cancel_flg', false)
            ->where(function ($w) use ($like, $q) {
                $w->where('name', 'like', $like)
                    ->orWhere('furigana', 'like', $like)
                    ->orWhere('phone', 'like', $like)
                    ->orWhere('email', 'like', $like);
                if (ctype_digit($q)) {
                    $w->orWhere('id', (int) $q);
                }
            })
            ->orderByDesc('reservation_datetime')
            ->take(8)
            ->get(['id', 'event_id', 'venue_id', 'name', 'furigana', 'phone', 'reservation_datetime', 'status']);

        $customers = Customer::where(function ($w) use ($like, $q) {
                $w->where('name', 'like', $like)
                    ->orWhere('kana', 'like', $like)
                    ->orWhere('phone_number', 'like', $like)
                    ->orWhere('email', 'like', $like);
                if (ctype_digit($q)) {
                    $w->orWhere('id', (int) $q);
                }
            })
            ->orderByDesc('updated_at')
            ->take(8)
            ->get(['id', 'name', 'kana', 'phone_number', 'email']);

        return response()->json([
            'reservations' => $reservations,
            'customers'    => $customers,
            'query'        => $q,
        ]);
    }
}
