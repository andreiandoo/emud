# Integrări comerciale

## Plăți

În `Administrare → Plăți & livrare` există Stripe și NETOPIA. Fiecare poate fi activat/dezactivat, setat `sandbox`/`live`, iar unul singur este implicit. Credentialele nu sunt citite din `.env`; sunt criptate în PostgreSQL și nu sunt reafișate în formular.

Stripe folosește Payment Intents, o cheie de idempotency per comandă și webhook semnat cu toleranță de 5 minute. NETOPIA folosește API v2 JSON, headerul `Authorization`, `posSignature`, endpointurile separate sandbox/live și IPN.

Webhook public:

```text
POST /api/webhooks/payments/stripe
POST /api/webhooks/payments/netopia
```

Pagina de retur nu marchează o comandă drept plătită. Doar webhook-ul verificat actualizează `payment_status`, `paid_at` și istoricul tranzacției.

Documentație de referință:

- https://docs.stripe.com/payments/payment-intents
- https://docs.stripe.com/webhooks
- https://doc.netopia-payments.com/docs/payment-api/v2.x/intro/
- https://doc.netopia-payments.com/docs/payment-api/v2.x/start/start-strc/

## FAN Courier

Providerul și metoda `fan-standard` sunt create de seeder, dar rămân inactive până la introducerea credentialelor. Din detaliul comenzii se poate genera AWB, iar trackingul poate fi reîmprospătat și salvat ca evenimente.

FAN oferă documentația API prin zona SelfAWB și pagina oficială eCommerce. Deoarece versiunea/endpointurile pot depinde de contul contractual, `base_url`, `awb_path`, `tracking_path` și serviciul sunt setări persistente, nu constante împrăștiate în cod.

- https://www.fancourier.ro/en/ecommerce/
- https://www.fancourier.ro/en/the-new-selfawb/
