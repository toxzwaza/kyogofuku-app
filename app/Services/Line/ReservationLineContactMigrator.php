<?php

namespace App\Services\Line;

use App\Models\Customer;
use App\Models\CustomerLineContact;
use App\Models\EventReservation;
use Illuminate\Support\Facades\Log;

class ReservationLineContactMigrator
{
    /**
     * 予約専用の LINE 連絡先を顧客に付け替える（メッセージ履歴は同一 contact 行のまま）
     *
     * @return array{ok: bool, message?: string}
     */
    public function migrateReservationContactsToCustomer(EventReservation $reservation, Customer $customer): array
    {
        $contacts = CustomerLineContact::query()
            ->where('event_reservation_id', $reservation->id)
            ->whereNull('customer_id')
            ->get();

        if ($contacts->isEmpty()) {
            return ['ok' => true];
        }

        foreach ($contacts as $contact) {
            $hasOtherLine = CustomerLineContact::query()
                ->where('customer_id', $customer->id)
                ->where('line_user_id', '!=', $contact->line_user_id)
                ->exists();

            if ($hasOtherLine) {
                Log::warning('Reservation LINE migrate: customer already has another LINE user', [
                    'reservation_id' => $reservation->id,
                    'customer_id' => $customer->id,
                    'reservation_line_user_id' => $contact->line_user_id,
                ]);

                return [
                    'ok' => false,
                    'message' => 'この顧客には既に別の LINE 連絡先が登録されているため、予約の LINE を自動で引き継げません。管理者が LINE 設定を確認してください。',
                ];
            }

            $contact->customer_id = $customer->id;
            $contact->event_reservation_id = null;
            if ($customer->shop_id) {
                $contact->shop_id = $customer->shop_id;
            }
            $contact->save();
        }

        return ['ok' => true];
    }
}
