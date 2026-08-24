<?php

namespace App\Models;

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
}
