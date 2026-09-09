<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ServiceShopHour extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['is_closed' => 'boolean'];
    }

    public function shop(): BelongsTo
    {
        return $this->belongsTo(ServiceShop::class, 'service_shop_id');
    }
}
