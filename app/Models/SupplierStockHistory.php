<?php

namespace App\Models;

use App\Enums\StockStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupplierStockHistory extends Model
{
    protected $table = 'supplier_stock_history';

    protected $guarded = [];

    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'stock_status' => StockStatus::class,
            'recorded_at' => 'datetime',
        ];
    }

    public function offer(): BelongsTo
    {
        return $this->belongsTo(SupplierOffer::class, 'supplier_offer_id');
    }
}
