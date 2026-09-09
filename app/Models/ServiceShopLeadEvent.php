<?php

namespace App\Models;

use App\Enums\ServiceLeadEventType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One billable moment, with nothing identifying the visitor who caused it.
 */
class ServiceShopLeadEvent extends Model
{
    public const UPDATED_AT = null;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['type' => ServiceLeadEventType::class, 'created_at' => 'datetime'];
    }

    public function shop(): BelongsTo
    {
        return $this->belongsTo(ServiceShop::class, 'service_shop_id');
    }
}
