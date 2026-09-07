<?php

namespace Tests\Feature;

use App\Models\CatalogApiConsumer;
use App\Models\CatalogApiExternalIdentity;
use App\Models\CatalogApiKey;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class RapidApiAuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_valid_rapidapi_request_auto_provisions_and_reuses_consumer(): void
    {
        config([
            'catalog_api.rapidapi.proxy_secret' => 'provider-secret',
            'catalog_api.rapidapi.auto_provision' => true,
        ]);

        $headers = $this->rapidHeaders('rapid-user-123', 'PRO');

        $this->withHeaders($headers)
            ->getJson('/api/v1/coverage')
            ->assertOk()
            ->assertHeader('X-Catalog-Auth-Channel', 'rapidapi')
            ->assertHeader('X-RateLimit-Limit', '120')
            ->assertHeaderMissing('X-Catalog-Monthly-Quota');

        $identity = CatalogApiExternalIdentity::query()->where([
            'provider' => 'rapidapi',
            'external_user_id' => 'rapid-user-123',
        ])->firstOrFail();
        $consumerId = $identity->catalog_api_consumer_id;
        $this->assertSame('PRO', $identity->subscription);
        $this->assertSame('rapidapi_pro', $identity->consumer->plan);
        $this->assertSame(1, (int) $identity->consumer->requests_used);

        $this->withHeaders($headers)->getJson('/api/v1/coverage')->assertOk();

        $this->assertSame(1, CatalogApiExternalIdentity::query()->where('provider', 'rapidapi')->where('external_user_id', 'rapid-user-123')->count());
        $this->assertSame(2, (int) CatalogApiConsumer::query()->findOrFail($consumerId)->requests_used);
    }

    public function test_invalid_rapidapi_proxy_secret_cannot_fallback_to_valid_native_key(): void
    {
        config(['catalog_api.rapidapi.proxy_secret' => 'provider-secret']);
        $nativeToken = $this->nativeToken();

        $this->withHeaders([
            'X-API-Key' => $nativeToken,
            'X-RapidAPI-Proxy-Secret' => 'wrong-secret',
            'X-RapidAPI-User' => 'rapid-user-123',
            'X-RapidAPI-Subscription' => 'BASIC',
        ])->getJson('/api/v1/coverage')
            ->assertStatus(401)
            ->assertJsonPath('error.code', 'RAPIDAPI_PROXY_INVALID');
    }

    public function test_provider_headers_fail_closed_when_rapidapi_is_not_configured(): void
    {
        config(['catalog_api.rapidapi.proxy_secret' => null]);

        $this->withHeaders([
            'X-RapidAPI-User' => 'rapid-user-123',
            'X-RapidAPI-Subscription' => 'BASIC',
        ])->getJson('/api/v1/coverage')
            ->assertStatus(503)
            ->assertJsonPath('error.code', 'RAPIDAPI_NOT_CONFIGURED');
    }

    public function test_rapidapi_user_header_is_required_after_proxy_validation(): void
    {
        config(['catalog_api.rapidapi.proxy_secret' => 'provider-secret']);

        $this->withHeaders([
            'X-RapidAPI-Proxy-Secret' => 'provider-secret',
            'X-RapidAPI-Subscription' => 'BASIC',
        ])->getJson('/api/v1/coverage')
            ->assertStatus(401)
            ->assertJsonPath('error.code', 'RAPIDAPI_USER_REQUIRED');
    }

    public function test_unknown_rapidapi_user_is_rejected_when_auto_provisioning_is_disabled(): void
    {
        config([
            'catalog_api.rapidapi.proxy_secret' => 'provider-secret',
            'catalog_api.rapidapi.auto_provision' => false,
        ]);

        $this->withHeaders($this->rapidHeaders('not-provisioned', 'BASIC'))
            ->getJson('/api/v1/coverage')
            ->assertStatus(403)
            ->assertJsonPath('error.code', 'RAPIDAPI_USER_UNPROVISIONED');

        $this->assertDatabaseCount('catalog_api_external_identities', 0);
    }

    public function test_rapidapi_subscription_change_updates_plan_and_optional_local_quota(): void
    {
        config([
            'catalog_api.rapidapi.proxy_secret' => 'provider-secret',
            'catalog_api.rapidapi.plan_quotas.BASIC' => 10,
            'catalog_api.rapidapi.plan_quotas.PRO' => 25,
        ]);

        $this->withHeaders($this->rapidHeaders('plan-change-user', 'BASIC'))
            ->getJson('/api/v1/coverage')
            ->assertOk()
            ->assertHeader('X-RateLimit-Limit', '120')
            ->assertHeader('X-Catalog-Monthly-Quota', '10')
            ->assertHeader('X-Catalog-Monthly-Remaining', '9');

        $this->withHeaders($this->rapidHeaders('plan-change-user', 'PRO'))
            ->getJson('/api/v1/coverage')
            ->assertOk()
            ->assertHeader('X-RateLimit-Limit', '120')
            ->assertHeader('X-Catalog-Monthly-Quota', '25')
            ->assertHeader('X-Catalog-Monthly-Remaining', '23');

        $identity = CatalogApiExternalIdentity::query()->where('external_user_id', 'plan-change-user')->firstOrFail();
        $this->assertSame('PRO', $identity->subscription);
        $this->assertSame('rapidapi_pro', $identity->consumer->plan);
        $this->assertSame(25, (int) $identity->consumer->monthly_quota);
        $this->assertSame(2, (int) $identity->consumer->requests_used);
    }

    public function test_configured_rapidapi_host_is_validated(): void
    {
        config([
            'catalog_api.rapidapi.proxy_secret' => 'provider-secret',
            'catalog_api.rapidapi.expected_host' => 'automotive-catalog.p.rapidapi.com',
        ]);

        $headers = $this->rapidHeaders('rapid-user-123', 'BASIC');
        $headers['X-RapidAPI-Host'] = 'wrong-host.p.rapidapi.com';

        $this->withHeaders($headers)
            ->getJson('/api/v1/coverage')
            ->assertStatus(401)
            ->assertJsonPath('error.code', 'RAPIDAPI_HOST_INVALID');
    }

    public function test_local_rapidapi_quota_is_enforced_atomically(): void
    {
        config([
            'catalog_api.rapidapi.proxy_secret' => 'provider-secret',
            'catalog_api.rapidapi.plan_quotas.BASIC' => 1,
        ]);

        $headers = $this->rapidHeaders('quota-user', 'BASIC');
        $this->withHeaders($headers)
            ->getJson('/api/v1/coverage')
            ->assertOk()
            ->assertHeader('X-Catalog-Monthly-Quota', '1')
            ->assertHeader('X-Catalog-Monthly-Remaining', '0');

        $this->withHeaders($headers)
            ->getJson('/api/v1/coverage')
            ->assertStatus(429)
            ->assertJsonPath('error.code', 'QUOTA_EXCEEDED');

        $identity = CatalogApiExternalIdentity::query()->where('external_user_id', 'quota-user')->firstOrFail();
        $this->assertSame(1, (int) $identity->consumer->fresh()->requests_used);
    }

    public function test_rapidapi_consumer_key_header_alone_is_never_trusted_by_backend(): void
    {
        config(['catalog_api.rapidapi.proxy_secret' => 'provider-secret']);

        $this->withHeaders(['X-RapidAPI-Key' => 'developer-side-key'])
            ->getJson('/api/v1/coverage')
            ->assertStatus(401)
            ->assertJsonPath('error.code', 'API_KEY_REQUIRED');
    }

    public function test_native_key_authentication_still_works_and_uses_common_meter(): void
    {
        $token = $this->nativeToken(monthlyQuota: 2);

        $this->withHeader('X-API-Key', $token)
            ->getJson('/api/v1/coverage')
            ->assertOk()
            ->assertHeader('X-Catalog-Auth-Channel', 'native')
            ->assertHeader('X-RateLimit-Limit', '120')
            ->assertHeader('X-Catalog-Monthly-Quota', '2')
            ->assertHeader('X-Catalog-Monthly-Remaining', '1');

        $this->withHeader('X-API-Key', $token)->getJson('/api/v1/coverage')->assertOk();
        $this->withHeader('X-API-Key', $token)
            ->getJson('/api/v1/coverage')
            ->assertStatus(429)
            ->assertJsonPath('error.code', 'QUOTA_EXCEEDED');
    }

    private function rapidHeaders(string $user, string $subscription): array
    {
        return [
            'X-RapidAPI-Proxy-Secret' => 'provider-secret',
            'X-RapidAPI-User' => $user,
            'X-RapidAPI-Subscription' => $subscription,
            'X-RapidAPI-Version' => '1.2.3',
        ];
    }

    private function nativeToken(int $monthlyQuota = 100): string
    {
        $consumer = CatalogApiConsumer::query()->create([
            'public_id' => (string) Str::ulid(),
            'name' => 'Native API test consumer',
            'slug' => 'native-api-test-'.Str::lower(Str::random(8)),
            'plan' => 'test',
            'monthly_quota' => $monthlyQuota,
            'requests_used' => 0,
            'period_started_at' => now()->startOfMonth(),
            'is_active' => true,
        ]);

        return CatalogApiKey::issue($consumer, 'Test')['token'];
    }
}
