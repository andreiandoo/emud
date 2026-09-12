<?php

use App\Http\Controllers\Api\V1\CategoryTreeController;
use App\Http\Controllers\Api\V1\ChangesController;
use App\Http\Controllers\Api\V1\CompatibilityController;
use App\Http\Controllers\Api\V1\CoverageController;
use App\Http\Controllers\Api\V1\PartByNumberController;
use App\Http\Controllers\Api\V1\PartGraphController;
use App\Http\Controllers\Api\V1\PartNumberBatchController;
use App\Http\Controllers\Api\V1\PartSearchController;
use App\Http\Controllers\Api\V1\PartShowController;
use App\Http\Controllers\Api\V1\SourceIndexController;
use App\Http\Controllers\Api\V1\VehiclePartsController;
use App\Http\Controllers\Api\V1\VehicleSearchController;
use App\Http\Controllers\Api\V1\VehicleShowController;
use App\Http\Controllers\Api\V1\VehicleTreeController;
use App\Http\Controllers\Api\V1\VinBatchController;
use App\Http\Controllers\Api\V1\VinController;
use App\Http\Controllers\PaymentWebhookController;
use Illuminate\Support\Facades\Route;

Route::post('/webhooks/payments/{provider}', PaymentWebhookController::class)->name('payments.webhook');

Route::prefix('v1')->middleware(['catalog.api', 'throttle:api'])->group(function (): void {
    Route::post('/vin/batch', VinBatchController::class);
    Route::get('/vin/{vin}', VinController::class)->where('vin', '[A-HJ-NPR-Z0-9]{17}');

    // The car picker cascade, in the order a customer answers it.
    Route::get('/makes', [VehicleTreeController::class, 'makes']);
    Route::get('/makes/{make}/models', [VehicleTreeController::class, 'models'])->whereNumber('make');
    Route::get('/models/{model}/generations', [VehicleTreeController::class, 'generations'])->whereNumber('model');
    Route::get('/generations/{generation}/vehicles', [VehicleTreeController::class, 'vehicles'])->whereNumber('generation');

    Route::get('/vehicles/search', VehicleSearchController::class);
    Route::get('/vehicles/{vehicle}', VehicleShowController::class)->whereNumber('vehicle');
    Route::get('/vehicles/{vehicle}/parts', VehiclePartsController::class)->whereNumber('vehicle');

    Route::get('/categories', CategoryTreeController::class);
    Route::get('/sources', SourceIndexController::class);

    Route::get('/parts/search', PartSearchController::class);
    Route::post('/parts/by-number/batch', PartNumberBatchController::class);
    // Query forms first: they are the only ones a number containing a slash can reach, and they
    // must not be mistaken for a part id or a number by the routes below.
    Route::get('/parts/lookup', PartByNumberController::class);
    Route::get('/parts/graph', PartGraphController::class);
    Route::get('/parts/by-number/{number}/graph', PartGraphController::class);
    Route::get('/parts/by-number/{number}', PartByNumberController::class);
    Route::get('/parts/{part}', PartShowController::class);

    Route::post('/compatibility/check', CompatibilityController::class);
    Route::get('/coverage', CoverageController::class);
    Route::get('/changes', ChangesController::class);
});
