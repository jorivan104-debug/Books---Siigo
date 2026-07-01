<?php

use App\Http\Controllers\Setup\SetupAuthController;
use App\Http\Controllers\Setup\SetupController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/setup');

Route::prefix('setup')->name('setup.')->group(function () {
    Route::get('/login', [SetupAuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [SetupAuthController::class, 'login'])->name('login.submit');

    Route::middleware('setup.auth')->group(function () {
        Route::get('/', [SetupController::class, 'index'])->name('index');
        Route::post('/logout', [SetupAuthController::class, 'logout'])->name('logout');

        Route::post('/zoho/exchange-grant-token', [SetupController::class, 'exchangeZohoGrantToken'])
            ->name('zoho.exchange');
        Route::post('/zoho/test', [SetupController::class, 'testZoho'])->name('zoho.test');
        Route::post('/siigo/test', [SetupController::class, 'testSiigo'])->name('siigo.test');
    });
});
