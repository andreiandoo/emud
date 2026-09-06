<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupplierSyncSchedule extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'is_enabled' => 'boolean',
            'last_dispatched_at' => 'datetime',
        ];
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }
}
