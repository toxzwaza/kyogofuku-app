<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\CustomerPhoto;
use App\Models\CustomerQuestionnaire;
use App\Models\EventReservation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * 予約→顧客の紐付け時に、予約に紐づく写真・振袖アンケートを顧客へ引き継ぐ。
 * （ReservationLineContactMigrator の写真・アンケート版）
 *
 * - 写真: すべて顧客に付け替える（S3のファイルパスはそのままでよい）
 * - アンケート: 顧客側に未登録なら付け替える。顧客側に既存がある場合は
 *   顧客側を優先し、予約側はそのまま残す（データ消失を防ぐ）。
 *   その際、予約側アンケートのスキャン写真は整合性のため予約側に残す。
 * - 紐付け解除時に戻す処理は行わない（引き継いだデータは顧客の資産として残す）
 */
class ReservationAssetMigrator
{
    /**
     * @return array{photos:int, questionnaire:string} 移行結果（questionnaire: migrated / kept_customer / none）
     */
    public function migrate(EventReservation $reservation, Customer $customer): array
    {
        return DB::transaction(function () use ($reservation, $customer) {
            $reservationQuestionnaire = CustomerQuestionnaire::query()
                ->where('event_reservation_id', $reservation->id)
                ->first();
            $customerHasQuestionnaire = CustomerQuestionnaire::query()
                ->where('customer_id', $customer->id)
                ->exists();

            $questionnaireResult = 'none';
            $keepPhotoIds = [];

            if ($reservationQuestionnaire) {
                if (! $customerHasQuestionnaire) {
                    $reservationQuestionnaire->update([
                        'customer_id' => $customer->id,
                        'event_reservation_id' => null,
                    ]);
                    $questionnaireResult = 'migrated';
                } else {
                    // 顧客側を優先。予約側アンケートとそのスキャン写真は予約に残す
                    $keepPhotoIds = array_filter([
                        $reservationQuestionnaire->page1_photo_id,
                        $reservationQuestionnaire->page2_photo_id,
                    ]);
                    $questionnaireResult = 'kept_customer';
                }
            }

            $photosQuery = CustomerPhoto::query()
                ->where('event_reservation_id', $reservation->id);
            if (! empty($keepPhotoIds)) {
                $photosQuery->whereNotIn('id', $keepPhotoIds);
            }
            $migratedPhotos = $photosQuery->update([
                'customer_id' => $customer->id,
                'event_reservation_id' => null,
            ]);

            if ($migratedPhotos > 0 || $questionnaireResult !== 'none') {
                Log::info('Reservation assets migrated to customer', [
                    'reservation_id' => $reservation->id,
                    'customer_id' => $customer->id,
                    'photos' => $migratedPhotos,
                    'questionnaire' => $questionnaireResult,
                ]);
            }

            return [
                'photos' => $migratedPhotos,
                'questionnaire' => $questionnaireResult,
            ];
        });
    }
}
