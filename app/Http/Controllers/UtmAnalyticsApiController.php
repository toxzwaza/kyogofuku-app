<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\EventReservation;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class UtmAnalyticsApiController extends Controller
{
    /**
     * UTM 流入経路分析データを返す（GAS 等から計測・分析用に利用）
     * 認証: X-Api-Key ヘッダー または token クエリパラメータで UTM_ANALYTICS_API_SECRET を送信
     */
    public function __invoke(Request $request): JsonResponse
    {
        $secret = config('services.utm_analytics_api_secret');
        if (empty($secret)) {
            return response()->json(['success' => false, 'message' => 'エンドポイントが未設定です'], 503, [], JSON_UNESCAPED_UNICODE);
        }

        $token = $request->header('X-Api-Key') ?? $request->query('token');
        if ($token !== $secret) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 401, [], JSON_UNESCAPED_UNICODE);
        }

        $query = EventReservation::where('cancel_flg', false);

        if ($request->has('from_date')) {
            $fromDate = Carbon::parse($request->input('from_date'))->startOfDay();
            $query->whereDate('created_at', '>=', $fromDate);
        }
        if ($request->has('to_date')) {
            $toDate = Carbon::parse($request->input('to_date'))->endOfDay();
            $query->whereDate('created_at', '<=', $toDate);
        }

        $bySource = (clone $query)
            ->select(DB::raw('COALESCE(utm_source, \'（未計測）\') as source'), DB::raw('count(*) as count'))
            ->groupBy(DB::raw('COALESCE(utm_source, \'（未計測）\')'))
            ->orderByDesc('count')
            ->get()
            ->map(fn($item) => ['source' => $item->source, 'count' => (int) $item->count])
            ->values()
            ->toArray();

        $publishedEvents = Event::where('is_public', true)->orderBy('title')->get();
        $byEvent = [];
        foreach ($publishedEvents as $event) {
            $eventQuery = (clone $query)->where('event_id', $event->id);
            $count = $eventQuery->count();
            if ($count > 0) {
                $byEvent[] = [
                    'event_id' => $event->id,
                    'event_title' => $event->title,
                    'count' => $count,
                ];
            }
        }

        return response()->json([
            'success' => true,
            'total_conversion' => $query->count(),
            'by_source' => $bySource,
            'by_event' => $byEvent,
        ], 200, [], JSON_UNESCAPED_UNICODE);
    }
}
