<?php

namespace Tests\Feature;

use App\Models\PaymentProvider;
use App\Payments\Gateways\NetopiaGateway;
use App\Payments\Gateways\StripeGateway;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaymentWebhookVerificationTest extends TestCase
{
    use RefreshDatabase;

    private string $privateKey;

    private string $publicKey;

    protected function setUp(): void
    {
        parent::setUp();

        $key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);

        // openssl_pkey_export() writes through a reference, and a typed property cannot be
        // passed by reference before it holds a value.
        openssl_pkey_export($key, $privateKey);

        $this->privateKey = (string) $privateKey;
        $this->publicKey = openssl_pkey_get_details($key)['key'];
    }

    // ---------------------------------------------------------------- NETOPIA

    public function test_netopia_accepts_a_signed_notification_bound_to_the_body(): void
    {
        $payload = '{"payment":{"ntpID":"123","status":3}}';
        $token = $this->jwt(['iat' => time(), 'sub' => hash('sha512', $payload)]);

        $this->assertTrue(
            (new NetopiaGateway)->verifyWebhook($this->netopia(), $payload, ['verification-token' => [$token]])
        );
    }

    /**
     * The defect this replaces: the API key is sent to NETOPIA on every payment start, so
     * treating it as proof of authenticity let anyone holding it mark an order paid.
     */
    public function test_netopia_rejects_a_notification_authenticated_only_by_the_api_key(): void
    {
        $this->assertFalse(
            (new NetopiaGateway)->verifyWebhook(
                $this->netopia(),
                '{"payment":{"ntpID":"123","status":3}}',
                ['authorization' => ['secret-api-key']],
            )
        );
    }

    public function test_netopia_rejects_a_token_signed_by_another_key(): void
    {
        $payload = '{"payment":{"ntpID":"123","status":3}}';
        $other = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
        openssl_pkey_export($other, $otherPrivate);

        $token = $this->jwt(['iat' => time(), 'sub' => hash('sha512', $payload)], $otherPrivate);

        $this->assertFalse(
            (new NetopiaGateway)->verifyWebhook($this->netopia(), $payload, ['verification-token' => [$token]])
        );
    }

    /**
     * A genuine token replayed against a different body would otherwise confirm a payment the
     * provider never reported.
     */
    public function test_netopia_rejects_a_valid_token_paired_with_a_different_body(): void
    {
        $token = $this->jwt(['iat' => time(), 'sub' => hash('sha512', '{"payment":{"status":5}}')]);

        $this->assertFalse(
            (new NetopiaGateway)->verifyWebhook($this->netopia(), '{"payment":{"status":3}}', ['verification-token' => [$token]])
        );
    }

    public function test_netopia_rejects_an_unsigned_token(): void
    {
        $payload = '{"payment":{"status":3}}';
        $unsigned = $this->segment(['alg' => 'none', 'typ' => 'JWT'])
            .'.'.$this->segment(['iat' => time(), 'sub' => hash('sha512', $payload)])
            .'.';

        $this->assertFalse(
            (new NetopiaGateway)->verifyWebhook($this->netopia(), $payload, ['verification-token' => [$unsigned]])
        );
    }

    public function test_netopia_rejects_a_stale_token(): void
    {
        $payload = '{"payment":{"status":3}}';
        $token = $this->jwt(['iat' => time() - 3600, 'sub' => hash('sha512', $payload)]);

        $this->assertFalse(
            (new NetopiaGateway)->verifyWebhook($this->netopia(), $payload, ['verification-token' => [$token]])
        );
    }

    public function test_netopia_fails_closed_without_a_configured_public_key(): void
    {
        $provider = $this->netopia();
        $provider->update(['credentials' => ['api_key' => 'k']]);
        $payload = '{"payment":{"status":3}}';

        $this->assertFalse(
            (new NetopiaGateway)->verifyWebhook($provider, $payload, ['verification-token' => [$this->jwt(['iat' => time(), 'sub' => hash('sha512', $payload)])]])
        );
    }

    // ----------------------------------------------------------------- Stripe

    public function test_stripe_accepts_a_signature_from_a_rotated_secret_listed_after_others(): void
    {
        $provider = $this->stripe(['whsec_old', 'whsec_new']);
        $payload = '{"type":"payment_intent.succeeded"}';
        $timestamp = time();

        // Stripe puts every valid signature in the header; the one that verifies is not
        // necessarily the last, which is exactly what the previous parser assumed.
        $header = "t={$timestamp}"
            .',v1='.hash_hmac('sha256', "{$timestamp}.{$payload}", 'whsec_new')
            .',v1=0000000000000000000000000000000000000000000000000000000000000000';

        $this->assertTrue((new StripeGateway)->verifyWebhook($provider, $payload, ['stripe-signature' => [$header]]));
    }

    public function test_stripe_accepts_either_secret_during_rotation(): void
    {
        $provider = $this->stripe(['whsec_old', 'whsec_new']);
        $payload = '{"type":"payment_intent.succeeded"}';
        $timestamp = time();

        foreach (['whsec_old', 'whsec_new'] as $secret) {
            $header = "t={$timestamp},v1=".hash_hmac('sha256', "{$timestamp}.{$payload}", $secret);

            $this->assertTrue((new StripeGateway)->verifyWebhook($provider, $payload, ['stripe-signature' => [$header]]));
        }
    }

    public function test_stripe_rejects_a_forged_signature(): void
    {
        $provider = $this->stripe(['whsec_new']);
        $payload = '{"type":"payment_intent.succeeded"}';
        $timestamp = time();
        $header = "t={$timestamp},v1=".hash_hmac('sha256', "{$timestamp}.{$payload}", 'attacker');

        $this->assertFalse((new StripeGateway)->verifyWebhook($provider, $payload, ['stripe-signature' => [$header]]));
    }

    public function test_stripe_rejects_a_replayed_signature(): void
    {
        $provider = $this->stripe(['whsec_new']);
        $payload = '{"type":"payment_intent.succeeded"}';
        $timestamp = time() - 3600;
        $header = "t={$timestamp},v1=".hash_hmac('sha256', "{$timestamp}.{$payload}", 'whsec_new');

        $this->assertFalse((new StripeGateway)->verifyWebhook($provider, $payload, ['stripe-signature' => [$header]]));
    }

    // ----------------------------------------------------------------- helpers

    /** @param array<string, mixed> $claims */
    private function jwt(array $claims, ?string $privateKey = null): string
    {
        $signing = $this->segment(['alg' => 'RS256', 'typ' => 'JWT']).'.'.$this->segment($claims);
        openssl_sign($signing, $signature, $privateKey ?? $this->privateKey, OPENSSL_ALGO_SHA256);

        return $signing.'.'.$this->base64Url($signature);
    }

    /** @param array<string, mixed> $data */
    private function segment(array $data): string
    {
        return $this->base64Url(json_encode($data, JSON_THROW_ON_ERROR));
    }

    private function base64Url(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    private function netopia(): PaymentProvider
    {
        return PaymentProvider::create([
            'code' => 'netopia',
            'name' => 'NETOPIA',
            'driver' => 'netopia',
            'is_active' => true,
            'credentials' => ['api_key' => 'secret-api-key', 'ipn_public_key' => $this->publicKey],
            'settings' => [],
        ]);
    }

    /** @param list<string> $secrets */
    private function stripe(array $secrets): PaymentProvider
    {
        return PaymentProvider::create([
            'code' => 'stripe',
            'name' => 'Stripe',
            'driver' => 'stripe',
            'is_active' => true,
            'credentials' => ['secret_key' => 'sk_test', 'webhook_secrets' => $secrets],
        ]);
    }
}
