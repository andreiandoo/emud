<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkshopCapability extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'value' => 'boolean',
            'score' => 'integer',
            'evidence' => 'array',
        ];
    }

    public function workshop(): BelongsTo
    {
        return $this->belongsTo(Workshop::class);
    }
}
