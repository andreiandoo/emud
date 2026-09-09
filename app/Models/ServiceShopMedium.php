<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ServiceShopMedium extends Model
{
    protected $table = 'service_shop_media';

    protected $guarded = [];

    public function shop(): BelongsTo
    {
        return $this->belongsTo(ServiceShop::class, 'service_shop_id');
    }
}
