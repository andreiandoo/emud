<?php

namespace App\Models;

use App\Enums\StockStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupplierOffer extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'stock_status' => StockStatus::class,
            'cost_price' => 'decimal:4',
            'recommended_retail_price' => 'decimal:2',
            'price_synced_at' => 'datetime',
            'stock_synced_at' => 'datetime',
            'stale_after' => 'datetime',
        ];
    }

    public function supplierProduct(): BelongsTo
    {
        return $this->belongsTo(SupplierProduct::class);
    }
}
