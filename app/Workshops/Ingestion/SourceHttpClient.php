<?php

namespace App\Workshops\Ingestion;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * The HTTP client every workshop source uses: identified by our user agent, bounded by a timeout,
 * and retried with exponential backoff only for failures worth retrying (a dropped connection, a
 * 429, a 5xx). A 404 or a 403 is an answer, and asking again would only add load.
 */
class SourceHttpClient
{
    public function request(int $timeoutSeconds, int $maxRetries, int $baseDelayMs): PendingRequest
    {
        return Http::withUserAgent((string) config('workshops.user_agent'))
            ->timeout(max(1, $timeoutSeconds))
            ->connectTimeout(min(30, max(1, $timeoutSeconds)))
            ->retry(
                max(1, $maxRetries + 1),
                fn (int $attempt): int => max(0, $baseDelayMs) * (2 ** max(0, $attempt - 1)),
                fn (Throwable $exception): bool => self::isTransient($exception),
            );
    }

    public static function isTransient(Throwable $exception): bool
    {
        if ($exception instanceof ConnectionException) {
            return true;
        }

        if ($exception instanceof RequestException) {
            $status = $exception->response->status();

            return $status === 429 || $status >= 500;
        }

        return false;
    }
}
