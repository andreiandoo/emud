<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One authorisation document as RAR publishes it. A renewal is a new document with a new exit
 * number; the previous one stays here with is_current = false.
 */
class WorkshopAuthorization extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'valid_from' => 'date',
            'valid_until' => 'date',
            'initially_authorized_at' => 'date',
            'revision_date' => 'date',
            'revision_number' => 'integer',
            'is_current' => 'boolean',
            'raw_data' => 'array',
        ];
    }

    public function workshop(): BelongsTo
    {
        return $this->belongsTo(Workshop::class);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(WorkshopCompany::class, 'company_id');
    }

    public function sourceRecord(): BelongsTo
    {
        return $this->belongsTo(WorkshopSourceRecord::class, 'source_record_id');
    }

    public function activities(): HasMany
    {
        return $this->hasMany(WorkshopAuthorizationActivity::class)->orderBy('code');
    }
}
