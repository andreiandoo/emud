<?php

namespace App\Models;

use App\Enums\CatalogImportStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CatalogImportRun extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'status' => CatalogImportStatus::class,
            'checkpoint' => 'array',
            'summary' => 'array',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    public function source(): BelongsTo
    {
        return $this->belongsTo(CatalogSource::class, 'catalog_source_id');
    }

    public function release(): BelongsTo
    {
        return $this->belongsTo(CatalogSourceRelease::class, 'catalog_source_release_id');
    }
}
