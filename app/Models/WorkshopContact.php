<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkshopContact extends Model
{
    public const TYPES = ['phone', 'mobile', 'email', 'website', 'facebook', 'instagram', 'whatsapp', 'other'];

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'confidence_score' => 'integer',
            'is_primary' => 'boolean',
            'is_verified' => 'boolean',
            'first_seen_at' => 'datetime',
            'last_seen_at' => 'datetime',
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
