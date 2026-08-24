<?php

namespace App\Enums;

enum StockStatus: string
{
    case InStock = 'in_stock';
    case LowStock = 'low_stock';
    case OutOfStock = 'out_of_stock';
    case Backorder = 'backorder';
    case Discontinued = 'discontinued';
    case Unknown = 'unknown';
}
