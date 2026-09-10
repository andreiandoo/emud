<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkshopSourceLink extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'match_confidence' => 'integer',
            'evidence' => 'array',
        ];
    }

    public function workshop(): BelongsTo
    {
        return $this->belongsTo(Workshop::class);
    }

    public function dataSource(): BelongsTo
    {
        return $this->belongsTo(WorkshopDataSource::class, 'data_source_id');
    }

    public function sourceRecord(): BelongsTo
    {
        return $this->belongsTo(WorkshopSourceRecord::class, 'source_record_id');
    }
}
