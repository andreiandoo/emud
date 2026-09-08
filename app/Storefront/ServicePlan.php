<?php

namespace App\Storefront;

use App\Enums\ServiceReminderType;
use App\Models\CustomerVehicle;
use App\Models\VehicleServiceReminder;
use Illuminate\Support\Collection;

/**
 * The maintenance plan for one vehicle.
 *
 * Reminders are ordered by whichever limit arrives first — date or mileage — because that is
 * how an owner decides what to do next. Anything undated sorts last rather than being hidden:
 * an item the customer set up but never scheduled is still something they wanted to remember.
 */
class ServicePlan
{
    /** @return Collection<int, VehicleServiceReminder> */
    public function forVehicle(CustomerVehicle $vehicle): Collection
    {
        return $vehicle->reminders()
            ->active()
            ->get()
            ->each(fn (VehicleServiceReminder $reminder) => $reminder->setRelation('vehicle', $vehicle))
            ->sortBy(fn (VehicleServiceReminder $reminder) => $reminder->urgencyInDays() ?? PHP_INT_MAX)
            ->values();
    }

    /**
     * Records that an item was done and schedules the next one from the type's own interval.
     * Scheduling from the completion date rather than from the old due date stops a late
     * service permanently shifting the whole plan earlier.
     */
    public function markDone(VehicleServiceReminder $reminder, ?string $doneOn = null, ?int $doneAtKm = null): VehicleServiceReminder
    {
        $type = $reminder->type instanceof ServiceReminderType ? $reminder->type : ServiceReminderType::Other;
        $completedOn = $doneOn === null ? now()->toDateString() : $doneOn;

        $months = $reminder->interval_months ?? $type->defaultIntervalMonths();
        $kilometres = $reminder->interval_km ?? $type->defaultIntervalKm();

        $reminder->update([
            'last_done_on' => $completedOn,
            'last_done_km' => $doneAtKm ?? $reminder->vehicle?->mileage_km,
            'due_on' => $months === null ? null : now()->parse($completedOn)->addMonths($months)->toDateString(),
            'due_at_km' => $this->nextMileage($kilometres, $doneAtKm ?? $reminder->vehicle?->mileage_km),
        ]);

        return $reminder->refresh();
    }

    /**
     * The starting set offered to a customer who has just added a vehicle. Legal deadlines are
     * left undated because only the owner knows them; wear items get an interval so the plan
     * becomes useful as soon as a service is recorded.
     *
     * @return list<array<string, mixed>>
     */
    public function suggestedFor(CustomerVehicle $vehicle): array
    {
        $existing = $vehicle->reminders()->pluck('type')->all();

        $suggestions = [];

        foreach ([ServiceReminderType::Itp, ServiceReminderType::Rca, ServiceReminderType::OilAndFilter, ServiceReminderType::TimingBelt] as $type) {
            if (in_array($type->value, array_map(fn ($value) => $value instanceof ServiceReminderType ? $value->value : (string) $value, $existing), true)) {
                continue;
            }

            $suggestions[] = [
                'type' => $type,
                'interval_months' => $type->defaultIntervalMonths(),
                'interval_km' => $type->defaultIntervalKm(),
            ];
        }

        return $suggestions;
    }

    private function nextMileage(?int $interval, ?int $currentKm): ?int
    {
        return $interval === null || $currentKm === null ? null : $currentKm + $interval;
    }
}
