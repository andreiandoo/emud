<?php

namespace App\Workshops\Ingestion;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Sleep;

/**
 * Keeps requests to one source at least a fixed interval apart, across every worker at once.
 *
 * The last request time lives in the cache and is read and written under a lock, so two county
 * jobs running in parallel still queue up behind each other instead of doubling the rate.
 */
class SourceThrottle
{
    public function wait(string $key, int $delayMs): void
    {
        if ($delayMs <= 0) {
            return;
        }

        Cache::lock("workshops:throttle:{$key}:lock", 120)->block(300, function () use ($key, $delayMs): void {
            $last = (float) Cache::get("workshops:throttle:{$key}:last", 0);
            $remaining = $last + $delayMs / 1000 - microtime(true);

            if ($remaining > 0) {
                Sleep::usleep((int) round($remaining * 1_000_000));
            }

            Cache::put("workshops:throttle:{$key}:last", microtime(true), 3600);
        });
    }
}
