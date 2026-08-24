<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Order extends Model
{
    protected $guarded = [];
    protected function casts(): array { return ['metadata' => 'array', 'placed_at' => 'datetime']; }
    public function items(): HasMany { return $this->hasMany(OrderItem::class); }
}
