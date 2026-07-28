<?php

use App\Http\Controllers\ZohoInvoiceSyncController;
use App\Services\SiigoInvoiceService;
use Illuminate\Support\Facades\Route;

Route::get('/build', function () {
    return response()->json([
        'hub_build' => SiigoInvoiceService::HUB_BUILD,
        'app' => config('app.name'),
    ]);
});

Route::middleware('integration.key')->prefix('zoho')->group(function () {
    Route::post('/invoice/sync', [ZohoInvoiceSyncController::class, 'sync']);
});
