<?php

namespace App\Models;

use App\Support\Money;
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

    /**
     * The free-shipping threshold is compared as an exact amount. Compared as floats, a subtotal
     * that is exactly the threshold could land a fraction of a ban below it and quietly charge
     * a customer who had earned free delivery.
     */
    public function priceFor(Money $subtotal): Money
    {
        $threshold = $this->free_over === null ? null : Money::of($this->free_over, $subtotal->currency);

        return $threshold !== null && $subtotal->isGreaterThanOrEqualTo($threshold)
            ? Money::zero($subtotal->currency)
            : Money::of($this->base_price, $subtotal->currency);
    }
}
