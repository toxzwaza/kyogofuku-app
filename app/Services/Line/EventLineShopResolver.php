<?php

namespace App\Services\Line;

use App\Models\Event;

class EventLineShopResolver
{
    /**
     * イベントに紐づく店舗のうち、ID 昇順で先頭 1 件（LINE 用 shop_id）
     */
    public function resolveShopIdForEvent(Event $event): ?int
    {
        $id = $event->shops()
            ->where('shops.is_active', true)
            ->orderBy('shops.id')
            ->value('shops.id');

        if ($id !== null) {
            return (int) $id;
        }

        return $event->shops()->orderBy('shops.id')->value('shops.id');
    }
}
