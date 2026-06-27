<?php

use App\Http\Controllers\ZohoInvoiceSyncController;
use Illuminate\Support\Facades\Route;

Route::middleware('integration.key')->prefix('zoho')->group(function () {
    Route::post('/invoice/sync', [ZohoInvoiceSyncController::class, 'sync']);
});
