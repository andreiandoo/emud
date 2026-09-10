<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkshopWebsiteCandidate extends Model
{
    public const PENDING = 'pending';

    public const ACCEPTED = 'accepted';

    public const REJECTED = 'rejected';

    public const UNREACHABLE = 'unreachable';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'confidence' => 'integer',
            'evidence' => 'array',
            'validated_at' => 'datetime',
            'crawled_at' => 'datetime',
        ];
    }

    public function workshop(): BelongsTo
    {
        return $this->belongsTo(Workshop::class);
    }
}
