<?php

namespace App\Http\Middleware;

use App\Models\Event;
use Illuminate\Http\Request;
use Inertia\Middleware;
use Tightenco\Ziggy\Ziggy;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that is loaded on the first page visit.
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determine the current asset version.
     */
    public function version(Request $request): string|null
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $shared = array_merge(parent::share($request), [
            'auth' => [
                'user' => $request->user(),
            ],
            'ziggy' => function () use ($request) {
                return array_merge((new Ziggy)->toArray(), [
                    'location' => $request->url(),
                ]);
            },
            'flash' => [
                'success' => $request->session()->get('success'),
                'error' => $request->session()->get('error'),
            ],
        ]);

        // GTMタグIDを特定のルートでのみ取得
        $gtmId = $this->getGtmIdForRoute($request);
        if ($gtmId) {
            $shared['gtmId'] = $gtmId;
        }

        return $shared;
    }

    /**
     * ルートに応じてGTMタグIDを取得
     */
    protected function getGtmIdForRoute(Request $request): ?string
    {
        $route = $request->route();
        if (!$route) {
            return null;
        }

        $routeName = $route->getName();
        
        // 対象ルートのみ処理
        if (!in_array($routeName, ['event.show', 'event.reserve', 'event.reserve.success'])) {
            return null;
        }

        try {
            $event = null;

            if ($routeName === 'event.show') {
                // slugパラメータからイベントを取得
                $slug = $route->parameter('slug');
                if ($slug) {
                    $event = Event::where('slug', $slug)
                        ->where('is_public', true)
                        ->first();
                }
            } else {
                // eventパラメータからイベントを取得（Route Model Binding）
                $event = $route->parameter('event');
                
                // Route Model Bindingがまだ実行されていない場合、IDから取得
                if (!$event instanceof Event) {
                    $eventId = $route->parameter('event');
                    if ($eventId) {
                        $event = Event::where('id', $eventId)
                            ->where('is_public', true)
                            ->first();
                    }
                }
            }

            return $event && $event->gtm_id ? $event->gtm_id : null;
        } catch (\Exception $e) {
            // エラーが発生した場合はGTMタグを表示しない
            return null;
        }
    }
}
