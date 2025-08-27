<?php

use Illuminate\Support\Facades\Route;
use NMDigitalHub\PaymentGateway\Http\Controllers\CheckoutController;
use NMDigitalHub\PaymentGateway\Http\Controllers\WebhookController;

/*
|--------------------------------------------------------------------------
| Payment Gateway Web Routes
|--------------------------------------------------------------------------
*/

// Public Payment Pages
Route::middleware(['web'])->prefix('payment-gateway')->name('payment-gateway.')->group(function () {
    // Package catalog
    Route::get('/packages', [CheckoutController::class, 'getAvailablePackages'])
        ->name('catalog');
    
    // Checkout pages
    Route::get('/checkout/{slug}', [CheckoutController::class, 'showPaymentPage'])
        ->name('checkout')
        ->where('slug', '[a-zA-Z0-9\-_]+');
    
    // Payment processing
    Route::post('/checkout/{slug}', [CheckoutController::class, 'processPayment'])
        ->name('process')
        ->where('slug', '[a-zA-Z0-9\-_]+');
    
    // Status check (AJAX)
    Route::get('/status/{reference}', [CheckoutController::class, 'checkPaymentStatus'])
        ->name('status')
        ->where('reference', '[a-zA-Z0-9\-_]+');
    
    // Cancel payment
    Route::post('/cancel/{reference}', [CheckoutController::class, 'cancelPayment'])
        ->name('cancel')
        ->where('reference', '[a-zA-Z0-9\-_]+');
    
    // Success/Failed pages
    Route::get('/success', [CheckoutController::class, 'paymentSuccess'])
        ->name('success');
    
    Route::get('/failed', [CheckoutController::class, 'paymentFailed'])
        ->name('failed');
});

// Webhook Routes (public, no auth required)
Route::middleware(['api'])->prefix('webhooks/payment-gateway')->name('payment-gateway.webhooks.')->group(function () {
    Route::post('/cardcom', [WebhookController::class, 'handleCardCom'])->name('cardcom');
    Route::post('/maya-mobile', [WebhookController::class, 'handleMayaMobile'])->name('maya-mobile');
    Route::post('/resellerclub', [WebhookController::class, 'handleResellerClub'])->name('resellerclub');
    Route::get('/health', [WebhookController::class, 'healthCheck'])->name('health');
});