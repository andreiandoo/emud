<?php

namespace App\Catalog\Api;

use App\Models\CatalogApiConsumer;
use Illuminate\Support\Facades\DB;

class CatalogApiUsageMeter
{
    public function consume(int $consumerId): ?CatalogApiConsumer
    {
        return DB::transaction(function () use ($consumerId): ?CatalogApiConsumer {
            $consumer = CatalogApiConsumer::query()->lockForUpdate()->findOrFail($consumerId);

            if ($consumer->period_started_at === null || $consumer->period_started_at->lt(now()->startOfMonth())) {
                $consumer->period_started_at = now()->startOfMonth();
                $consumer->requests_used = 0;
            }

            if ($consumer->monthly_quota > 0 && $consumer->requests_used >= $consumer->monthly_quota) {
                $consumer->save();

                return null;
            }

            $consumer->requests_used++;
            $consumer->save();

            return $consumer->fresh();
        }, 3);
    }
}
