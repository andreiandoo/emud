<?php

namespace App\Models;

use App\Enums\SyncStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SupplierSyncRun extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'status' => SyncStatus::class,
            'checkpoint' => 'array',
            'summary' => 'array',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function errors(): HasMany
    {
        return $this->hasMany(SupplierSyncError::class);
    }

    /** Share of received rows that could not be imported. */
    public function errorRate(): float
    {
        $received = (int) $this->received_count;

        return $received > 0 ? ((int) $this->failed_count + (int) $this->rejected_count) / $received : 0.0;
    }
}
