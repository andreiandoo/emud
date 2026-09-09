<?php

namespace App\Models;

use App\Enums\AppointmentSlot;
use App\Enums\ServiceAppointmentStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A request for a fitting, not a booking.
 *
 * The customer says which day and roughly when; the workshop answers. Everything the workshop
 * needs in order to answer is carried here — which car, which job, and, when the fitting is for
 * something just bought, which order.
 */
class ServiceAppointment extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'status' => ServiceAppointmentStatus::class,
            'preferred_slot' => AppointmentSlot::class,
            'preferred_date' => 'date',
            'responded_at' => 'datetime',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'token';
    }

    public function shop(): BelongsTo
    {
        return $this->belongsTo(ServiceShop::class, 'service_shop_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(CustomerVehicle::class, 'customer_vehicle_id');
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * What to call the car. The garage record is preferred because it is structured and current;
     * the typed label is what a visitor without an account gave us.
     */
    public function vehicleLabel(): string
    {
        if ($this->vehicle) {
            return trim(implode(' ', array_filter([
                $this->vehicle->make?->name,
                $this->vehicle->model?->name,
                $this->vehicle->year,
            ])));
        }

        return (string) ($this->vehicle_label ?: '—');
    }
}
