<?php

namespace App\Http\Middleware;

use App\Models\CatalogApiKey;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateCatalogApiKey
{
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->header('X-API-Key');
        if (! $token && str_starts_with((string) $request->header('Authorization'), 'Bearer ')) {
            $token = substr((string) $request->header('Authorization'), 7);
        }

        if (! $token) {
            return response()->json(['error' => ['code' => 'API_KEY_REQUIRED', 'message' => 'A valid API key is required.']], 401);
        }

        $apiKey = CatalogApiKey::query()
            ->with('consumer')
            ->where('key_hash', hash('sha256', $token))
            ->whereNull('revoked_at')
            ->where(fn ($query) => $query->whereNull('expires_at')->orWhere('expires_at', '>', now()))
            ->first();

        if (! $apiKey || ! $apiKey->consumer?->is_active) {
            return response()->json(['error' => ['code' => 'API_KEY_INVALID', 'message' => 'The API key is invalid or inactive.']], 401);
        }

        $consumer = $apiKey->consumer;
        if ($consumer->period_started_at === null || $consumer->period_started_at->lt(now()->startOfMonth())) {
            $consumer->update(['period_started_at' => now()->startOfMonth(), 'requests_used' => 0]);
            $consumer->refresh();
        }

        if ($consumer->monthly_quota > 0 && $consumer->requests_used >= $consumer->monthly_quota) {
            return response()->json(['error' => ['code' => 'QUOTA_EXCEEDED', 'message' => 'Monthly API quota exceeded.']], 429);
        }

        $consumer->increment('requests_used');
        if (! $apiKey->last_used_at || $apiKey->last_used_at->lt(now()->subMinutes(5))) {
            $apiKey->update(['last_used_at' => now()]);
        }

        $request->attributes->set('catalog_api_key', $apiKey);
        $request->attributes->set('catalog_api_consumer', $consumer);

        $response = $next($request);
        $response->headers->set('X-RateLimit-Limit', (string) $consumer->monthly_quota);
        $response->headers->set('X-RateLimit-Remaining', (string) max(0, $consumer->monthly_quota - $consumer->requests_used - 1));

        return $response;
    }
}
