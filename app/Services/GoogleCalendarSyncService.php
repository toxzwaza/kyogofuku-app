<?php

namespace App\Services;

use App\Models\GoogleCalendarEventSync;
use App\Models\Shop;
use App\Models\StaffSchedule;
use App\Models\User;
use Google\Client as GoogleClient;
use Google\Service\Calendar;
use Google\Service\Calendar\Event;
use Google\Service\Calendar\EventDateTime;
use Google\Service\Calendar\EventSource;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\URL;

class GoogleCalendarSyncService
{
    /**
     * スケジュールを参加者が所属する店舗のGoogleカレンダーに同期
     */
    public function syncScheduleToShopCalendars(StaffSchedule $schedule): void
    {
        if (!$schedule->sync_to_google_calendar) {
            Log::info('[GoogleCalendar] sync_to_google_calendar=false のためスキップ', ['schedule_id' => $schedule->id]);
            return;
        }

        Log::info('[GoogleCalendar] syncScheduleToShopCalendars 開始', [
            'schedule_id' => $schedule->id,
            'schedule_title' => $schedule->title,
        ]);

        $shopsToSync = $this->getShopsForSchedule($schedule);

        Log::info('[GoogleCalendar] 同期対象店舗数', [
            'schedule_id' => $schedule->id,
            'shops_count' => count($shopsToSync),
            'shop_ids' => collect($shopsToSync)->pluck('shop.id')->toArray(),
        ]);

        if (empty($shopsToSync)) {
            Log::warning('[GoogleCalendar] 同期対象店舗なし（参加者がGoogle連携済みかつ店舗所属でない可能性）', [
                'schedule_id' => $schedule->id,
            ]);
        }

        foreach ($shopsToSync as $shopData) {
            $shop = $shopData['shop'];
            $tokenUser = $shopData['token_user'];
            if (!$tokenUser) {
                continue;
            }

            try {
                Log::info('[GoogleCalendar] 店舗カレンダーへ同期実行', [
                    'schedule_id' => $schedule->id,
                    'shop_id' => $shop->id,
                    'shop_name' => $shop->name,
                ]);
                $this->syncToShopCalendar($schedule, $shop, $tokenUser);
                Log::info('[GoogleCalendar] 同期成功', ['schedule_id' => $schedule->id, 'shop_id' => $shop->id]);
            } catch (\Throwable $e) {
                Log::error('[GoogleCalendar] 同期失敗', [
                    'schedule_id' => $schedule->id,
                    'shop_id' => $shop->id,
                    'error' => $e->getMessage(),
                ]);
                try {
                    \App\Jobs\SyncToGoogleCalendarJob::dispatch($schedule, 'sync');
                } catch (\Throwable $jobEx) {
                    Log::warning('[GoogleCalendar] リトライジョブの投入に失敗（スケジュール作成は継続）', [
                        'error' => $jobEx->getMessage(),
                    ]);
                }
            }
        }
    }

    /**
     * 参加者変更時の差分を反映
     */
    public function syncScheduleToShopCalendarsOnUpdate(StaffSchedule $schedule): void
    {
        if (!$schedule->sync_to_google_calendar) {
            $this->removeFromShopCalendarsIfSynced($schedule);
            return;
        }

        // 担当者（participantUsers）のキャッシュをクリアしてDBから再取得
        // （Controllerで participantUsers()->sync() 後に update() が呼ばれるが、
        //  Observer 発火時点で $schedule のリレーションは古いままのため）
        $schedule->unsetRelation('participantUsers');

        $shopsToSync = $this->getShopsForSchedule($schedule);
        $targetShopIds = collect($shopsToSync)->pluck('shop.id')->toArray();
        $existingSyncs = GoogleCalendarEventSync::where('staff_schedule_id', $schedule->id)->get();

        foreach ($existingSyncs as $sync) {
            if (!in_array($sync->shop_id, $targetShopIds)) {
                $this->deleteFromShopCalendar($sync);
                $sync->delete();
            } else {
                $shopData = collect($shopsToSync)->first(fn ($item) => $item['shop']->id === $sync->shop_id);
                $tokenUser = $shopData['token_user'] ?? null;
                if ($tokenUser) {
                    try {
                        $this->syncToShopCalendar($schedule, $sync->shop, $tokenUser);
                    } catch (\Throwable $e) {
                        Log::error('Google Calendar update failed', [
                            'schedule_id' => $schedule->id,
                            'shop_id' => $sync->shop_id,
                            'error' => $e->getMessage(),
                        ]);
                        try {
                            \App\Jobs\SyncToGoogleCalendarJob::dispatch($schedule, 'sync');
                        } catch (\Throwable $jobEx) {
                            Log::warning('[GoogleCalendar] リトライジョブの投入に失敗', ['error' => $jobEx->getMessage()]);
                        }
                    }
                }
            }
        }

        foreach ($shopsToSync as $shopData) {
            $shop = $shopData['shop'];
            $tokenUser = $shopData['token_user'];
            if (!$tokenUser) {
                continue;
            }

            $hasSync = $existingSyncs->contains('shop_id', $shop->id);
            if (!$hasSync) {
                try {
                    $this->syncToShopCalendar($schedule, $shop, $tokenUser);
                } catch (\Throwable $e) {
                    Log::error('Google Calendar insert failed', [
                        'schedule_id' => $schedule->id,
                        'shop_id' => $shop->id,
                        'error' => $e->getMessage(),
                    ]);
                    try {
                        \App\Jobs\SyncToGoogleCalendarJob::dispatch($schedule, 'sync');
                    } catch (\Throwable $jobEx) {
                        Log::warning('[GoogleCalendar] リトライジョブの投入に失敗', ['error' => $jobEx->getMessage()]);
                    }
                }
            }
        }
    }

