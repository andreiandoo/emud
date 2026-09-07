<?php

namespace App\Http\Middleware;

use App\Catalog\Api\CatalogApiUsageMeter;
use App\Catalog\Api\RapidApiCatalogAuthenticator;
use App\Models\CatalogApiKey;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateCatalogApiKey
{
    public function __construct(
        private readonly RapidApiCatalogAuthenticator $rapidApi,
        private readonly CatalogApiUsageMeter $usageMeter,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $apiKey = null;
        $externalIdentity = null;
        $authChannel = 'native';

        if ($this->rapidApi->isRapidApiRequest($request)) {
            $auth = $this->rapidApi->authenticate($request);
            if ($auth instanceof Response) {
                return $auth;
            }

            $consumer = $auth['consumer'];
            $externalIdentity = $auth['identity'];
            $authChannel = 'rapidapi';
        } else {
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
        }

        $consumer = $this->usageMeter->consume($consumer->id);
        if (! $consumer) {
            return response()->json(['error' => ['code' => 'QUOTA_EXCEEDED', 'message' => 'Monthly API quota exceeded.']], 429);
        }

        if ($apiKey && (! $apiKey->last_used_at || $apiKey->last_used_at->lt(now()->subMinutes(5)))) {
            $apiKey->update(['last_used_at' => now()]);
        }

        $request->attributes->set('catalog_api_key', $apiKey);
        $request->attributes->set('catalog_api_consumer', $consumer);
        $request->attributes->set('catalog_api_external_identity', $externalIdentity);
        $request->attributes->set('catalog_api_auth_channel', $authChannel);

        $response = $next($request);
        $response->headers->set('X-Catalog-Auth-Channel', $authChannel);

        if ($consumer->monthly_quota > 0) {
            $response->headers->set('X-RateLimit-Limit', (string) $consumer->monthly_quota);
            $response->headers->set('X-RateLimit-Remaining', (string) max(0, $consumer->monthly_quota - $consumer->requests_used));
        }

        return $response;
    }
}
