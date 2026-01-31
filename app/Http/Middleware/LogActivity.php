<?php

namespace App\Http\Middleware;

use App\Models\ActivityLog;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class LogActivity
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // 認証済みユーザーのみログを記録
        if (Auth::check()) {
            $this->logActivity($request, $response);
        }

        return $response;
    }

    /**
     * アクティビティをログに記録
     */
    protected function logActivity(Request $request, Response $response): void
    {
        // ログイン・ログアウトは別途処理
        if ($request->routeIs('login') || $request->routeIs('logout')) {
            return;
        }

        // 成功レスポンスのみ記録（エラーは記録しない）
        if ($response->getStatusCode() >= 400) {
            return;
        }

        $user = Auth::user();
        $route = $request->route();
        $routeName = $route ? $route->getName() : null;

        // アクションタイプを判定
        $actionType = $this->determineActionType($request, $routeName);

        // リソース情報を取得
        $resourceInfo = $this->getResourceInfo($request, $route);

        // リソースの詳細情報を取得
        $resourceDetails = $this->getResourceDetails($resourceInfo);

        // 説明を生成
        $description = $this->generateDescription($request, $routeName, $resourceInfo, $resourceDetails);

        // 店舗IDを取得（ユーザーに関連する店舗の最初の1つ）
        $shopId = null;
        try {
            /** @var \App\Models\User $user */
            $shop = $user->shops()->first();
            $shopId = $shop?->id;
        } catch (\Exception $e) {
            // リレーションが存在しない場合はnullのまま
        }

        try {
            ActivityLog::create([
                'user_id' => $user->id,
                'shop_id' => $shopId,
                'action_type' => $actionType,
                'resource_type' => $resourceInfo['type'],
                'resource_id' => $resourceInfo['id'],
                'route_name' => $routeName,
                'url' => $request->fullUrl(),
                'method' => $request->method(),
                'description' => $description,
                'old_values' => $this->getOldValues($request, $actionType),
                'new_values' => $this->getNewValues($request, $actionType),
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);
        } catch (\Exception $e) {
            // ログ記録に失敗してもリクエスト処理は続行
            Log::error('Activity log creation failed', [
                'error' => $e->getMessage(),
                'user_id' => $user->id,
                'route' => $routeName,
            ]);
        }
    }

    /**
     * アクションタイプを判定
     */
    protected function determineActionType(Request $request, ?string $routeName): string
    {
        if (!$routeName) {
            return 'view';
        }

        // ルート名から判定
        if (str_contains($routeName, '.create') || str_contains($routeName, '.store')) {
            return 'create';
        }
        if (str_contains($routeName, '.edit') || str_contains($routeName, '.update')) {
            return 'update';
        }
        if (str_contains($routeName, '.destroy') || str_contains($routeName, '.delete')) {
            return 'delete';
        }
        if (str_contains($routeName, '.index') || str_contains($routeName, '.show')) {
            return 'view';
        }

        // HTTPメソッドから判定
        return match($request->method()) {
            'POST' => 'create',
            'PUT', 'PATCH' => 'update',
            'DELETE' => 'delete',
            default => 'view',
        };
    }

    /**
     * リソース情報を取得
     */
    protected function getResourceInfo(Request $request, $route): array
    {
        $routeName = $route ? $route->getName() : null;
        if (!$routeName) {
            return ['type' => null, 'id' => null];
        }

        // ルートパラメータからリソース情報を取得
        $parameters = $route->parameters();

        // リソースタイプを判定
        $resourceType = null;
        $resourceId = null;

        if (isset($parameters['event'])) {
            $resourceType = 'Event';
            $resourceId = is_object($parameters['event']) ? $parameters['event']->id : $parameters['event'];
        } elseif (isset($parameters['reservation'])) {
            $resourceType = 'EventReservation';
            $resourceId = is_object($parameters['reservation']) ? $parameters['reservation']->id : $parameters['reservation'];
        } elseif (isset($parameters['shop'])) {
            $resourceType = 'Shop';
            $resourceId = is_object($parameters['shop']) ? $parameters['shop']->id : $parameters['shop'];
        } elseif (isset($parameters['user'])) {
            $resourceType = 'User';
            $resourceId = is_object($parameters['user']) ? $parameters['user']->id : $parameters['user'];
        } elseif (isset($parameters['venue'])) {
            $resourceType = 'Venue';
            $resourceId = is_object($parameters['venue']) ? $parameters['venue']->id : $parameters['venue'];
        } elseif (isset($parameters['customer'])) {
            $resourceType = 'Customer';
            $resourceId = is_object($parameters['customer']) ? $parameters['customer']->id : $parameters['customer'];
        } elseif (isset($parameters['image'])) {
            $resourceType = 'EventImage';
            $resourceId = is_object($parameters['image']) ? $parameters['image']->id : $parameters['image'];
        } elseif (isset($parameters['timeslot'])) {
            $resourceType = 'EventTimeslot';
            $resourceId = is_object($parameters['timeslot']) ? $parameters['timeslot']->id : $parameters['timeslot'];
        } elseif (isset($parameters['note'])) {
            $resourceType = str_starts_with($routeName ?? '', 'admin.customers.notes.')
                ? 'CustomerNote'
                : 'ReservationNote';
            $resourceId = is_object($parameters['note']) ? $parameters['note']->id : $parameters['note'];
        }

        return [
            'type' => $resourceType,
            'id' => $resourceId,
        ];
    }

    /**
     * リソースの詳細情報を取得
     */
    protected function getResourceDetails(array $resourceInfo): ?array
    {
        if (!$resourceInfo['type'] || !$resourceInfo['id']) {
            return null;
        }

        try {
            $modelClass = "App\\Models\\{$resourceInfo['type']}";
            if (!class_exists($modelClass)) {
                return null;
            }

            $model = $modelClass::find($resourceInfo['id']);
            if (!$model) {
                return null;
            }

            // モデルごとに表示名を取得
            $name = null;
            switch ($resourceInfo['type']) {
                case 'Event':
                    $name = $model->title ?? null;
                    break;
                case 'EventReservation':
                    $name = $model->name ?? null;
                    break;
                case 'Shop':
                    $name = $model->name ?? null;
                    break;
                case 'User':
                    $name = $model->name ?? null;
                    break;
                case 'Venue':
                    $name = $model->name ?? null;
                    break;
                case 'Customer':
                    $name = $model->name ?? null;
                    break;
                case 'EventImage':
                    $name = $model->file_path ?? null;
                    break;
                case 'EventTimeslot':
                    $name = $model->start_at ? $model->start_at->format('Y-m-d H:i') : null;
                    break;
                case 'ReservationNote':
                    $name = mb_substr($model->content ?? '', 0, 50);
                    break;
                case 'CustomerNote':
                    $name = mb_substr($model->content ?? '', 0, 50);
                    break;
            }

            return [
                'name' => $name,
                'model' => $model,
            ];
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * 説明を生成
     */
    protected function generateDescription(Request $request, ?string $routeName, array $resourceInfo, ?array $resourceDetails): string
    {
        if (!$routeName) {
            return $request->method() . ' ' . $request->path();
        }

        $actionType = $this->determineActionType($request, $routeName);
        
        // リソースタイプのラベルを取得
        $resourceLabel = '';
        if ($resourceInfo['type']) {
            $labels = [
                'Event' => 'イベント',
                'EventReservation' => '予約',
                'EventImage' => 'イベント画像',
                'EventTimeslot' => '予約枠',
                'Shop' => '店舗',
                'User' => 'スタッフ',
                'Venue' => '会場',
                'Customer' => '顧客',
                'ReservationNote' => '予約メモ',
                'CustomerNote' => '顧客メモ',
            ];
            $resourceLabel = $labels[$resourceInfo['type']] ?? $resourceInfo['type'];
        }

        // ルート名から詳細な操作を判定
        $actionDetail = $this->getActionDetail($routeName, $resourceLabel);

        // リソース名を取得
        $resourceName = $resourceDetails['name'] ?? null;

        // 説明を構築
        $description = '';
        if ($actionDetail) {
            $description = $actionDetail;
        } else {
            $descriptions = [
                'view' => $resourceLabel ? "{$resourceLabel}を閲覧" : 'ページを閲覧',
                'create' => $resourceLabel ? "{$resourceLabel}を作成" : '新規作成',
                'update' => $resourceLabel ? "{$resourceLabel}を更新" : '更新',
                'delete' => $resourceLabel ? "{$resourceLabel}を削除" : '削除',
            ];
            $description = $descriptions[$actionType] ?? $actionType;
        }

        // リソース名を追加
        if ($resourceName) {
            $description .= "「{$resourceName}」";
        }

        // IDを追加
        if ($resourceInfo['id']) {
            $description .= " (ID: {$resourceInfo['id']})";
        }

        return $description;
    }

    /**
     * ルート名から詳細な操作を取得
     */
    protected function getActionDetail(?string $routeName, string $resourceLabel): ?string
    {
        if (!$routeName) {
            return null;
        }

        // ルート名から詳細な操作を判定
        $details = [
            'admin.events.index' => 'イベント一覧を閲覧',
            'admin.events.create' => 'イベント新規作成画面を表示',
            'admin.events.store' => 'イベントを作成',
            'admin.events.show' => 'イベント詳細を閲覧',
            'admin.events.edit' => 'イベント編集画面を表示',
            'admin.events.update' => 'イベントを更新',
            'admin.events.destroy' => 'イベントを削除',
            'admin.events.reservations.index' => '予約一覧を閲覧',
            'admin.reservations.show' => '予約詳細を閲覧',
            'admin.reservations.edit' => '予約編集画面を表示',
            'admin.reservations.update' => '予約を更新',
            'admin.reservations.destroy' => '予約をキャンセル',
            'admin.reservations.notes.store' => '予約メモを追加',
            'admin.reservations.notes.destroy' => '予約メモを削除',
            'admin.customers.notes.store' => '顧客メモを追加',
            'admin.customers.notes.destroy' => '顧客メモを削除',
            'admin.shops.index' => '店舗一覧を閲覧',
            'admin.shops.create' => '店舗新規作成画面を表示',
            'admin.shops.store' => '店舗を作成',
            'admin.shops.edit' => '店舗編集画面を表示',
            'admin.shops.update' => '店舗を更新',
            'admin.shops.destroy' => '店舗を削除',
            'admin.users.index' => 'スタッフ一覧を閲覧',
            'admin.users.create' => 'スタッフ新規作成画面を表示',
            'admin.users.store' => 'スタッフを作成',
            'admin.users.edit' => 'スタッフ編集画面を表示',
            'admin.users.update' => 'スタッフを更新',
            'admin.users.destroy' => 'スタッフを削除',
            'admin.events.images.index' => 'イベント画像一覧を閲覧',
            'admin.events.images.create' => 'イベント画像新規登録画面を表示',
            'admin.events.images.store' => 'イベント画像を登録',
            'admin.events.images.destroy' => 'イベント画像を削除',
            'admin.events.timeslots.index' => '予約枠一覧を閲覧',
            'admin.events.timeslots.create' => '予約枠新規作成画面を表示',
            'admin.events.timeslots.store' => '予約枠を作成',
            'admin.events.timeslots.edit' => '予約枠編集画面を表示',
            'admin.events.timeslots.update' => '予約枠を更新',
            'admin.events.timeslots.destroy' => '予約枠を削除',
            'admin.events.venues.store' => '会場を追加',
            'admin.venues.update' => '会場を更新',
            'admin.events.venues.destroy' => '会場を削除',
            'dashboard' => 'ダッシュボードを閲覧',
        ];

        return $details[$routeName] ?? null;
    }

    /**
     * 更新前の値を取得（更新時のみ）
     */
    protected function getOldValues(Request $request, string $actionType): ?array
    {
        if ($actionType !== 'update') {
            return null;
        }

        // 更新前の値を取得する場合は、コントローラーで設定する必要がある
        // ここでは簡易的にnullを返す
        return null;
    }

    /**
     * 更新後の値を取得（作成・更新時のみ）
     */
    protected function getNewValues(Request $request, string $actionType): ?array
    {
        if (!in_array($actionType, ['create', 'update'])) {
            return null;
        }

        // 機密情報を除外
        $excludedFields = ['password', 'password_confirmation', '_token', '_method'];
        $data = array_diff_key($request->all(), array_flip($excludedFields));

        // 配列が大きすぎる場合は制限
        if (count($data) > 50) {
            return array_slice($data, 0, 50, true);
        }

        return $data;
    }
}

