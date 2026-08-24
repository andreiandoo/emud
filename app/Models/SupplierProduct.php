<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class SupplierProduct extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['raw_payload' => 'array', 'last_seen_at' => 'datetime', 'discontinued_at' => 'datetime'];
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'variant_id');
    }

    public function offer(): HasOne
    {
        return $this->hasOne(SupplierOffer::class);
    }
}
