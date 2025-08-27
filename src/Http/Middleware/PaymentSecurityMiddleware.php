<?php

namespace NMDigitalHub\PaymentGateway\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Middleware לאבטחת עמודי תשלום
 * מוסיף CSP headers ובטיחות נוספת
 */
class PaymentSecurityMiddleware
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // הוספת CSP headers לעמודי תשלום
        if ($this->isPaymentPage($request)) {
            $this->addCspHeaders($response);
        }

        // הוספת security headers כלליים
        $this->addSecurityHeaders($response);

        return $response;
    }

    /**
     * בדיקה אם זהו עמוד תשלום
     */
    protected function isPaymentPage(Request $request): bool
    {
        $path = $request->path();
        
        return str_contains($path, 'payment') || 
               str_contains($path, 'checkout') ||
               str_contains($path, 'cardcom');
    }

    /**
     * הוספת CSP headers
     */
    protected function addCspHeaders(Response $response): void
    {
        $cspDirectives = [
            // Scripts - רק מהדומיין שלנו ו-CardCom
            "script-src 'self' 'unsafe-inline' https://secure.cardcom.solutions https://cdn.cardcom.solutions",
            
            // Styles - מאפשר inline styles לעיצוב
            "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com",
            
            // Frames - רק CardCom לiframes
            "frame-src https://secure.cardcom.solutions https://gateway.cardcom.solutions",
            
            // Child frames
            "child-src https://secure.cardcom.solutions",
            
            // Connect - APIs מורשים
            "connect-src 'self' https://secure.cardcom.solutions https://api.cardcom.solutions",
            
            // Images - מהדומיין וספקים מורשים
            "img-src 'self' data: https: blob:",
            
            // Fonts
            "font-src 'self' https://fonts.gstatic.com",
            
            // Base URI
            "base-uri 'self'",
            
            // Form action - רק לדומיין שלנו ו-CardCom
            "form-action 'self' https://secure.cardcom.solutions",
            
            // Object sources
            "object-src 'none'",
            
            // Media sources
            "media-src 'self'",
            
            // Worker sources  
            "worker-src 'self'",
            
            // Manifest
            "manifest-src 'self'",
        ];

        // בניית CSP policy
        $cspPolicy = implode('; ', $cspDirectives);
        
        $response->headers->set('Content-Security-Policy', $cspPolicy);
        
        // Fallback לדפדפנים ישנים
        $response->headers->set('X-Content-Security-Policy', $cspPolicy);
        $response->headers->set('X-WebKit-CSP', $cspPolicy);
    }

    /**
     * הוספת security headers כלליים
     */
    protected function addSecurityHeaders(Response $response): void
    {
        // מניעת clickjacking - אבל מתיר embedding מCardCom
        if ($this->shouldAllowFraming($response)) {
            $response->headers->set('X-Frame-Options', 'ALLOWALL');
        } else {
            $response->headers->set('X-Frame-Options', 'SAMEORIGIN');
        }
        
        // HTTPS Enforcement
        $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        
        // Content type sniffing prevention
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        
        // XSS Protection
        $response->headers->set('X-XSS-Protection', '1; mode=block');
        
        // Referrer Policy
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        
        // Permissions Policy
        $permissions = [
            'geolocation=()',
            'microphone=()',
            'camera=()',
            'payment=(self "https://secure.cardcom.solutions")',
            'encrypted-media=()',
        ];
        $response->headers->set('Permissions-Policy', implode(', ', $permissions));
        
        // CORP for iframe compatibility
        $response->headers->set('Cross-Origin-Resource-Policy', 'cross-origin');
        
        // Cache control for payment pages
        if ($this->isPaymentPage(request())) {
            $response->headers->set('Cache-Control', 'no-cache, no-store, must-revalidate, private');
            $response->headers->set('Pragma', 'no-cache');
            $response->headers->set('Expires', '0');
        }
    }

    /**
     * בדיקה אם יש לאפשר framing
     */
    protected function shouldAllowFraming(Response $response): bool
    {
        $request = request();
        
        // אפשר framing לעמודי CardCom integration
        return str_contains($request->path(), 'cardcom') ||
               str_contains($request->path(), 'iframe') ||
               str_contains($request->path(), 'embed');
    }

    /**
     * קבלת CSP directives מהקונפיגורציה
     */
    protected function getCspDirectivesFromConfig(): array
    {
        return config('payment-gateway.security.csp_directives', []);
    }

    /**
     * בדיקה אם CSP מופעל בקונפיגורציה
     */
    protected function isCspEnabled(): bool
    {
        return config('payment-gateway.security.csp_enabled', true);
    }

    /**
     * קבלת דומיינים מורשים לiframes
     */
    protected function getAllowedFrameSources(): array
    {
        return config('payment-gateway.security.allowed_frame_sources', [
            'https://secure.cardcom.solutions',
            'https://gateway.cardcom.solutions'
        ]);
    }
}
