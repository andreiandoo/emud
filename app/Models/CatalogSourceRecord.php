<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CatalogSourceRecord extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'raw_payload' => 'array',
            'normalized_payload' => 'array',
            'deleted_at_source' => 'boolean',
            'source_updated_at' => 'datetime',
            'last_seen_at' => 'datetime',
        ];
    }

    public function source(): BelongsTo
    {
        return $this->belongsTo(CatalogSource::class, 'catalog_source_id');
    }

    public function importRun(): BelongsTo
    {
        return $this->belongsTo(CatalogImportRun::class, 'catalog_import_run_id');
    }

    public function supplierProduct(): BelongsTo
    {
        return $this->belongsTo(SupplierProduct::class);
    }

    public function supplierSyncRun(): BelongsTo
    {
        return $this->belongsTo(SupplierSyncRun::class);
    }

    public function supplierFeedArtifact(): BelongsTo
    {
        return $this->belongsTo(SupplierFeedArtifact::class);
    }

    public function assertions(): HasMany
    {
        return $this->hasMany(CatalogSourceAssertion::class, 'catalog_source_record_id');
    }
}
