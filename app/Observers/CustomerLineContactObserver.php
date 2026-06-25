<?php

namespace App\Observers;

use App\Models\Customer;
use App\Models\CustomerLineContact;
use App\Services\Referral\ReferralCustomerSyncService;

/**
 * LINE連携（顧客への紐付け）が行われたら、その顧客を被紹介者とする紹介を同期する。
 * referred_line_user_id だけで作られていた紹介に referred_customer_id を補完し、
 * 確定成約があれば contracted へ昇格させる（連携が後から行われたケースの取りこぼし防止）。
 */
class CustomerLineContactObserver
{
    public function created(CustomerLineContact $contact): void
    {
        $this->sync($contact);
    }

    public function updated(CustomerLineContact $contact): void
    {
        $this->sync($contact);
    }

    private function sync(CustomerLineContact $contact): void
    {
        if (!$contact->customer_id) {
            return;
        }

        $customer = Customer::find($contact->customer_id);
        if ($customer) {
            app(ReferralCustomerSyncService::class)->sync($customer);
        }
    }
}