    /**
     * sync_to_google_calendar をオフにした場合、Googleカレンダーから削除して同期レコードを消去
     */
    public function removeFromShopCalendarsIfSynced(StaffSchedule $schedule): void
    {
        $syncs = GoogleCalendarEventSync::where('staff_schedule_id', $schedule->id)->get();
        foreach ($syncs as $sync) {
            try {
                $this->deleteFromShopCalendar($sync);
            } catch (\Throwable $e) {
                Log::error('[GoogleCalendar] 同期解除時の削除失敗', [
                    'schedule_id' => $schedule->id,
                    'shop_id' => $sync->shop_id,
                    'error' => $e->getMessage(),
                ]);
            }
            $sync->delete();
        }
    }

    /**
     * スケジュールを店舗のGoogleカレンダーから削除
     */
    public function deleteFromShopCalendars(StaffSchedule $schedule): void
    {
        $syncs = GoogleCalendarEventSync::where('staff_schedule_id', $schedule->id)->get();

        foreach ($syncs as $sync) {
            try {
                $this->deleteFromShopCalendar($sync);
            } catch (\Throwable $e) {
                Log::error('Google Calendar delete failed', [
                    'schedule_id' => $schedule->id,
                    'shop_id' => $sync->shop_id,
                    'error' => $e->getMessage(),
                ]);
            }
        }
        // 同期レコードは schedule 削除時の cascade で削除される
    }

    /**
     * 参加者の所属店舗を取得（Google連携済みの参加者がいる店舗のみ）
     * 戻り値: [['shop' => Shop, 'token_user' => User|null], ...]
     */
    protected function getShopsForSchedule(StaffSchedule $schedule): array
    {
        $schedule->load(['participantUsers.shops']);
        $participants = $schedule->participantUsers;
        $participantsWithGoogle = $participants->filter(
            fn (User $user) => !empty($user->google_calendar_refresh_token)
        );

        Log::info('[GoogleCalendar] getShopsForSchedule 参加者情報', [
            'schedule_id' => $schedule->id,
            'participants_count' => $participants->count(),
            'participants_with_google_count' => $participantsWithGoogle->count(),
            'participant_ids' => $participants->pluck('id')->toArray(),
            'participants_with_google_ids' => $participantsWithGoogle->pluck('id')->toArray(),
            'participant_names' => $participants->pluck('name')->toArray(),
        ]);

        if ($participants->isEmpty()) {
            Log::warning('[GoogleCalendar] 参加者が0人（参加者を追加すると同期されます）', ['schedule_id' => $schedule->id]);
        }

        foreach ($participants as $p) {
            $shopIds = $p->shops->pluck('id')->toArray();
            if (empty($shopIds)) {
                Log::info('[GoogleCalendar] 参加者が店舗に未所属', [
                    'participant_id' => $p->id,
                    'participant_name' => $p->name,
                    'has_google_token' => !empty($p->google_calendar_refresh_token),
                ]);
            }
        }

        $shopsMap = [];
        foreach ($participantsWithGoogle as $user) {
            foreach ($user->shops as $shop) {
                if (!isset($shopsMap[$shop->id])) {
                    $shopsMap[$shop->id] = [
                        'shop' => $shop,
                        'token_user' => $user,
                    ];
                }
            }
        }

        return array_values($shopsMap);
    }

