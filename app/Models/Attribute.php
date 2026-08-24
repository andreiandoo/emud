<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Attribute extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'validation_rules' => 'array',
            'is_global' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function options(): HasMany
    {
        return $this->hasMany(AttributeOption::class)->orderBy('position');
    }

    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(Category::class)->withPivot([
            'position', 'is_required', 'is_filterable', 'is_comparable', 'is_variant_defining',
        ]);
    }
}
