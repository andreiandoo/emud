<?php

use App\Http\Controllers\PaymentWebhookController;
use Illuminate\Support\Facades\Route;

Route::post('/webhooks/payments/{provider}', PaymentWebhookController::class)->name('payments.webhook');
