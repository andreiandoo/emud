<?php

namespace App\Models;

use App\Enums\PricingScope;
use Illuminate\Database\Eloquent\Model;

class PricingRule extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'scope_type' => PricingScope::class,
            'target_gross_margin_percent' => 'decimal:2',
            'minimum_contribution_percent' => 'decimal:2',
            'max_auto_change_percent' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }
}
