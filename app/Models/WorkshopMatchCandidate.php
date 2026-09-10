<?php

namespace App\Models;

use App\Enums\WorkshopMatchStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkshopMatchCandidate extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'score' => 'integer',
            'evidence' => 'array',
            'status' => WorkshopMatchStatus::class,
            'reviewed_at' => 'datetime',
        ];
    }

    public function workshopA(): BelongsTo
    {
        return $this->belongsTo(Workshop::class, 'workshop_a_id');
    }

    public function workshopB(): BelongsTo
    {
        return $this->belongsTo(Workshop::class, 'workshop_b_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
