<?php

use Illuminate\Support\Facades\Route;
use NMDigitalHub\PaymentGateway\Http\Controllers\ApiController;

/*
|--------------------------------------------------------------------------
| Payment Gateway API Routes
|--------------------------------------------------------------------------
*/

Route::middleware(['api'])->prefix('payment-gateway')->name('payment-gateway.api.')->group(function () {
    
    // Payment status check
    Route::get('/status/{reference}', [ApiController::class, 'getStatus'])
        ->name('status');
    
    // Transaction details
    Route::get('/transaction/{id}', [ApiController::class, 'getTransaction'])
        ->name('transaction');
    
    // Health check
    Route::get('/health', [ApiController::class, 'health'])
        ->name('health');
        
    // Provider status
    Route::get('/providers', [ApiController::class, 'getProviders'])
        ->name('providers');
});