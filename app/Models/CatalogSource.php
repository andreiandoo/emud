<?php

namespace App\Models;

use App\Enums\CatalogRightsClass;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CatalogSource extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'credentials' => 'encrypted:array',
            'settings' => 'array',
            'field_mapping' => 'array',
            'rights_class' => CatalogRightsClass::class,
            'territories' => 'array',
            'capabilities' => 'array',
            'allow_internal' => 'boolean',
            'allow_ecommerce' => 'boolean',
            'allow_derived' => 'boolean',
            'allow_api_redistribution' => 'boolean',
            'allow_bulk_export' => 'boolean',
            'allow_media_redistribution' => 'boolean',
            'attribution_required' => 'boolean',
            'is_active' => 'boolean',
            'last_successful_sync_at' => 'datetime',
            'last_attempted_sync_at' => 'datetime',
        ];
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function schedules(): HasMany
    {
        return $this->hasMany(CatalogSourceSchedule::class);
    }

    public function releases(): HasMany
    {
        return $this->hasMany(CatalogSourceRelease::class);
    }

    public function importRuns(): HasMany
    {
        return $this->hasMany(CatalogImportRun::class);
    }

    public function records(): HasMany
    {
        return $this->hasMany(CatalogSourceRecord::class);
    }
}
