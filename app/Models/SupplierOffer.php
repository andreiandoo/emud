<?php

namespace App\Models;

use App\Enums\OfferSourceType;
use App\Enums\ShippingClass;
use App\Enums\StockStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SupplierOffer extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'stock_status' => StockStatus::class,
            'shipping_class' => ShippingClass::class,
            'source_type' => OfferSourceType::class,
            'cost_price' => 'decimal:4',
            'cost_gross' => 'decimal:4',
            'base_cost_net' => 'decimal:4',
            'fx_rate' => 'decimal:8',
            'recommended_retail_price' => 'decimal:2',
            'map_price' => 'decimal:2',
            'msrp' => 'decimal:2',
            'dropship_fee' => 'decimal:2',
            'handling_fee' => 'decimal:2',
            'shipping_cost_estimate' => 'decimal:2',
            'weight_kg' => 'decimal:3',
            'packed_weight_kg' => 'decimal:3',
            'oversize_flag' => 'boolean',
            'hazmat_flag' => 'boolean',
            'is_dropship_eligible' => 'boolean',
            'is_active' => 'boolean',
            'price_synced_at' => 'datetime',
            'stock_synced_at' => 'datetime',
            'stale_after' => 'datetime',
            'fx_rate_at' => 'datetime',
            'source_updated_at' => 'datetime',
            'last_verified_at' => 'datetime',
        ];
    }

    public function supplierProduct(): BelongsTo
    {
        return $this->belongsTo(SupplierProduct::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(SupplierWarehouse::class, 'supplier_warehouse_id');
    }

    public function stockHistory(): HasMany
    {
        return $this->hasMany(SupplierStockHistory::class);
    }

    public function isSellable(): bool
    {
        return $this->is_active
            && in_array($this->stock_status, [StockStatus::InStock, StockStatus::LowStock, StockStatus::Backorder], true)
            && ($this->stale_after === null || $this->stale_after->isFuture());
    }

    /**
     * Whether this particular article carries an article-level dropship exclusion.
     *
     * Feeds rarely flag this per SKU, so null means "no known restriction on this
     * article" rather than "unknown supplier". Whether the supplier relationship
     * permits dropshipping at all is a routing decision, not an offer attribute.
     */
    public function hasArticleLevelDropshipBlock(): bool
    {
        return $this->is_dropship_eligible === false;
    }
}
