<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupplierFeedArtifact extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'source_modified_at' => 'datetime',
            'retrieved_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function syncRun(): BelongsTo
    {
        return $this->belongsTo(SupplierSyncRun::class, 'supplier_sync_run_id');
    }
}
