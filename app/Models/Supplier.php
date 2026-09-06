<?php

namespace App\Models;

use App\Enums\CatalogRightsClass;
use App\Enums\SupplierProtocol;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Supplier extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'protocol' => SupplierProtocol::class,
            'credentials' => 'encrypted:array',
            'field_mapping' => 'array',
            'settings' => 'array',
            'data_rights_class' => CatalogRightsClass::class,
            'allow_internal_data' => 'boolean',
            'allow_ecommerce_data' => 'boolean',
            'allow_derived_data' => 'boolean',
            'allow_api_redistribution' => 'boolean',
            'attribution_required' => 'boolean',
            'is_active' => 'boolean',
            'last_successful_sync_at' => 'datetime',
        ];
    }

    public function products(): HasMany
    {
        return $this->hasMany(SupplierProduct::class);
    }

    public function syncRuns(): HasMany
    {
        return $this->hasMany(SupplierSyncRun::class);
    }

    public function syncSchedules(): HasMany
    {
        return $this->hasMany(SupplierSyncSchedule::class);
    }

    public function feedArtifacts(): HasMany
    {
        return $this->hasMany(SupplierFeedArtifact::class);
    }
}
