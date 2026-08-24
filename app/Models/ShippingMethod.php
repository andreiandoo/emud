<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ShippingMethod extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['rules' => 'array', 'is_active' => 'boolean'];
    }

    public function provider(): BelongsTo
    {
        return $this->belongsTo(ShippingProvider::class, 'shipping_provider_id');
    }

    public function priceFor(float $subtotal): float
    {
        return $this->free_over !== null && $subtotal >= (float) $this->free_over
            ? 0.0
            : (float) $this->base_price;
    }
}
