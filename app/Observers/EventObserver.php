<?php

namespace App\Observers;

use App\Http\Controllers\Api\PublicEventController;
use App\Models\Event;
use Illuminate\Support\Facades\Cache;

class EventObserver
{
    public function saved(Event $event): void
    {
        $this->bumpCacheVersion();
    }

    public function deleted(Event $event): void
    {
        $this->bumpCacheVersion();
    }

    /**
     * 公開API のキャッシュ世代をインクリメントして既存キャッシュを無効化
     */
    private function bumpCacheVersion(): void
    {
        $current = Cache::get(PublicEventController::CACHE_VERSION_KEY, 1);
        Cache::forever(PublicEventController::CACHE_VERSION_KEY, $current + 1);
    }
}
