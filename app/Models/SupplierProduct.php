<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class SupplierProduct extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'raw_payload' => 'array',
            'technical_payload' => 'array',
            'catalog_mapping_reason' => 'array',
            'last_seen_at' => 'datetime',
            'discontinued_at' => 'datetime',
            'catalog_mapped_at' => 'datetime',
            'technical_promoted_at' => 'datetime',
        ];
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

    public function catalogPart(): BelongsTo
    {
        return $this->belongsTo(CatalogPart::class);
    }

    public function lastSupplierSyncRun(): BelongsTo
    {
        return $this->belongsTo(SupplierSyncRun::class, 'last_supplier_sync_run_id');
    }

    public function catalogCandidates(): HasMany
    {
        return $this->hasMany(SupplierProductMatchCandidate::class);
    }

    public function identifiers(): HasMany
    {
        return $this->hasMany(SupplierProductIdentifier::class);
    }
}
