<?php

namespace NMDigitalHub\PaymentGateway\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * שירות המרת מטבע עם API חיצוני
 * תומך במספר ספקי API שונים
 */
class CurrencyExchangeService
{
    protected array $providers = [
        'fixer' => 'https://api.fixer.io/v1',
        'exchangerate' => 'https://v6.exchangerate-api.com/v6',
        'currencylayer' => 'https://api.currencylayer.com',
        'openexchange' => 'https://openexchangerates.org/api'
    ];

    protected string $defaultProvider;
    protected string $apiKey;
    protected int $cacheTtl = 3600; // 1 hour

    public function __construct()
    {
        $this->defaultProvider = config('payment-gateway.currency.default_provider', 'fixer');
        $this->apiKey = config('payment-gateway.currency.api_key');
        $this->cacheTtl = config('payment-gateway.currency.cache_ttl', 3600);
    }

    /**
     * המרת סכום בין מטבעות
     */
    public function convert(
        float $amount, 
        string $fromCurrency, 
        string $toCurrency,
        ?string $provider = null
    ): float {
        if ($fromCurrency === $toCurrency) {
            return $amount;
        }

        $rate = $this->getExchangeRate($fromCurrency, $toCurrency, $provider);
        return round($amount * $rate, 2);
    }

    /**
     * קבלת שער החלפה בין מטבעות
     */
    public function getExchangeRate(
        string $fromCurrency, 
        string $toCurrency,
        ?string $provider = null
    ): float {
        $provider = $provider ?? $this->defaultProvider;
        $cacheKey = "exchange_rate_{$provider}_{$fromCurrency}_{$toCurrency}";

        return Cache::remember($cacheKey, $this->cacheTtl, function () use ($fromCurrency, $toCurrency, $provider) {
            try {
                return $this->fetchExchangeRate($fromCurrency, $toCurrency, $provider);
            } catch (\Exception $e) {
                Log::warning('Failed to fetch exchange rate', [
                    'provider' => $provider,
                    'from' => $fromCurrency,
                    'to' => $toCurrency,
                    'error' => $e->getMessage()
                ]);

                // נסיון עם ספק חלופי
                return $this->getFallbackRate($fromCurrency, $toCurrency);
            }
        });
    }

    /**
     * שליפת שער החלפה מ-API חיצוני
     */
    protected function fetchExchangeRate(string $fromCurrency, string $toCurrency, string $provider): float
    {
        return match ($provider) {
            'fixer' => $this->fetchFromFixer($fromCurrency, $toCurrency),
            'exchangerate' => $this->fetchFromExchangeRateApi($fromCurrency, $toCurrency),
            'currencylayer' => $this->fetchFromCurrencyLayer($fromCurrency, $toCurrency),
            'openexchange' => $this->fetchFromOpenExchange($fromCurrency, $toCurrency),
            default => throw new \InvalidArgumentException("Unknown provider: {$provider}")
        };
    }

    /**
     * Fixer.io API
     */
    protected function fetchFromFixer(string $fromCurrency, string $toCurrency): float
    {
        $response = Http::timeout(10)->get($this->providers['fixer'] . '/latest', [
            'access_key' => $this->apiKey,
            'base' => $fromCurrency,
            'symbols' => $toCurrency
        ]);

        if (!$response->successful()) {
            throw new \Exception('Fixer API request failed: ' . $response->body());
        }

        $data = $response->json();
        
        if (!$data['success']) {
            throw new \Exception('Fixer API error: ' . ($data['error']['info'] ?? 'Unknown error'));
        }

        return $data['rates'][$toCurrency] ?? throw new \Exception('Rate not found');
    }

    /**
     * ExchangeRate-API
     */
    protected function fetchFromExchangeRateApi(string $fromCurrency, string $toCurrency): float
    {
        $response = Http::timeout(10)->get(
            $this->providers['exchangerate'] . "/{$this->apiKey}/pair/{$fromCurrency}/{$toCurrency}"
        );

        if (!$response->successful()) {
            throw new \Exception('ExchangeRate API request failed');
        }

        $data = $response->json();
        
        if ($data['result'] !== 'success') {
            throw new \Exception('ExchangeRate API error: ' . ($data['error-type'] ?? 'Unknown error'));
        }

        return $data['conversion_rate'];
    }

