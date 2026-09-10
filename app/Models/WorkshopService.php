<?php

namespace App\Models;

use App\Enums\WorkshopEvidenceType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkshopService extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'evidence_type' => WorkshopEvidenceType::class,
            'evidence' => 'array',
            'is_authorized' => 'boolean',
            'confidence_score' => 'integer',
        ];
    }

    public function workshop(): BelongsTo
    {
        return $this->belongsTo(Workshop::class);
    }

    public function serviceType(): BelongsTo
    {
        return $this->belongsTo(WorkshopServiceType::class, 'service_type_id');
    }

    public function sourceRecord(): BelongsTo
    {
        return $this->belongsTo(WorkshopSourceRecord::class, 'source_record_id');
    }
}
