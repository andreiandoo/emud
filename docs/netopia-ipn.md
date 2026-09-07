# NETOPIA IPN verification

## What changed and why

IPN authenticity used to be decided by comparing the request's `Authorization` header against our own NETOPIA API key. That check authenticated nothing:

- the notification body was never covered, so any body could be posted with a correct header and an order marked paid;
- the API key is an **outbound request credential**, sent to NETOPIA on every payment start. It is far more exposed than a signing key, and it is not a secret NETOPIA uses to prove authorship;
- nothing prevented a captured request from being replayed.

Verification now requires a token signed by NETOPIA, checked against the POS public key, with the request body bound to the token through a payload hash claim. It **fails closed**: no configured public key means no notification is accepted.

## Configuration

Encrypted, on the provider's `credentials`:

```text
ipn_public_key    PEM public key from the NETOPIA merchant account
```

Non-secret, on the provider's `settings`:

```text
ipn_token_header        default "verification-token"
ipn_payload_hash_claim  default "sub"
ipn_payload_hash_algo   default "sha512"
ipn_max_age_seconds     default 300
```

## Confirm these before go-live

The cryptography here is not in question: the signature is verified with the POS public key over the exact bytes received, the algorithm is taken from an allowlist rather than from the token, and an unsigned or downgraded token is rejected.

What **must** be confirmed against the documentation of your specific merchant account, because it varies and has not been verified against a live POS:

1. the exact header name carrying the signed token;
2. the claim that carries the payload hash;
3. the hash algorithm used for that claim;
4. the signing algorithm NETOPIA uses, if it is not RS256 or RS512.

The defaults above are the configuration this integration ships with. If any of them is wrong for your account, verification fails closed and IPNs are rejected with 401 — the integration will not silently accept unverified notifications. Adjust the settings above rather than weakening the check.

## Verifying after configuration

1. Configure `ipn_public_key` and activate the provider in sandbox.
2. Trigger a sandbox payment and confirm the IPN is accepted (the payment transaction reaches a terminal status).
3. Replay the same request and confirm it is rejected once outside `ipn_max_age_seconds`.
4. Alter one byte of the body and confirm the notification is rejected with 401.

## Still outstanding

This covers authenticity of the notification. The following remain open in the handoff backlog and are not addressed here:

- deduplication keyed on more than `ntpID`, since one payment legitimately receives several status updates;
- mapping every NETOPIA status into a documented internal state;
- amount, currency, order and POS checks before an order is updated;
- status query/reconciliation for delayed or lost notifications.
