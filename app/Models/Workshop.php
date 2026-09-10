<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A physical place where vehicles are worked on, built from one or more source records.
 *
 * Every value here was derived from a source record that is still stored and linked through
 * workshop_source_links, and the per-field confidence says how far to trust it. A workshop that
 * turns out to be a duplicate is never deleted: it points at the one it was merged into.
 */
class Workshop extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'latitude' => 'float',
            'longitude' => 'float',
            'coordinates_confidence' => 'integer',
            'is_active' => 'boolean',
            'is_rar_authorized' => 'boolean',
            'is_itp' => 'boolean',
            'is_gpl_gnc' => 'boolean',
            'is_tlv' => 'boolean',
            'is_modification_authorized' => 'boolean',
            'is_dismantling' => 'boolean',
            'is_mobile' => 'boolean',
            'supports_4x4' => 'boolean',
            'supports_ev' => 'boolean',
            'supports_hybrid' => 'boolean',
            'supports_trucks' => 'boolean',
            'offroad_score' => 'integer',
            'workstations' => 'integer',
            'employees' => 'integer',
            'confidence_score' => 'integer',
            'website_checked_at' => 'datetime',
            'first_seen_at' => 'datetime',
            'last_seen_at' => 'datetime',
            'last_verified_at' => 'datetime',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(WorkshopCompany::class, 'company_id');
    }

    public function contacts(): HasMany
    {
        return $this->hasMany(WorkshopContact::class);
    }

    public function authorizations(): HasMany
    {
        return $this->hasMany(WorkshopAuthorization::class);
    }

    public function services(): HasMany
    {
        return $this->hasMany(WorkshopService::class);
    }

    public function serviceTypes(): BelongsToMany
    {
        return $this->belongsToMany(WorkshopServiceType::class, 'workshop_services', 'workshop_id', 'service_type_id')
            ->withPivot(['evidence_type', 'is_authorized', 'confidence_score'])
            ->withTimestamps();
    }

    public function capabilities(): HasMany
    {
        return $this->hasMany(WorkshopCapability::class);
    }

    public function sourceLinks(): HasMany
    {
        return $this->hasMany(WorkshopSourceLink::class);
    }

    public function websiteCandidates(): HasMany
    {
        return $this->hasMany(WorkshopWebsiteCandidate::class);
    }

    public function mergedInto(): BelongsTo
    {
        return $this->belongsTo(self::class, 'merged_into_id');
    }

    /** Workshops that are not duplicates folded into another one. */
    public function scopeCanonical(Builder $query): Builder
    {
        return $query->whereNull('merged_into_id');
    }

    public function hasCoordinates(): bool
    {
        return $this->latitude !== null && $this->longitude !== null;
    }

    public function primaryContact(string ...$types): ?WorkshopContact
    {
        return $this->contacts
            ->whereIn('type', $types)
            ->sortByDesc(fn (WorkshopContact $contact): array => [$contact->is_primary, $contact->confidence_score])
            ->first();
    }
}
