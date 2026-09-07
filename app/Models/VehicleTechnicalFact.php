<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VehicleTechnicalFact extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'value_numeric' => 'decimal:6',
            'value_json' => 'array',
            'confidence' => 'decimal:2',
        ];
    }

    public function configuration(): BelongsTo
    {
        return $this->belongsTo(VehicleConfiguration::class, 'configuration_id');
    }

    public function source(): BelongsTo
    {
        return $this->belongsTo(CatalogSource::class, 'catalog_source_id');
    }
}
