<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProductVariant extends Model
{
    use SoftDeletes;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'retail_price' => 'decimal:2',
            'compare_at_price' => 'decimal:2',
            'dimensions_cm' => 'array',
            'is_active' => 'boolean',
        ];
    }

    public function product(): BelongsTo { return $this->belongsTo(Product::class); }
    public function supplierProducts(): HasMany { return $this->hasMany(SupplierProduct::class, 'variant_id'); }
}
