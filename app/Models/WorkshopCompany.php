<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A legal entity. Not a place: the registered office is where the paperwork lives, and nothing
 * here says a car is repaired there.
 */
class WorkshopCompany extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'is_vat_payer' => 'boolean',
            'has_automotive_caen' => 'boolean',
            'caen_codes' => 'array',
            'registered_latitude' => 'float',
            'registered_longitude' => 'float',
            'source_confidence' => 'integer',
            'onrc_verified_at' => 'datetime',
        ];
    }

    public function workshops(): HasMany
    {
        return $this->hasMany(Workshop::class, 'company_id');
    }
}
