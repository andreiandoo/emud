<?php

namespace App\Directory;

use App\Enums\ServiceLeadEventType;
use App\Models\ServiceAppointment;
use App\Models\ServiceShop;
use App\Models\ServiceShopLeadEvent;

/**
 * Counts what a workshop received, so a paid listing can be argued about with numbers.
 *
 * Deliberately records nothing about who caused the event — no session id, no address, no user.
 * The question these rows answer is "how many leads did this workshop get in September", and
 * keeping an identifier would turn a billing ledger into a tracking log without improving the
 * answer.
 */
class LeadTracker
{
    public function record(ServiceShop $shop, ServiceLeadEventType $type, ?ServiceAppointment $appointment = null): void
    {
        ServiceShopLeadEvent::query()->create([
            'service_shop_id' => $shop->id,
            'service_appointment_id' => $appointment?->id,
            'type' => $type,
        ]);
    }
}
