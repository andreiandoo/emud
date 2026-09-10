<?php

namespace App\Models;

use App\Enums\PriceChangeStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PriceChange extends Model
{
    protected $guarded = [];

    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'status' => PriceChangeStatus::class,
            'old_price' => 'decimal:2',
            'new_price' => 'decimal:2',
            'decision' => 'array',
            'decided_at' => 'datetime',
            'created_at' => 'datetime',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'variant_id');
    }

    public function decidedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by');
    }

    /** Signed percentage move, or null when there was no earlier price to compare with. */
    public function changePercent(): ?float
    {
        $old = $this->old_price === null ? null : (float) $this->old_price;

        return $old === null || $old <= 0 ? null : round(((float) $this->new_price - $old) / $old * 100, 1);
    }
}
