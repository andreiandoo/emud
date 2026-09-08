<?php

namespace App\Models;

use App\Storefront\Compatibility\CompatibilityVerdict;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WishlistItem extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['verdict_when_saved' => CompatibilityVerdict::class];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'variant_id');
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(CustomerVehicle::class, 'customer_vehicle_id');
    }

    public function scopeForVehicle(Builder $query, ?int $vehicleId): Builder
    {
        return $vehicleId === null
            ? $query->whereNull('customer_vehicle_id')
            : $query->where('customer_vehicle_id', $vehicleId);
    }
}
