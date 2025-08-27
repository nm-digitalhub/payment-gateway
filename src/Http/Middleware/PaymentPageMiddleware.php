<?php

namespace NMDigitalHub\PaymentGateway\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use NMDigitalHub\PaymentGateway\Models\PaymentPage;

/**
 * Middleware לטיפול בעמודי תשלום
 * מוודא הרשאות וזמינות עמודים
 */
class PaymentPageMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $slug = $request->route('slug');
        
        if ($slug) {
            $page = PaymentPage::where('slug', $slug)
                ->published()
                ->public()
                ->first();
                
            if (!$page) {
                abort(404, 'עמוד התשלום לא נמצא או לא זמין');
            }
            
            if ($page->requiresAuth() && !auth()->check()) {
                return redirect()->route('login')
                    ->with('intended', $request->fullUrl());
            }
            
            // שיתוף הדף עם הview
            view()->share('paymentPage', $page);
        }
        
        return $next($request);
    }
}
