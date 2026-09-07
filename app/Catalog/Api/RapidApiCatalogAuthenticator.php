<?php

namespace App\Catalog\Api;

use App\Models\CatalogApiConsumer;
use App\Models\CatalogApiExternalIdentity;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class RapidApiCatalogAuthenticator
{
    private const SUBSCRIPTIONS = ['BASIC', 'PRO', 'ULTRA', 'MEGA', 'CUSTOM'];

    public function isRapidApiRequest(Request $request): bool
    {
        return collect([
            'X-RapidAPI-Proxy-Secret',
            'X-RapidAPI-User',
            'X-RapidAPI-Subscription',
            'X-RapidAPI-Version',
        ])->contains(fn (string $header) => $request->headers->has($header));
    }

    /**
     * @return array{consumer:CatalogApiConsumer, identity:CatalogApiExternalIdentity}|Response
     */
    public function authenticate(Request $request): array|Response
    {
        $configuredSecret = (string) config('catalog_api.rapidapi.proxy_secret', '');
        if ($configuredSecret === '') {
            return response()->json([
                'error' => ['code' => 'RAPIDAPI_NOT_CONFIGURED', 'message' => 'RapidAPI provider authentication is not configured.'],
            ], 503);
        }

        $providedSecret = (string) $request->header('X-RapidAPI-Proxy-Secret', '');
        if ($providedSecret === '' || ! hash_equals($configuredSecret, $providedSecret)) {
            return response()->json([
                'error' => ['code' => 'RAPIDAPI_PROXY_INVALID', 'message' => 'RapidAPI proxy authentication failed.'],
            ], 401);
        }

        $externalUserId = trim((string) $request->header('X-RapidAPI-User', ''));
        if ($externalUserId === '') {
            return response()->json([
                'error' => ['code' => 'RAPIDAPI_USER_REQUIRED', 'message' => 'RapidAPI user identity is required.'],
            ], 401);
        }

        $expectedHost = trim((string) config('catalog_api.rapidapi.expected_host', ''));
        $providedHost = trim((string) $request->header('X-RapidAPI-Host', ''));
        if ($expectedHost !== '' && ($providedHost === '' || ! hash_equals(strtolower($expectedHost), strtolower($providedHost)))) {
            return response()->json([
                'error' => ['code' => 'RAPIDAPI_HOST_INVALID', 'message' => 'RapidAPI host validation failed.'],
            ], 401);
        }

        $rawSubscription = strtoupper(trim((string) $request->header('X-RapidAPI-Subscription', 'CUSTOM')));
        $subscription = in_array($rawSubscription, self::SUBSCRIPTIONS, true) ? $rawSubscription : 'CUSTOM';
        $quota = max(0, (int) config("catalog_api.rapidapi.plan_quotas.{$subscription}", 0));
        $autoProvision = (bool) config('catalog_api.rapidapi.auto_provision', true);

        return DB::transaction(function () use ($request, $externalUserId, $subscription, $rawSubscription, $quota, $autoProvision): array|Response {
            $identity = CatalogApiExternalIdentity::query()
                ->with('consumer')
                ->where('provider', 'rapidapi')
                ->where('external_user_id', $externalUserId)
                ->lockForUpdate()
                ->first();

            if (! $identity) {
                if (! $autoProvision) {
                    return response()->json([
                        'error' => ['code' => 'RAPIDAPI_USER_UNPROVISIONED', 'message' => 'This RapidAPI user has not been provisioned.'],
                    ], 403);
                }

                $consumer = CatalogApiConsumer::query()->create([
                    'public_id' => (string) Str::ulid(),
                    'name' => 'RapidAPI '.Str::limit($externalUserId, 80, ''),
                    'slug' => 'rapidapi-'.substr(hash('sha256', $externalUserId), 0, 24),
                    'plan' => 'rapidapi_'.strtolower($subscription),
                    'monthly_quota' => $quota,
                    'requests_used' => 0,
                    'period_started_at' => now()->startOfMonth(),
                    'is_active' => true,
                    'metadata' => ['auth_channel' => 'rapidapi'],
                ]);

                $identity = CatalogApiExternalIdentity::query()->create([
                    'catalog_api_consumer_id' => $consumer->id,
                    'provider' => 'rapidapi',
                    'external_user_id' => $externalUserId,
                ]);
            } else {
                $consumer = $identity->consumer;
            }

            if (! $consumer || ! $consumer->is_active) {
                return response()->json([
                    'error' => ['code' => 'API_CONSUMER_INACTIVE', 'message' => 'The API consumer is inactive.'],
                ], 403);
            }

            $identity->update([
                'subscription' => $subscription,
                'metadata' => [
                    'rapidapi_version' => $request->header('X-RapidAPI-Version'),
                    'rapidapi_host' => $request->header('X-RapidAPI-Host'),
                    'raw_subscription' => $rawSubscription,
                ],
                'last_seen_at' => now(),
            ]);

            $expectedPlan = 'rapidapi_'.strtolower($subscription);
            if ($consumer->plan !== $expectedPlan || (int) $consumer->monthly_quota !== $quota) {
                $consumer->update([
                    'plan' => $expectedPlan,
                    'monthly_quota' => $quota,
                ]);
                $consumer->refresh();
            }

            return ['consumer' => $consumer, 'identity' => $identity->fresh()];
        }, 3);
    }
}
