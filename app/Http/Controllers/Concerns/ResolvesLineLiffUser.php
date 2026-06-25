<?php

namespace App\Http\Controllers\Concerns;

use App\Models\Customer;
use App\Models\CustomerLineContact;
use App\Services\Line\LineIdTokenVerifier;
use Illuminate\Http\Request;

/**
 * LIFF からの id_token を検証して line_user_id（sub）と顧客を解決する共通処理。
 */
trait ResolvesLineLiffUser
{
    /**
     * id_token を検証して line_user_id を返す（失敗時 null）。
     */
    protected function resolveLineUserId(Request $request): ?string
    {
        $idToken = (string) $request->input('id_token', '');
        if ($idToken === '') {
            return null;
        }

        $channelId = (string) config('line.liff.login_channel_id');
        $verified = app(LineIdTokenVerifier::class)->verify($idToken, $channelId);

        return $verified['sub'] ?? null;
    }

    /**
     * line_user_id から顧客を解決（紐付け済みのみ・最初の1件）。
     */
    protected function resolveCustomerByLineUserId(?string $lineUserId): ?Customer
    {
        if (!$lineUserId) {
            return null;
        }

        $contact = CustomerLineContact::query()
            ->where('line_user_id', $lineUserId)
            ->whereNotNull('customer_id')
            ->first();

        return $contact?->customer;
    }
}
