<?php

namespace NMDigitalHub\PaymentGateway\Tests\Feature;

use NMDigitalHub\PaymentGateway\Http\Middleware\PaymentSecurityMiddleware;
use NMDigitalHub\PaymentGateway\Http\Middleware\PageRedirectMiddleware;
use NMDigitalHub\PaymentGateway\Models\PageRedirect;
use NMDigitalHub\PaymentGateway\Models\PaymentPage;
use NMDigitalHub\PaymentGateway\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * בדיקות Middlewares
 * מתמקדת בCSP headers וredirects
 */
class MiddlewareTest extends TestCase
{
    use RefreshDatabase;

    public function test_payment_security_middleware_adds_csp_headers()
    {
        $middleware = new PaymentSecurityMiddleware();
        
        $request = Request::create('/payment/checkout');
        $response = new Response('Test content');
        
        $result = $middleware->handle($request, function () use ($response) {
            return $response;
        });
        
        // בדיקה שCSP header נוסף
        $this->assertTrue($result->headers->has('Content-Security-Policy'));
        
        $csp = $result->headers->get('Content-Security-Policy');
        $this->assertStringContainsString('frame-src https://secure.cardcom.solutions', $csp);
        $this->assertStringContainsString("script-src 'self'", $csp);
    }

    public function test_payment_security_middleware_adds_security_headers()
    {
        $middleware = new PaymentSecurityMiddleware();
        
        $request = Request::create('/payment/checkout');
        $response = new Response('Test content');
        
        $result = $middleware->handle($request, function () use ($response) {
            return $response;
        });
        
        // בדיקת security headers
        $this->assertTrue($result->headers->has('X-Content-Type-Options'));
        $this->assertEquals('nosniff', $result->headers->get('X-Content-Type-Options'));
        
        $this->assertTrue($result->headers->has('X-XSS-Protection'));
        $this->assertEquals('1; mode=block', $result->headers->get('X-XSS-Protection'));
        
        $this->assertTrue($result->headers->has('Referrer-Policy'));
        $this->assertTrue($result->headers->has('Permissions-Policy'));
    }

    public function test_payment_security_middleware_allows_framing_for_cardcom()
    {
        $middleware = new PaymentSecurityMiddleware();
        
        // בקשה לעמוד CardCom
        $request = Request::create('/cardcom/iframe');
        $response = new Response('Test content');
        
        $result = $middleware->handle($request, function () use ($response) {
            return $response;
        });
        
        $this->assertEquals('ALLOWALL', $result->headers->get('X-Frame-Options'));
    }

    public function test_payment_security_middleware_sets_cache_headers_for_payment_pages()
    {
        $middleware = new PaymentSecurityMiddleware();
        
        $request = Request::create('/payment/test');
        $response = new Response('Test content');
        
        $result = $middleware->handle($request, function () use ($response) {
            return $response;
        });
        
        $this->assertEquals('no-cache, no-store, must-revalidate, private', 
                          $result->headers->get('Cache-Control'));
        $this->assertEquals('no-cache', $result->headers->get('Pragma'));
        $this->assertEquals('0', $result->headers->get('Expires'));
    }

    public function test_page_redirect_middleware_finds_and_executes_redirect()
    {
        // יצירת עמוד
        $page = PaymentPage::create([
            'title' => 'עמוד עם redirect',
            'slug' => 'current-page',
            'type' => PaymentPage::TYPE_CHECKOUT,
            'status' => PaymentPage::STATUS_PUBLISHED
        ]);

        // יצירת redirect
        PageRedirect::create([
            'old_slug' => 'old-page-slug',
            'new_slug' => 'current-page',
            'page_id' => $page->id,
            'locale' => 'he',
            'status_code' => 301,
            'is_active' => true
        ]);

        $middleware = new PageRedirectMiddleware();
        $request = Request::create('/p/old-page-slug');
        
        $result = $middleware->handle($request, function () {
            return new Response('Should not reach here');
        });
        
        // בדיקה שזה redirect response
        $this->assertEquals(301, $result->getStatusCode());
        $this->assertStringContainsString('current-page', $result->headers->get('Location'));
        $this->assertEquals('slug-change', $result->headers->get('X-Redirect-Reason'));
        $this->assertEquals('old-page-slug', $result->headers->get('X-Original-Slug'));
    }