    /**
     * CurrencyLayer API
     */
    protected function fetchFromCurrencyLayer(string $fromCurrency, string $toCurrency): float
    {
        $response = Http::timeout(10)->get($this->providers['currencylayer'] . '/live', [
            'access_key' => $this->apiKey,
            'source' => $fromCurrency,
            'currencies' => $toCurrency
        ]);

        if (!$response->successful()) {
            throw new \Exception('CurrencyLayer API request failed');
        }

        $data = $response->json();
        
        if (!$data['success']) {
            throw new \Exception('CurrencyLayer API error: ' . ($data['error']['info'] ?? 'Unknown error'));
        }

        $quoteKey = $fromCurrency . $toCurrency;
        return $data['quotes'][$quoteKey] ?? throw new \Exception('Rate not found');
    }

    /**
     * OpenExchangeRates API
     */
    protected function fetchFromOpenExchange(string $fromCurrency, string $toCurrency): float
    {
        $response = Http::timeout(10)->get($this->providers['openexchange'] . '/latest.json', [
            'app_id' => $this->apiKey,
            'base' => $fromCurrency,
            'symbols' => $toCurrency
        ]);

        if (!$response->successful()) {
            throw new \Exception('OpenExchangeRates API request failed');
        }

        $data = $response->json();
        return $data['rates'][$toCurrency] ?? throw new \Exception('Rate not found');
    }

    /**
     * שערי חלופין סטטיים (למקרה של כשל API)
     */
    protected function getFallbackRate(string $fromCurrency, string $toCurrency): float
    {
        // שערים בסיסיים מעודכנים ידנית (אנד 2025)
        $staticRates = [
            'USD' => [
                'ILS' => 3.70,
                'EUR' => 0.85,
                'GBP' => 0.75,
                'JPY' => 110.0,
            ],
            'EUR' => [
                'USD' => 1.18,
                'ILS' => 4.35,
                'GBP' => 0.88,
                'JPY' => 130.0,
            ],
            'ILS' => [
                'USD' => 0.27,
                'EUR' => 0.23,
                'GBP' => 0.20,
                'JPY' => 29.7,
            ],
            'GBP' => [
                'USD' => 1.33,
                'EUR' => 1.14,
                'ILS' => 4.95,
                'JPY' => 147.0,
            ]
        ];

        if (isset($staticRates[$fromCurrency][$toCurrency])) {
            Log::info('Using fallback exchange rate', [
                'from' => $fromCurrency,
                'to' => $toCurrency,
                'rate' => $staticRates[$fromCurrency][$toCurrency]
            ]);
            
            return $staticRates[$fromCurrency][$toCurrency];
        }

        // אם אין שער ידוע, החזר 1 (לא להמיר)
        Log::warning('No fallback rate available', [
            'from' => $fromCurrency,
            'to' => $toCurrency
        ]);
        
        return 1.0;
    }

    /**
     * קבלת רשימת מטבעות נתמכים
     */
    public function getSupportedCurrencies(): array
    {
        return [
            'USD' => 'US Dollar',
            'EUR' => 'Euro', 
            'ILS' => 'Israeli Shekel',
            'GBP' => 'British Pound',
            'JPY' => 'Japanese Yen',
            'CAD' => 'Canadian Dollar',
            'AUD' => 'Australian Dollar',
            'CHF' => 'Swiss Franc',
        ];
    }

    /**
     * בדיקת תקינות API key
     */
    public function validateApiKey(?string $provider = null): bool
    {
        $provider = $provider ?? $this->defaultProvider;
        
        try {
            $this->fetchExchangeRate('USD', 'EUR', $provider);
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * ניקוי cache שערי החלפה
     */
    public function clearCache(?string $fromCurrency = null, ?string $toCurrency = null): void
    {
        if ($fromCurrency && $toCurrency) {
            foreach ($this->providers as $provider => $url) {
                $cacheKey = "exchange_rate_{$provider}_{$fromCurrency}_{$toCurrency}";
                Cache::forget($cacheKey);
            }
        } else {
            // ניקוי כל הcache
            Cache::flush();
        }
    }

    /**
     * קבלת סטטיסטיקות שימוש
     */
    public function getUsageStats(): array
    {
        return Cache::remember('currency_usage_stats', 300, function () {
            return [
                'total_requests' => Cache::get('currency_total_requests', 0),
                'successful_requests' => Cache::get('currency_successful_requests', 0),
                'failed_requests' => Cache::get('currency_failed_requests', 0),
                'cache_hits' => Cache::get('currency_cache_hits', 0),
                'providers_used' => Cache::get('currency_providers_used', []),
            ];
        });
    }

    /**
     * עדכון מוני שימוש
     */
    protected function updateUsageStats(string $type): void
    {
        Cache::increment("currency_{$type}", 1, 86400); // 24 hours
    }
}
