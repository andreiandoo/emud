<?php

namespace App\Payments\Support;

/**
 * Minimal JWS verification for provider webhooks.
 *
 * The algorithm is chosen by the caller from an allowlist and never taken from the token, so a
 * forged header claiming "none" — or downgrading RS256 to HS256, which would let the public key
 * itself be used as the HMAC secret — cannot be accepted. Claims are only decoded once the
 * signature over the exact received segments has verified.
 */
final class JwtVerifier
{
    /** @var array<string, int> */
    private const ALGORITHMS = [
        'RS256' => OPENSSL_ALGO_SHA256,
        'RS384' => OPENSSL_ALGO_SHA384,
        'RS512' => OPENSSL_ALGO_SHA512,
    ];

    /**
     * @param  list<string>  $allowedAlgorithms
     * @return array<string, mixed>|null the claims, or null when the token is not authentic
     */
    public static function verify(string $token, string $publicKey, array $allowedAlgorithms = ['RS256', 'RS512']): ?array
    {
        $segments = explode('.', $token);

        if (count($segments) !== 3) {
            return null;
        }

        [$encodedHeader, $encodedPayload, $encodedSignature] = $segments;

        $header = self::decodeJson($encodedHeader);
        $signature = self::base64UrlDecode($encodedSignature);

        if ($header === null || $signature === '') {
            return null;
        }

        $algorithm = (string) ($header['alg'] ?? '');

        if (! in_array($algorithm, $allowedAlgorithms, true) || ! array_key_exists($algorithm, self::ALGORITHMS)) {
            return null;
        }

        $key = openssl_pkey_get_public($publicKey);

        if ($key === false) {
            return null;
        }

        $verified = openssl_verify(
            $encodedHeader.'.'.$encodedPayload,
            $signature,
            $key,
            self::ALGORITHMS[$algorithm],
        );

        return $verified === 1 ? self::decodeJson($encodedPayload) : null;
    }

    /** @return array<string, mixed>|null */
    private static function decodeJson(string $segment): ?array
    {
        $decoded = json_decode(self::base64UrlDecode($segment), true);

        return is_array($decoded) ? $decoded : null;
    }

    private static function base64UrlDecode(string $segment): string
    {
        $decoded = base64_decode(strtr($segment, '-_', '+/'), true);

        return $decoded === false ? '' : $decoded;
    }
}
