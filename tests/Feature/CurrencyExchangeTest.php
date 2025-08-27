<?php

namespace NMDigitalHub\PaymentGateway\Tests\Feature;

use NMDigitalHub\PaymentGateway\Services\CurrencyExchangeService;
use NMDigitalHub\PaymentGateway\Tests\TestCase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;

/**
 * בדיקות שירות המרת מטבע
 * מתמקדת בAPI חיצוני וcache
 */
class CurrencyExchangeTest extends TestCase
{
    protected CurrencyExchangeService $currencyService;
    
    protected function setUp(): void
    {
        parent::setUp();
        $this->currencyService = new CurrencyExchangeService();
        Cache::flush(); // ניקוי cache לפני כל בדיקה
    }

    public function test_same_currency_conversion_returns_same_amount()
    {
        $result = $this->currencyService->convert(100, 'USD', 'USD');
        $this->assertEquals(100, $result);
    }

    public function test_fallback_rates_are_used_when_api_fails()
    {
        // משיק HTTP requests שנכשלים
        Http::fake([
            '*' => Http::response(null, 500)
        ]);
        
        $result = $this->currencyService->convert(100, 'USD', 'ILS');
        
        // אמור להשתמש בשער fallback (3.70)
        $this->assertEquals(370.0, $result);
    }

    public function test_successful_api_response_is_cached()
    {
        // משיק תגובה מוצלחת
        Http::fake([
            'api.fixer.io/*' => Http::response([
                'success' => true,
                'rates' => ['ILS' => 3.75]
            ])
        ]);
        
        // קריאה ראשונה - אמורה לשלוח בקשת HTTP
        $rate1 = $this->currencyService->getExchangeRate('USD', 'ILS');
        
        // קריאה שנייה - אמורה להשתמש בcache
        $rate2 = $this->currencyService->getExchangeRate('USD', 'ILS');
        
        $this->assertEquals(3.75, $rate1);
        $this->assertEquals(3.75, $rate2);
        
        // בדיקה שהבקשה נשלחה רק פעם אחת
        Http::assertSentCount(1);
    }

    public function test_cache_can_be_cleared()
    {
        // שמירת איזה דבר בcache
        Cache::put('exchange_rate_fixer_USD_ILS', 3.80, 3600);
        $this->assertTrue(Cache::has('exchange_rate_fixer_USD_ILS'));
        
        // ניקוי cache
        $this->currencyService->clearCache('USD', 'ILS');
        
        // בדיקה שהcache נוקה
        $this->assertFalse(Cache::has('exchange_rate_fixer_USD_ILS'));
    }

    public function test_supported_currencies_list()
    {
        $currencies = $this->currencyService->getSupportedCurrencies();
        
        $this->assertIsArray($currencies);
        $this->assertArrayHasKey('USD', $currencies);
        $this->assertArrayHasKey('EUR', $currencies);
        $this->assertArrayHasKey('ILS', $currencies);
        $this->assertEquals('US Dollar', $currencies['USD']);
    }

    public function test_api_validation_works()
    {
        // משיק תגובה תקינה
        Http::fake([
            'api.fixer.io/*' => Http::response([
                'success' => true,
                'rates' => ['EUR' => 0.85]
            ])
        ]);
        
        $isValid = $this->currencyService->validateApiKey('fixer');
        $this->assertTrue($isValid);
    }

    public function test_api_validation_fails_with_invalid_key()
    {
        // משיק תגובת שגיאה
        Http::fake([
            'api.fixer.io/*' => Http::response([
                'success' => false,
                'error' => ['code' => 101, 'info' => 'Invalid API key']
            ], 401)
        ]);
        
        $isValid = $this->currencyService->validateApiKey('fixer');
        $this->assertFalse($isValid);
    }

    public function test_usage_stats_tracking()
    {
        $initialStats = $this->currencyService->getUsageStats();
        
        $this->assertIsArray($initialStats);
        $this->assertArrayHasKey('total_requests', $initialStats);
        $this->assertArrayHasKey('successful_requests', $initialStats);
        $this->assertArrayHasKey('failed_requests', $initialStats);
    }

    public function test_multiple_providers_support()
    {
        $providers = ['fixer', 'exchangerate', 'currencylayer', 'openexchange'];
        
        foreach ($providers as $provider) {
            // משיק תגובה לכל ספק
            Http::fake([
                "*{$provider}*" => Http::response([
                    'success' => true,
                    'rates' => ['EUR' => 0.85],
                    'conversion_rate' => 0.85,
                    'quotes' => ['USDEUR' => 0.85]
                ])
            ]);
            
            try {
                $rate = $this->currencyService->getExchangeRate('USD', 'EUR', $provider);
                $this->assertIsFloat($rate);
            } catch (\Exception $e) {
                // אם הספק לא נתמך, סביר
                $this->assertStringContainsString('Unknown provider', $e->getMessage());
            }
        }
    }

    public function test_amount_conversion_with_rounding()
    {
        Http::fake([
            'api.fixer.io/*' => Http::response([
                'success' => true,
                'rates' => ['ILS' => 3.7333333]
            ])
        ]);
        
        $result = $this->currencyService->convert(100.56, 'USD', 'ILS');
        
        // בדיקה שהתוצאה מעוגלת ל-2 מקומות עשרוניים
        $expected = round(100.56 * 3.7333333, 2);
        $this->assertEquals($expected, $result);
    }
}
