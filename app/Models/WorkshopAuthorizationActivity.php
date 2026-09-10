<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkshopAuthorizationActivity extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'depth' => 'integer',
            'vehicle_categories' => 'array',
            'restrictions' => 'array',
            'limitations' => 'array',
            'observations' => 'array',
            'raw_data' => 'array',
        ];
    }

    public function authorization(): BelongsTo
    {
        return $this->belongsTo(WorkshopAuthorization::class, 'workshop_authorization_id');
    }
}
