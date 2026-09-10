<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class WorkshopSourceRecord extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_PARSED = 'parsed';

    public const STATUS_FAILED = 'failed';

    public const STATUS_SKIPPED = 'skipped';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'is_current' => 'boolean',
            'http_status' => 'integer',
            'first_seen_at' => 'datetime',
            'last_seen_at' => 'datetime',
            'fetched_at' => 'datetime',
            'content_changed_at' => 'datetime',
            'parsed_at' => 'datetime',
        ];
    }

    public function dataSource(): BelongsTo
    {
        return $this->belongsTo(WorkshopDataSource::class, 'data_source_id');
    }

    public function importRun(): BelongsTo
    {
        return $this->belongsTo(WorkshopImportRun::class, 'import_run_id');
    }

    public function link(): HasOne
    {
        return $this->hasOne(WorkshopSourceLink::class, 'source_record_id');
    }

    public function authorization(): HasOne
    {
        return $this->hasOne(WorkshopAuthorization::class, 'source_record_id');
    }

    public function matches(): HasMany
    {
        return $this->hasMany(WorkshopRecordMatch::class, 'source_record_id');
    }

    public function scopeCurrent(Builder $query): Builder
    {
        return $query->where('is_current', true);
    }

    public function markParsed(): void
    {
        $this->forceFill(['parse_status' => self::STATUS_PARSED, 'parse_error' => null, 'parsed_at' => now()])->save();
    }

    public function markFailed(string $error): void
    {
        $this->forceFill(['parse_status' => self::STATUS_FAILED, 'parse_error' => mb_substr($error, 0, 4000), 'parsed_at' => now()])->save();
    }

    public function markSkipped(string $reason): void
    {
        $this->forceFill(['parse_status' => self::STATUS_SKIPPED, 'parse_error' => mb_substr($reason, 0, 4000), 'parsed_at' => now()])->save();
    }
}