    public function test_page_redirect_middleware_records_hit_count()
    {
        $page = PaymentPage::create([
            'title' => 'עמוד עם redirect',
            'slug' => 'target-page',
            'type' => PaymentPage::TYPE_CHECKOUT,
            'status' => PaymentPage::STATUS_PUBLISHED
        ]);

        $redirect = PageRedirect::create([
            'old_slug' => 'source-page',
            'new_slug' => 'target-page',
            'page_id' => $page->id,
            'locale' => 'he',
            'status_code' => 301,
            'is_active' => true,
            'hit_count' => 0
        ]);

        $middleware = new PageRedirectMiddleware();
        $request = Request::create('/p/source-page');
        
        $middleware->handle($request, function () {
            return new Response('Should not reach here');
        });
        
        // בדיקה שמונה הפגיעות עלה
        $this->assertEquals(1, $redirect->fresh()->hit_count);
    }

    public function test_page_redirect_middleware_ignores_non_get_requests()
    {
        PageRedirect::create([
            'old_slug' => 'old-slug',
            'new_slug' => 'new-slug',
            'locale' => 'he',
            'status_code' => 301,
            'is_active' => true
        ]);

        $middleware = new PageRedirectMiddleware();
        
        // POST request לא אמור להיות redirect
        $request = Request::create('/p/old-slug', 'POST');
        
        $result = $middleware->handle($request, function () {
            return new Response('Normal response');
        });
        
        $this->assertEquals(200, $result->getStatusCode());
        $this->assertEquals('Normal response', $result->getContent());
    }

    public function test_page_redirect_middleware_handles_locales()
    {
        $page = PaymentPage::create([
            'title' => 'English Page',
            'slug' => 'english-page',
            'type' => PaymentPage::TYPE_CHECKOUT,
            'status' => PaymentPage::STATUS_PUBLISHED
        ]);

        // Redirect עבור אנגלית
        PageRedirect::create([
            'old_slug' => 'old-english',
            'new_slug' => 'english-page', 
            'page_id' => $page->id,
            'locale' => 'en',
            'status_code' => 301,
            'is_active' => true
        ]);

        $middleware = new PageRedirectMiddleware();
        
        // בקשה עם locale אנגלי
        $request = Request::create('/en/p/old-english');
        $request->headers->set('Accept-Language', 'en-US,en;q=0.9');
        
        $result = $middleware->handle($request, function () {
            return new Response('Should not reach here');
        });
        
        $this->assertEquals(301, $result->getStatusCode());
        $this->assertStringContainsString('english-page', $result->headers->get('Location'));
    }

    public function test_page_redirect_middleware_continues_when_no_redirect_found()
    {
        $middleware = new PageRedirectMiddleware();
        $request = Request::create('/p/non-existent-slug');
        
        $result = $middleware->handle($request, function () {
            return new Response('Normal processing');
        });
        
        $this->assertEquals(200, $result->getStatusCode());
        $this->assertEquals('Normal processing', $result->getContent());
    }

    public function test_expired_redirects_are_ignored()
    {
        PageRedirect::create([
            'old_slug' => 'expired-slug',
            'new_slug' => 'new-slug',
            'locale' => 'he',
            'status_code' => 301,
            'is_active' => true,
            'expires_at' => now()->subDay() // פג
        ]);

        $middleware = new PageRedirectMiddleware();
        $request = Request::create('/p/expired-slug');
        
        $result = $middleware->handle($request, function () {
            return new Response('No redirect should happen');
        });
        
        $this->assertEquals(200, $result->getStatusCode());
        $this->assertEquals('No redirect should happen', $result->getContent());
    }
}
