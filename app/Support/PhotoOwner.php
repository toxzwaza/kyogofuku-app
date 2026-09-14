<?php

namespace App\Support;

use App\Models\Customer;
use App\Models\CustomerPhoto;
use App\Models\CustomerQuestionnaire;
use App\Models\EventReservation;
use Illuminate\Database\Eloquent\Builder;

/**
 * 写真・振袖アンケートの「持ち主」（顧客 or イベント予約）を抽象化する値オブジェクト。
 *
 * 予約詳細の「写真・アンケート」タブは、予約が顧客に紐付いていれば顧客のデータを
 * 直接操作し、未紐付けなら予約自身に紐づけて保存する。その分岐をここに集約する。
 */
class PhotoOwner
{
    private function __construct(
        public readonly ?Customer $customer,
        public readonly ?EventReservation $reservation,
    ) {
    }

    public static function forCustomer(Customer $customer): self
    {
        return new self($customer, null);
    }

    /**
     * 予約に対する操作対象を解決する（顧客紐付け済みなら顧客が持ち主になる）
     */
    public static function forReservation(EventReservation $reservation): self
    {
        if ($reservation->customer_id && $reservation->customer) {
            return new self($reservation->customer, null);
        }

        return new self(null, $reservation);
    }

    public function isCustomer(): bool
    {
        return $this->customer !== null;
    }

    /** 持ち主モデルのID */
    public function id(): int
    {
        return $this->isCustomer() ? (int) $this->customer->id : (int) $this->reservation->id;
    }

    /** S3保存パスのディレクトリ（customers/{id}/... または reservations/{id}/...） */
    public function dir(): string
    {
        return $this->isCustomer() ? 'customers' : 'reservations';
    }

    /** customer_photos / customer_questionnaires に設定する外部キー */
    public function attrs(): array
    {
        return $this->isCustomer()
            ? ['customer_id' => $this->customer->id]
            : ['event_reservation_id' => $this->reservation->id];
    }

    /** @return Builder<CustomerPhoto> */
    public function photosQuery(): Builder
    {
        return CustomerPhoto::query()->where($this->attrs());
    }

    public function ownsPhoto(CustomerPhoto $photo): bool
    {
        return $this->isCustomer()
            ? (int) $photo->customer_id === (int) $this->customer->id
            : (int) $photo->event_reservation_id === (int) $this->reservation->id;
    }

    public function questionnaire(): ?CustomerQuestionnaire
    {
        return CustomerQuestionnaire::query()->where($this->attrs())->first();
    }

    public function firstOrCreateQuestionnaire(): CustomerQuestionnaire
    {
        return CustomerQuestionnaire::firstOrCreate($this->attrs());
    }

    /**
     * アンケート印刷用の「顧客情報」オブジェクト（予約の場合は予約者情報をマッピング）
     */
    public function printCustomer(): object
    {
        if ($this->isCustomer()) {
            return $this->customer;
        }

        $r = $this->reservation;

        return (object) [
            'name' => $r->name,
            'kana' => $r->furigana,
            'birth_date' => $r->birth_date,
            'coming_of_age_year' => $r->seijin_year,
            'guardian_name' => null,
            'postal_code' => $r->postal_code,
            'address' => $r->address,
            'phone_number' => $r->phone,
            'school_name' => $r->school_name,
            'staff_name' => $r->staff_name,
        ];
    }
}
