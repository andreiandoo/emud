<?php

namespace App\Models;

use App\Workshops\Ingestion\DataSourceCatalog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WorkshopDataSource extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'is_enabled' => 'boolean',
            'is_public_output_allowed' => 'boolean',
            'configuration' => 'array',
            'state' => 'array',
            'last_started_at' => 'datetime',
            'last_completed_at' => 'datetime',
        ];
    }

    /**
     * The row for a source, created from its definition the first time anything needs it.
     * Unlike a seeder, this never touches a row that exists, so an operator who disables a
     * source or clears it for publication keeps that decision across deploys.
     */
    public static function forKey(string $key): self
    {
        return static::query()->firstOrCreate(['key' => $key], DataSourceCatalog::definition($key));
    }

    public function records(): HasMany
    {
        return $this->hasMany(WorkshopSourceRecord::class, 'data_source_id');
    }

    public function runs(): HasMany
    {
        return $this->hasMany(WorkshopImportRun::class, 'data_source_id');
    }

    public function stateValue(string $key, mixed $default = null): mixed
    {
        return data_get($this->state ?? [], $key, $default);
    }

    public function putState(string $key, mixed $value): void
    {
        $state = $this->state ?? [];
        data_set($state, $key, $value);
        $this->update(['state' => $state]);
    }
}
