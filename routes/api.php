<?php

use App\Http\Controllers\Api\V1\ChangesController;
use App\Http\Controllers\Api\V1\CompatibilityController;
use App\Http\Controllers\Api\V1\CoverageController;
use App\Http\Controllers\Api\V1\PartByNumberController;
use App\Http\Controllers\Api\V1\PartGraphController;
use App\Http\Controllers\Api\V1\PartSearchController;
use App\Http\Controllers\Api\V1\PartShowController;
use App\Http\Controllers\Api\V1\VehiclePartsController;
use App\Http\Controllers\Api\V1\VehicleSearchController;
use App\Http\Controllers\Api\V1\VehicleShowController;
use App\Http\Controllers\Api\V1\VinController;
use App\Http\Controllers\PaymentWebhookController;
use Illuminate\Support\Facades\Route;

Route::post('/webhooks/payments/{provider}', PaymentWebhookController::class)->name('payments.webhook');

Route::prefix('v1')->middleware(['catalog.api', 'throttle:api'])->group(function (): void {
    Route::get('/vin/{vin}', VinController::class)->where('vin', '[A-HJ-NPR-Z0-9]{17}');
    Route::get('/vehicles/search', VehicleSearchController::class);
    Route::get('/vehicles/{vehicle}', VehicleShowController::class)->whereNumber('vehicle');
    Route::get('/vehicles/{vehicle}/parts', VehiclePartsController::class)->whereNumber('vehicle');
    Route::get('/parts/search', PartSearchController::class);
    Route::get('/parts/by-number/{number}/graph', PartGraphController::class);
    Route::get('/parts/by-number/{number}', PartByNumberController::class);
    Route::get('/parts/{part}', PartShowController::class);
    Route::post('/compatibility/check', CompatibilityController::class);
    Route::get('/coverage', CoverageController::class);
    Route::get('/changes', ChangesController::class);
});
