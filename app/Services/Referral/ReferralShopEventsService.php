<?php

namespace App\Services\Referral;

use App\Models\Event;
use App\Models\Referral;
use App\Models\ReferralCode;

/**
 * 未連携ユーザーのLIFF画面に出す「紹介者の所属店舗で開催中の公開イベント一覧」。
 * 紹介者は ?ref= の紹介コード、または referrals.referred_line_user_id から解決する。
 */
class ReferralShopEventsService
{
    /**
     * 被紹介者の LINE user_id から紹介者の店舗の開催中イベントを返す。
     * 紹介レコードがなければ空配列。
     *
     * @return array<int, array{title:string, thumbnail_url:?string, reserve_url:string}>
     */
    public function forReferredLineUserId(?string $lineUserId): array
    {
        if (!$lineUserId) {
            return [];
        }

        $referral = Referral::query()
            ->where('referred_line_user_id', $lineUserId)
            ->whereIn('status', [
                Referral::STATUS_LINKED,
                Referral::STATUS_CONTRACTED,
                Referral::STATUS_MATURED,
            ])
            ->with('referrer:id,shop_id')
            ->latest('id')
            ->first();

        return $this->forShopId($referral?->referrer?->shop_id);
    }

    /**
     * 紹介コードから紹介者の店舗の開催中イベントを返す。
     *
     * @return array<int, array{title:string, thumbnail_url:?string, reserve_url:string}>
     */
    public function forReferralCode(string $code): array
    {
        if ($code === '') {
            return [];
        }

        $referralCode = ReferralCode::query()
            ->where('code', $code)
            ->with('customer:id,shop_id')
            ->first();

        return $this->forShopId($referralCode?->customer?->shop_id);
    }

    /**
     * 店舗IDから開催中（is_public かつ 開催期間内。start/end が NULL の側は制限なし）の
     * イベントを開催日順に返す。
     *
     * @return array<int, array{title:string, thumbnail_url:?string, reserve_url:string}>
     */
    public function forShopId(?int $shopId): array
    {
        if (!$shopId) {
            return [];
        }

        $today = now()->startOfDay();

        return Event::query()
            ->where('is_public', true)
            ->whereHas('shops', fn ($q) => $q->where('shops.id', $shopId))
            ->where(fn ($q) => $q->whereNull('start_at')->orWhere('start_at', '<=', $today))
            ->where(fn ($q) => $q->whereNull('end_at')->orWhere('end_at', '>=', $today))
            ->orderBy('start_at')
            ->get()
            ->map(fn (Event $e) => [
                'title' => (string) $e->title,
                'thumbnail_url' => $e->thumbnail_url,
                // 条件を満たさないイベントはサーバー側で紹介ページへリダイレクトされる
                'reserve_url' => route('event.reserve.page', ['slug' => $e->slug]),
            ])
            ->values()
            ->all();
    }
}
