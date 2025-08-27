<?php

namespace NMDigitalHub\PaymentGateway\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use NMDigitalHub\PaymentGateway\Models\PageRedirect;
use Illuminate\Support\Facades\Cache;

/**
 * Middleware לטיפול בהפניות Slug אוטומטיות
 * מטפל בשינויי שמות עמודים עם 301 redirects
 */
class PageRedirectMiddleware
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next)
    {
        // רק לGET requests
        if (!$request->isMethod('GET')) {
            return $next($request);
        }

        $path = $request->path();
        $locale = $this->detectLocale($request);
        
        // חיפוש redirect לשpath הנוכחי
        $redirect = $this->findRedirect($path, $locale);
        
        if ($redirect) {
            // רישום פגיעה בהפניה
            $redirect->recordHit();
            
            // בניית URL חדש
            $newUrl = $this->buildRedirectUrl($redirect, $request);
            
            // הפניה עם סטטוס קוד מתאים
            return redirect($newUrl, $redirect->status_code)
                ->header('X-Redirect-Reason', 'slug-change')
                ->header('X-Original-Slug', $redirect->old_slug);
        }

        // אם לא נמצא redirect, המשך לטיפול רגיל
        return $next($request);
    }

    /**
     * חיפוש redirect עבור path וlocale
     */
    protected function findRedirect(string $path, string $locale): ?PageRedirect
    {
        // חיפוש בcache קודם
        $cacheKey = "redirect:{$locale}:{$path}";
        
        return Cache::remember($cacheKey, 3600, function () use ($path, $locale) {
            // חיפוש slug מהpath
            $slug = $this->extractSlugFromPath($path);
            
            if (!$slug) {
                return null;
            }
            
            // חיפוש redirect פעיל
            return PageRedirect::active()
                ->byLocale($locale)
                ->byOldSlug($slug)
                ->first();
        });
    }

    /**
     * חילוץ slug מ-path
     */
    protected function extractSlugFromPath(string $path): ?string
    {
        // נתיבים שונים לעמודי תשלום
        $patterns = [
            '/^payment-page\/(.+)$/',          // payment-page/{slug}
            '/^p\/(.+)$/',                     // p/{slug}
            '/^pages?\/(.+)$/',                // page/{slug} or pages/{slug}
            '/^checkout\/(.+)$/',              // checkout/{slug}
            '/^([^\/?]+)$/',                   // Direct slug
        ];
        
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $path, $matches)) {
                return $matches[1];
            }
        }
        
        return null;
    }

    /**
     * גילוי locale מהrequest
     */
    protected function detectLocale(Request $request): string
    {
        // בדיקה בURL segment
        $segments = $request->segments();
        $supportedLocales = ['he', 'en', 'fr', 'ar'];
        
        if (!empty($segments) && in_array($segments[0], $supportedLocales)) {
            return $segments[0];
        }
        
        // בדיקה בsession
        if ($request->hasSession() && $request->session()->has('locale')) {
            $sessionLocale = $request->session()->get('locale');
            if (in_array($sessionLocale, $supportedLocales)) {
                return $sessionLocale;
            }
        }
        
        // בדיקה בAccept-Language header
        $acceptLanguage = $request->header('Accept-Language');
        if ($acceptLanguage) {
            foreach ($supportedLocales as $locale) {
                if (str_contains($acceptLanguage, $locale)) {
                    return $locale;
                }
            }
        }
        
        // ברירת מחדל
        return config('app.locale', 'he');
    }

    /**
     * בניית URL להפניה
     */
    protected function buildRedirectUrl(PageRedirect $redirect, Request $request): string
    {
        $locale = $this->detectLocale($request);
        $baseUrl = config('app.url');
        
        // בניית path חדש
        $newPath = $this->buildNewPath($redirect->new_slug, $locale);
        
        // שמירת query parameters
        $queryString = $request->getQueryString();
        if ($queryString) {
            $newPath .= '?' . $queryString;
        }
        
        return $baseUrl . '/' . ltrim($newPath, '/');
    }

    /**
     * בניית path חדש עם locale
     */
    protected function buildNewPath(string $newSlug, string $locale): string
    {
        $defaultLocale = config('app.locale', 'he');
        
        // אם זה לא locale ברירת מחדל, הוסף אותו
        if ($locale !== $defaultLocale) {
            return "{$locale}/p/{$newSlug}";
        }
        
        return "p/{$newSlug}";
    }

    /**
     * ניקוי cache לאחר שינוי redirects
     */
    public static function clearCache(string $locale = null, string $path = null): void
    {
        if ($locale && $path) {
            // ניקוי cache ספציפי
            Cache::forget("redirect:{$locale}:{$path}");
        } else {
            // ניקוי כל הredirects cache
            Cache::flush(); // או יותר מדויק עם tags
        }
    }

    /**
     * בדיקה אם הpath מוחרג מטיפול redirects
     */
    protected function shouldSkipRedirect(string $path): bool
    {
        $skipPaths = [
            'api/*',
            'admin/*',
            'livewire/*',
            '_debugbar/*',
            'vendor/*',
        ];
        
        foreach ($skipPaths as $skipPath) {
            if (fnmatch($skipPath, $path)) {
                return true;
            }
        }
        
        return false;
    }

    /**
     * לוגינג פעילות redirect
     */
    protected function logRedirectActivity(PageRedirect $redirect, Request $request): void
    {
        if (config('payment-gateway.logging.redirects', false)) {
            \Log::info('Page redirect executed', [
                'old_slug' => $redirect->old_slug,
                'new_slug' => $redirect->new_slug,
                'status_code' => $redirect->status_code,
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'referer' => $request->header('referer'),
            ]);
        }
    }
}
