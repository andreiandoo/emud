<?php

namespace App\Http\Controllers;

use App\Models\PaymentProvider;
use App\Models\PaymentTransaction;
use App\Models\PaymentWebhookEvent;
use App\Payments\PaymentGatewayRegistry;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PaymentWebhookController extends Controller
{
    public function __invoke(Request $request, string $provider, PaymentGatewayRegistry $registry): Response
    {
        $paymentProvider = PaymentProvider::where('code', $provider)->where('is_active', true)->firstOrFail();
        $gateway = $registry->gateway($paymentProvider);
        $raw = $request->getContent();
        abort_unless($gateway->verifyWebhook($paymentProvider, $raw, $request->headers->all()), 401, 'Semnătură webhook invalidă.');
        $payload = json_decode($raw, true, flags: JSON_THROW_ON_ERROR);
        $externalId = (string) ($payload['id'] ?? $gateway->webhookReference($payload) ?? Str::uuid());
        $type = (string) ($payload['type'] ?? data_get($payload, 'payment.status', 'payment.update'));

        $event = PaymentWebhookEvent::firstOrCreate(
            ['payment_provider_id' => $paymentProvider->id, 'external_id' => $externalId],
            ['event_type' => $type, 'payload' => $payload]
        );
        if ($event->processed_at) {
            return response('OK');
        }

        DB::transaction(function () use ($event, $gateway, $payload): void {
            $reference = $gateway->webhookReference($payload);
            $transaction = PaymentTransaction::where('external_id', $reference)->lockForUpdate()->first();
            if ($transaction) {
                $status = $gateway->webhookStatus($payload);
                $transaction->update(['status' => $status, 'processed_at' => now(), 'payload' => [...($transaction->payload ?? []), 'webhook' => $payload]]);
                $transaction->order->update([
                    'payment_status' => $status,
                    'status' => $status === 'paid' ? 'confirmed' : $transaction->order->status,
                    'paid_at' => $status === 'paid' ? now() : $transaction->order->paid_at,
                ]);
            }
            $event->update(['status' => 'processed', 'processed_at' => now()]);
        });

        return response('OK');
    }
}
