<?php

namespace App\Services\Referral;

use App\Models\CustomerLineContact;
use App\Models\Referral;
use App\Models\ReferralCode;
use App\Models\ReferralSetting;

/**
 * 紹介URL（?ref=XXXX）経由の紐付け。自己紹介・既存顧客・重複を判定して referrals を作成する。
 * ※「紹介できるのは成約済顧客のみ」は referral_codes の発行側で担保（成約済のみ発行）。
 */
class ReferralLinkingService
{
    /**
     * @return array{status:string, reason:?string, referral:?Referral}
     */
    public function link(string $refCode, string $referredLineUserId): array
    {
        $code = ReferralCode::query()->where('code', strtoupper(trim($refCode)))->first();
        if (!$code) {
            return ['status' => 'rejected', 'reason' => 'invalid_code', 'referral' => null];
        }
        $referrerId = $code->customer_id;

        // 重複（同一紹介者×同一友達）→ 既存を返す
        $existing = Referral::query()
            ->where('referrer_customer_id', $referrerId)
            ->where('referred_line_user_id', $referredLineUserId)
            ->first();
        if ($existing) {
            return ['status' => $existing->status, 'reason' => 'duplicate', 'referral' => $existing];
        }

        // 自己紹介：紹介者自身のLINEと一致
        $isSelf = CustomerLineContact::query()
            ->where('customer_id', $referrerId)
            ->where('line_user_id', $referredLineUserId)
            ->exists();
        if ($isSelf) {
            $referral = $this->createRejected($referrerId, $referredLineUserId, 'self');

            return ['status' => Referral::STATUS_REJECTED, 'reason' => 'self', 'referral' => $referral];
        }

        // 既存顧客：被紹介者のLINEが既にいずれかの顧客に紐付いている
        $existingContact = CustomerLineContact::query()
            ->where('line_user_id', $referredLineUserId)
            ->first();
        if ($existingContact) {
            $referral = $this->createRejected(
                $referrerId,
                $referredLineUserId,
                'existing_customer',
                $existingContact->customer_id,
            );

            return ['status' => Referral::STATUS_REJECTED, 'reason' => 'existing_customer', 'referral' => $referral];
        }

        // 最初の紹介者のみ有効：別の紹介者から既に有効な紹介がある被紹介者は却下する
        $alreadyByOther = Referral::query()
            ->where('referred_line_user_id', $referredLineUserId)
            ->where('referrer_customer_id', '!=', $referrerId)
            ->whereIn('status', [
                Referral::STATUS_LINKED,
                Referral::STATUS_CONTRACTED,
                Referral::STATUS_MATURED,
            ])
            ->exists();
        if ($alreadyByOther) {
            $referral = $this->createRejected($referrerId, $referredLineUserId, 'already_referred');

            return ['status' => Referral::STATUS_REJECTED, 'reason' => 'already_referred', 'referral' => $referral];
        }

        // 紹介成立待ち
        $months = ReferralSetting::getInt('referral_expire_months', 6);
        $referral = Referral::create([
            'referrer_customer_id' => $referrerId,
            'referred_line_user_id' => $referredLineUserId,
            'status' => Referral::STATUS_LINKED,
            'expires_at' => now()->addMonths($months),
        ]);

        return ['status' => Referral::STATUS_LINKED, 'reason' => null, 'referral' => $referral];
    }

    private function createRejected(int $referrerId, string $lineUserId, string $reason, ?int $referredCustomerId = null): Referral
    {
        return Referral::create([
            'referrer_customer_id' => $referrerId,
            'referred_line_user_id' => $lineUserId,
            'referred_customer_id' => $referredCustomerId,
            'status' => Referral::STATUS_REJECTED,
            'reject_reason' => $reason,
            'expires_at' => now()->addMonths(ReferralSetting::getInt('referral_expire_months', 6)),
        ]);
    }
}