    /**
     * 特定店舗のカレンダーにスケジュールを同期
     */
    protected function syncToShopCalendar(StaffSchedule $schedule, Shop $shop, User $tokenUser): void
    {
        $client = $this->createAuthenticatedClient($tokenUser);
        $service = new Calendar($client);

        $calendarId = $shop->google_calendar_id ?: 'primary';

        $existingSync = GoogleCalendarEventSync::where('staff_schedule_id', $schedule->id)
            ->where('shop_id', $shop->id)
            ->first();

        $event = $this->buildCalendarEvent($schedule);

        if ($existingSync) {
            $service->events->update(
                $existingSync->google_calendar_id,
                $existingSync->google_event_id,
                $event
            );
        } else {
            $createdEvent = $service->events->insert($calendarId, $event);
            GoogleCalendarEventSync::create([
                'staff_schedule_id' => $schedule->id,
                'shop_id' => $shop->id,
                'google_event_id' => $createdEvent->getId(),
                'google_calendar_id' => $calendarId,
            ]);
        }
    }

    /**
     * 特定店舗のカレンダーからイベントを削除
     */
    protected function deleteFromShopCalendar(GoogleCalendarEventSync $sync): void
    {
        $sync->load('shop');
        $shop = $sync->shop;
        if (!$shop) {
            return;
        }

        $tokenUser = $shop->users()
            ->whereNotNull('google_calendar_refresh_token')
            ->where('google_calendar_refresh_token', '!=', '')
            ->first();

        if (!$tokenUser) {
            return;
        }

        $client = $this->createAuthenticatedClient($tokenUser);
        $service = new Calendar($client);

        $service->events->delete(
            $sync->google_calendar_id,
            $sync->google_event_id
        );
    }

    /**
     * Google API クライアントを生成（認証済み）
     */
    protected function createAuthenticatedClient(User $user): GoogleClient
    {
        $client = new GoogleClient();
        $client->setClientId(config('services.google.client_id'));
        $client->setClientSecret(config('services.google.client_secret'));
        $client->setAccessType('offline');
        $client->setPrompt('consent');
        $client->setScopes([
            \Google\Service\Calendar::CALENDAR,
        ]);

        // ローカル環境で SSL 証明書エラー (cURL error 60) を回避
        if (config('app.env') === 'local') {
            $client->setHttpClient(new \GuzzleHttp\Client(['verify' => false]));
        }

        $client->refreshToken($user->google_calendar_refresh_token);

        return $client;
    }

    /**
     * StaffSchedule から Google Calendar Event オブジェクトを構築
     */
    protected function buildCalendarEvent(StaffSchedule $schedule): Event
    {
        $event = new Event();
        $event->setSummary($schedule->title);

        $descriptionParts = [];

        // 担当者（schedule_participants）を説明の先頭に目立つ形で表示
        $schedule->loadMissing('participantUsers');
        if ($schedule->participantUsers->isNotEmpty()) {
            $descriptionParts[] = '【担当者】' . $schedule->participantUsers->pluck('name')->join('、');
            $descriptionParts[] = '';
        }

        if ($schedule->description) {
            $descriptionParts[] = $schedule->description;
            $descriptionParts[] = '';
        }

        // 予約由来のスケジュールの場合、予約詳細URLを追加
        if ($schedule->event_reservation_id) {
            $reservationUrl = URL::route('admin.reservations.show', $schedule->event_reservation_id, ['absolute' => true]);
            $descriptionParts[] = '';
            $descriptionParts[] = '予約詳細: ' . $reservationUrl;

            // Googleカレンダーにソースリンクを設定（クリックで予約詳細を開く）
            $source = new EventSource();
            $source->setTitle('予約詳細');
            $source->setUrl($reservationUrl);
            $event->setSource($source);
        }

        if (!empty($descriptionParts)) {
            $event->setDescription(implode("\n", $descriptionParts));
        }

        if ($schedule->all_day) {
            $start = new EventDateTime();
            $start->setDate($schedule->start_at->format('Y-m-d'));
            $event->setStart($start);

            $end = new EventDateTime();
            $endAt = $schedule->end_at ?? $schedule->start_at;
            $endDate = $endAt->copy()->addDay()->format('Y-m-d');
            $end->setDate($endDate);
            $event->setEnd($end);
        } else {
            $start = new EventDateTime();
            $start->setDateTime($schedule->start_at->toRfc3339String());
            $start->setTimeZone('Asia/Tokyo');
            $event->setStart($start);

            $end = new EventDateTime();
            $endAt = $schedule->end_at ?? $schedule->start_at->copy()->addHour();
            $end->setDateTime($endAt->toRfc3339String());
            $end->setTimeZone('Asia/Tokyo');
            $event->setEnd($end);
        }

        return $event;
    }
}
