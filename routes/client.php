<?php

use Illuminate\Support\Facades\Route;
use NMDigitalHub\PaymentGateway\Http\Controllers\Client\ClientAccountController;
use NMDigitalHub\PaymentGateway\Http\Controllers\Client\ClientOrdersController;
use NMDigitalHub\PaymentGateway\Http\Controllers\Client\ClientPaymentsController;

/*
|--------------------------------------------------------------------------
| Client Panel Routes (Payment Gateway)
|--------------------------------------------------------------------------
|
| נתיבים לפאנל לקוח - חשבונות, תשלומים והזמנות
*/

Route::middleware(['web', 'auth'])->prefix('account')->name('account.')->group(function () {
    
    // חשבון לקוח
    Route::get('/dashboard', [ClientAccountController::class, 'dashboard'])
        ->name('dashboard');
    
    Route::get('/profile', [ClientAccountController::class, 'profile'])
        ->name('profile');
    
    Route::put('/profile', [ClientAccountController::class, 'updateProfile'])
        ->name('profile.update');
    
    // הזמנות לקוח
    Route::get('/orders', [ClientOrdersController::class, 'index'])
        ->name('orders');
    
    Route::get('/orders/{order}', [ClientOrdersController::class, 'show'])
        ->name('orders.show')
        ->where('order', '[0-9]+');
    
    Route::get('/orders/{order}/invoice', [ClientOrdersController::class, 'downloadInvoice'])
        ->name('orders.invoice')
        ->where('order', '[0-9]+');
    
    Route::post('/orders/{order}/cancel', [ClientOrdersController::class, 'cancelOrder'])
        ->name('orders.cancel')
        ->where('order', '[0-9]+');
    
    // תשלומים לקוח
    Route::get('/payments', [ClientPaymentsController::class, 'index'])
        ->name('payments');
    
    Route::get('/payments/{payment}', [ClientPaymentsController::class, 'show'])
        ->name('payments.show')
        ->where('payment', '[0-9]+');
    
    Route::get('/payments/{payment}/receipt', [ClientPaymentsController::class, 'downloadReceipt'])
        ->name('payments.receipt')
        ->where('payment', '[0-9]+');
    
    // אמצעי תשלום שמורים
    Route::get('/payment-methods', [ClientPaymentsController::class, 'paymentMethods'])
        ->name('payment-methods');
    
    Route::delete('/payment-methods/{token}', [ClientPaymentsController::class, 'deletePaymentMethod'])
        ->name('payment-methods.delete')
        ->where('token', '[0-9]+');
    
    Route::post('/payment-methods/{token}/set-default', [ClientPaymentsController::class, 'setDefaultPaymentMethod'])
        ->name('payment-methods.set-default')
        ->where('token', '[0-9]+');
});

// נתיבים ללא אימות (לצפייה בעמודים ציבוריים)
Route::middleware(['web'])->group(function () {
    
    // צפייה בעמודי תשלום ציבוריים
    Route::get('/p/{slug}', [ClientAccountController::class, 'showPublicPage'])
        ->name('payment.page.show')
        ->where('slug', '[a-zA-Z0-9\-_]+');
    
    Route::get('/payment/page/{slug}', [ClientAccountController::class, 'showPublicPage'])
        ->name('payment.page.show.alt')
        ->where('slug', '[a-zA-Z0-9\-_]+');
    
    // תצוגה מקדימה (לאדמינים)
    Route::get('/payment/page/{slug}/preview', [ClientAccountController::class, 'previewPage'])
        ->name('payment.page.preview')
        ->middleware('auth')
        ->where('slug', '[a-zA-Z0-9\-_]+');
});

// API Routes ללקוח (AJAX)
Route::middleware(['api', 'auth:sanctum'])->prefix('api/account')->name('api.account.')->group(function () {
    
    // סטטיסטיקות לקוח
    Route::get('/stats', [ClientAccountController::class, 'getStats'])
        ->name('stats');
    
    // היסטוריית תשלומים
    Route::get('/payment-history', [ClientPaymentsController::class, 'getPaymentHistory'])
        ->name('payment-history');
    
    // סטטוס הזמנה בזמן אמת
    Route::get('/orders/{order}/status', [ClientOrdersController::class, 'getOrderStatus'])
        ->name('orders.status.api')
        ->where('order', '[0-9]+');
    
    // עדכון פרופיל חלקי
    Route::patch('/profile/field/{field}', [ClientAccountController::class, 'updateProfileField'])
        ->name('profile.update-field')
        ->where('field', '[a-zA-Z_]+');
});
