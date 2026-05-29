<?php

namespace App\Services\Line;

use App\Http\Controllers\LineWebhookController;
use App\Models\CustomerLineContact;
use Illuminate\Support\Facades\Log;

/**
 * 店舗LINEグループ宛ての通知を一元化するサービス。
 *
 * - notifySystemLinked(): システム連携完了を店舗グループに通知
 * - notifyInboundMessage(): 連携済みユーザーからのメッセージを店舗グループに通知
 *
 * 既存 LineWebhookController::pushToLineGroup を再利用し、通知失敗は
 * Log::warning で握りつぶす（業務処理を止めない方針、既存 BladeLp/LineNotifier と同じ）。
 */
class ShopLineGroupNotifier
{
    public function notifySystemLinked(CustomerLineContact $contact): void
    {
        $groupId = $this->resolveGroupId($contact);
        if ($groupId === null) {
            return;
        }

        $name = $this->resolveName($contact);
        $message = "{$name}さんがシステム連携が完了しました。";
        $detailUrl = $this->resolveDetailUrl($contact);

        $this->push($groupId, $message, $detailUrl, $contact, 'system_linked');
    }

    public function notifyInboundMessage(CustomerLineContact $contact, string $text): void
    {
        $groupId = $this->resolveGroupId($contact);
        if ($groupId === null) {
            return;
        }

        $name = $this->resolveName($contact);
        $message = "{$name}さんからメッセージを受信しました。\n{$text}";
        $detailUrl = $this->resolveDetailUrl($contact);

        $this->push($groupId, $message, $detailUrl, $contact, 'inbound_message');
    }

    /**
     * 紐付け先の氏名を解決する。
     * 優先順位: customer.name → event_reservation.name → contact.label → 'お客様'
     */
    private function resolveName(CustomerLineContact $contact): string
    {
        $contact->loadMissing(['customer', 'eventReservation']);

        if ($contact->customer && filled($contact->customer->name)) {
            return (string) $contact->customer->name;
        }
        if ($contact->eventReservation && filled($contact->eventReservation->name)) {
            return (string) $contact->eventReservation->name;
        }
        if (filled($contact->label)) {
            return (string) $contact->label;
        }

        return 'お客様';
    }

    /**
     * 通知先の店舗グループ ID を解決する。
     */
    private function resolveGroupId(CustomerLineContact $contact): ?string
    {
        $contact->loadMissing('shop');
        $gid = $contact->shop?->line_group_id;

        return filled($gid) ? (string) $gid : null;
    }

    /**
     * 詳細ページの URL を解決する。
     * customer 優先、なければ event_reservation。
     */
    private function resolveDetailUrl(CustomerLineContact $contact): ?string
    {
        if ($contact->customer_id) {
            return route('admin.customers.show', $contact->customer_id, true);
        }
        if ($contact->event_reservation_id) {
            return route('admin.reservations.show', $contact->event_reservation_id, true);
        }

        return null;
    }

    private function push(string $groupId, string $message, ?string $detailUrl, CustomerLineContact $contact, string $kind): void
    {
        try {
            $line = app(LineWebhookController::class);
            if ($detailUrl !== null) {
                $line->pushToLineGroup($message, $groupId, $detailUrl, '詳細を開く');
            } else {
                $line->pushToLineGroup($message, $groupId);
            }
        } catch (\Throwable $e) {
            Log::warning('[ShopLineGroupNotifier] LINE通知失敗', [
                'kind' => $kind,
                'contact_id' => $contact->id,
                'shop_id' => $contact->shop_id,
                'group_id' => $groupId,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
