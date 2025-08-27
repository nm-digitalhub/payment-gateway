<?php

use Illuminate\Support\Facades\Route;
use NMDigitalHub\PaymentGateway\Http\Controllers\PackageCheckoutController;
use NMDigitalHub\PaymentGateway\Http\Controllers\PackageCatalogController;
use NMDigitalHub\PaymentGateway\Http\Controllers\PaymentHandlerController;
use NMDigitalHub\PaymentGateway\Http\Controllers\WebhookController;

/**
 * Payment Gateway Package Routes
 * מבוסס על מערכת eSIM המאוחדת - הטמעה מלאה עם slug support
 */

// Unified Package System Routes - מערכת חבילות מאוחדת
Route::middleware(['web'])->prefix('payment-gateway')->name('payment-gateway.')->group(function () {
    
    // Package Catalog Routes - קטלוג חבילות (כמו eSIM packages)
    Route::get('/packages', [PackageCatalogController::class, 'index'])->name('packages.index');
    Route::get('/packages/search', [PackageCatalogController::class, 'search'])->name('packages.search');
    Route::get('/packages/sync', [PackageCatalogController::class, 'sync'])->name('packages.sync');
    Route::get('/packages/{packageSlug}', [PackageCatalogController::class, 'show'])
        ->name('packages.show')
        ->where('packageSlug', '[a-zA-Z0-9\-_]+');
    
    // Unified Checkout System - מערכת רכישה מאוחדת (מבוסס על EsimCheckoutController)
    Route::get('/checkout/{packageSlug}', [PackageCheckoutController::class, 'show'])
        ->name('checkout.show')
        ->where('packageSlug', '[a-zA-Z0-9\-_]+');
        
    Route::post('/checkout/{packageSlug}', [PackageCheckoutController::class, 'process'])
        ->name('checkout.process')
        ->where('packageSlug', '[a-zA-Z0-9\-_]+');
    
    // Payment Success/Failed Routes - דפי הצלחה/כשלון (כמו eSIM)
    Route::get('/payment/success', [PaymentHandlerController::class, 'paymentSuccess'])->name('payment.success');
    Route::get('/payment/failed', [PaymentHandlerController::class, 'paymentFailed'])->name('payment.failed');
    
    // Order Success Route - דף הצלחת הזמנה (כמו eSIM success)
    Route::get('/orders/{orderId}/success', [PackageCheckoutController::class, 'success'])
        ->name('orders.success')
        ->where('orderId', '[0-9]+');
    
    // AJAX API Routes - נתיבי API לקריאות AJAX
    Route::prefix('api')->name('api.')->group(function () {
        // Coupon validation (כמו במערכת eSIM)
        Route::post('/coupon/validate', [PackageCheckoutController::class, 'validateCoupon'])->name('coupon.validate');
        
        // Package availability and pricing
        Route::get('/packages/{packageSlug}/availability', [PackageCatalogController::class, 'checkAvailability'])
            ->name('packages.availability');
        Route::get('/packages/{packageSlug}/pricing', [PackageCatalogController::class, 'getPricing'])
            ->name('packages.pricing');
        
        // Payment status checking
        Route::get('/payment/status/{orderId}', [PaymentHandlerController::class, 'getPaymentStatus'])
            ->name('payment.status');
            
        // Package sync status
        Route::get('/sync/status', [PackageCatalogController::class, 'getSyncStatus'])->name('sync.status');
    });
});

// Webhook Routes (public, no auth required)
Route::middleware(['api'])->prefix('webhooks/payment-gateway')->name('payment-gateway.webhooks.')->group(function () {
    Route::post('/cardcom', [WebhookController::class, 'handleCardCom'])->name('cardcom');
    Route::post('/maya-mobile', [WebhookController::class, 'handleMayaMobile'])->name('maya-mobile');
    Route::post('/resellerclub', [WebhookController::class, 'handleResellerClub'])->name('resellerclub');
    Route::get('/health', [WebhookController::class, 'healthCheck'])->name('health');
});