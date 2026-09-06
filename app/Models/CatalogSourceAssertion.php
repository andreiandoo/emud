<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CatalogSourceAssertion extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'raw_value' => 'array',
            'normalized_value' => 'array',
            'api_redistributable' => 'boolean',
            'ecommerce_displayable' => 'boolean',
            'effective_from' => 'date',
            'effective_to' => 'date',
        ];
    }
}
