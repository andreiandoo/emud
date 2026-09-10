<?php

namespace App\Models;

use App\Enums\WorkshopRecordMatchStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkshopRecordMatch extends Model
{
    public const TARGET_WORKSHOP = 'workshop';

    public const TARGET_COMPANY = 'company';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'status' => WorkshopRecordMatchStatus::class,
            'score' => 'integer',
            'candidates' => 'array',
            'evidence' => 'array',
            'decided_at' => 'datetime',
        ];
    }

    public function sourceRecord(): BelongsTo
    {
        return $this->belongsTo(WorkshopSourceRecord::class, 'source_record_id');
    }

    public function target(): ?Model
    {
        if ($this->target_id === null) {
            return null;
        }

        return match ($this->target_type) {
            self::TARGET_WORKSHOP => Workshop::query()->find($this->target_id),
            self::TARGET_COMPANY => WorkshopCompany::query()->find($this->target_id),
            default => null,
        };
    }
}
