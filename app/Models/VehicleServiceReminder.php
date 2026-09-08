<?php

namespace App\Models;

use App\Enums\ServiceReminderType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VehicleServiceReminder extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'type' => ServiceReminderType::class,
            'due_on' => 'date',
            'last_done_on' => 'date',
            'is_active' => 'boolean',
        ];
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(CustomerVehicle::class, 'customer_vehicle_id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function isOverdue(): bool
    {
        return $this->daysRemaining() !== null && $this->daysRemaining() < 0;
    }

    public function daysRemaining(): ?int
    {
        return $this->due_on === null ? null : (int) now()->startOfDay()->diffInDays($this->due_on, false);
    }

    /**
     * Kilometres left before the item is due. Negative means it is already past due; null means
     * either the reminder has no mileage target or the vehicle's odometer is unknown, which are
     * both cases where claiming a number would be a guess.
     */
    public function kilometresRemaining(): ?int
    {
        $current = $this->vehicle?->mileage_km;

        return $this->due_at_km === null || $current === null
            ? null
            : (int) $this->due_at_km - (int) $current;
    }

    /**
     * Owners act on whichever limit arrives first, so urgency takes the nearer of the two.
     * A rough 1000 km per month converts mileage into a comparable horizon.
     */
    public function urgencyInDays(): ?int
    {
        $byDate = $this->daysRemaining();
        $byKm = $this->kilometresRemaining();
        $byKmAsDays = $byKm === null ? null : (int) round($byKm / 1000 * 30);

        return match (true) {
            $byDate === null => $byKmAsDays,
            $byKmAsDays === null => $byDate,
            default => min($byDate, $byKmAsDays),
        };
    }

    public function status(): string
    {
        $urgency = $this->urgencyInDays();

        return match (true) {
            $urgency === null => 'unscheduled',
            $urgency < 0 => 'overdue',
            $urgency <= 30 => 'due_soon',
            default => 'scheduled',
        };
    }
}
