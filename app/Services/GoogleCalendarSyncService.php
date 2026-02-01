<?php

namespace App\Services;

use App\Models\GoogleCalendarEventSync;
use App\Models\Shop;
use App\Models\StaffSchedule;
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
            'shop_ids' => collect($shopsToSync)->pluck('id')->toArray(),
        ]);

        if (empty($shopsToSync)) {
            Log::warning('[GoogleCalendar] 同期対象店舗なし（担当者の所属店舗がない、またはGOOGLE_CALENDAR_REFRESH_TOKEN未設定の可能性）', [
                'schedule_id' => $schedule->id,
            ]);
        }

        foreach ($shopsToSync as $shop) {
            try {
                Log::info('[GoogleCalendar] 店舗カレンダーへ同期実行', [
                    'schedule_id' => $schedule->id,
                    'shop_id' => $shop->id,
                    'shop_name' => $shop->name,
                ]);
                $this->syncToShopCalendar($schedule, $shop);
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

        // DBから最新のスケジュールを再取得（participantUsers()->sync() 後に update() が呼ばれるが、
        //  Observer 発火時点で $schedule の participantUsers は古いキャッシュの可能性があるため）
        $schedule = StaffSchedule::with('participantUsers.shops')->findOrFail($schedule->id);

        $shopsToSync = $this->getShopsForSchedule($schedule);
        $targetShopIds = collect($shopsToSync)->pluck('id')->toArray();
        $existingSyncs = GoogleCalendarEventSync::where('staff_schedule_id', $schedule->id)->get();

        foreach ($existingSyncs as $sync) {
            if (!in_array($sync->shop_id, $targetShopIds)) {
                $this->deleteFromShopCalendar($sync);
                $sync->delete();
            } else {
                try {
                    $sync->load('shop');
                    $this->syncToShopCalendar($schedule, $sync->shop);
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

        foreach ($shopsToSync as $shop) {
            $hasSync = $existingSyncs->contains('shop_id', $shop->id);
            if (!$hasSync) {
                try {
                    $this->syncToShopCalendar($schedule, $shop);
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
     * 担当者の所属店舗を取得（.env の GOOGLE_CALENDAR_REFRESH_TOKEN を使用して同期）
     * 戻り値: Shop[]
     */
    protected function getShopsForSchedule(StaffSchedule $schedule): array
    {
        $refreshToken = config('services.google.calendar_refresh_token');
        if (empty($refreshToken)) {
            Log::warning('[GoogleCalendar] GOOGLE_CALENDAR_REFRESH_TOKEN が未設定です', ['schedule_id' => $schedule->id]);
            return [];
        }

        $schedule->load(['participantUsers.shops']);
        $participants = $schedule->participantUsers;

        Log::info('[GoogleCalendar] getShopsForSchedule 担当者情報', [
            'schedule_id' => $schedule->id,
            'participants_count' => $participants->count(),
            'participant_ids' => $participants->pluck('id')->toArray(),
            'participant_names' => $participants->pluck('name')->toArray(),
        ]);

        if ($participants->isEmpty()) {
            Log::warning('[GoogleCalendar] 担当者が0人（担当者を追加すると同期されます）', ['schedule_id' => $schedule->id]);
            return [];
        }

        $shopsMap = [];
        foreach ($participants as $user) {
            foreach ($user->shops as $shop) {
                $shopsMap[$shop->id] = $shop;
            }
        }

        return array_values($shopsMap);
    }

    /**
     * 特定店舗のカレンダーにスケジュールを同期
     */
    protected function syncToShopCalendar(StaffSchedule $schedule, Shop $shop): void
    {
        $client = $this->createAuthenticatedClient();
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
        if (empty(config('services.google.calendar_refresh_token'))) {
            return;
        }

        $client = $this->createAuthenticatedClient();
        $service = new Calendar($client);

        $service->events->delete(
            $sync->google_calendar_id,
            $sync->google_event_id
        );
    }

    /**
     * Google API クライアントを生成（.env の GOOGLE_CALENDAR_REFRESH_TOKEN を使用）
     */
    protected function createAuthenticatedClient(): GoogleClient
    {
        $refreshToken = config('services.google.calendar_refresh_token');
        if (empty($refreshToken)) {
            throw new \RuntimeException('GOOGLE_CALENDAR_REFRESH_TOKEN が設定されていません。');
        }

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

        $client->refreshToken($refreshToken);

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
