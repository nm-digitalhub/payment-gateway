<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Payment Gateway Enabled
    |--------------------------------------------------------------------------
    |
    | This option determines if the payment gateway system is enabled.
    | When disabled, all payment operations will be blocked.
    |
    */
    
    'enabled' => env('PAYMENT_GATEWAY_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | Default Payment Provider
    |--------------------------------------------------------------------------
    |
    | Here you may specify which of the payment providers below you wish
    | to use as your default provider for all payment operations.
    |
    */

    'default' => env('PAYMENT_GATEWAY_DEFAULT', 'cardcom'),

    /*
    |--------------------------------------------------------------------------
    | Payment Provider Configurations
    |--------------------------------------------------------------------------
    |
    | Here are each of the payment providers setup for your application.
    | Configuration details are loaded from the database via Settings.
    |
    */

    'providers' => [
        'cardcom' => [
            'driver' => 'cardcom',
            'class' => \NMDigitalHub\PaymentGateway\Services\CardComService::class,
            'enabled' => true, // יכול להיות מוחלף בהגדרות פאנל אדמין
            'supports_tokens' => true,
            'supports_3ds' => true,
            'supports_refunds' => true,
            'currency' => 'ILS',
            // כל נתוני החיבור נקבעים דרך App\Settings\CardComSettings
            'settings_class' => \App\Settings\CardComSettings::class,
        ],
        
        'maya_mobile' => [
            'driver' => 'maya_mobile',
            'class' => \NMDigitalHub\PaymentGateway\Services\MayaMobileService::class,
            'enabled' => true, // יכול להיות מוחלף בהגדרות פאנל אדמין
            'supports_tokens' => false,
            'supports_3ds' => false,
            'supports_refunds' => false,
            'currency' => 'USD',
            // כל נתוני החיבור נקבעים דרך App\Settings\MayaMobileSettings
            'settings_class' => \App\Settings\MayaMobileSettings::class,
        ],
        
        'resellerclub' => [
            'driver' => 'resellerclub',
            'class' => \NMDigitalHub\PaymentGateway\Services\ResellerClubService::class,
            'enabled' => true, // יכול להיות מוחלף בהגדרות פאנל אדמין
            'supports_tokens' => false,
            'supports_3ds' => false,
            'supports_refunds' => true,
            'currency' => 'USD',
            // כל נתוני החיבור נקבעים דרך App\Settings\ResellerClubSettings
            'settings_class' => \App\Settings\ResellerClubSettings::class,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Auto Sync Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for automatic catalog synchronization from providers
    |
    */

    'auto_sync' => env('PAYMENT_GATEWAY_AUTO_SYNC', false),
    
    'sync' => [
        'enabled' => env('PAYMENT_GATEWAY_SYNC_ENABLED', true),
        'interval' => env('PAYMENT_GATEWAY_SYNC_INTERVAL', 'daily'),
        'time' => env('PAYMENT_GATEWAY_SYNC_TIME', '02:00'),
        'limit' => env('PAYMENT_GATEWAY_SYNC_LIMIT', 100),
        'timeout' => env('PAYMENT_GATEWAY_SYNC_TIMEOUT', 300), // 5 minutes
    ],

    /*
    |--------------------------------------------------------------------------
    | Health Check Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for provider health checks
    |
    */

    'health_check' => [
        'enabled' => env('PAYMENT_GATEWAY_HEALTH_CHECK', true),
        'interval' => env('PAYMENT_GATEWAY_HEALTH_INTERVAL', '*/15'), // Every 15 minutes
        'timeout' => env('PAYMENT_GATEWAY_HEALTH_TIMEOUT', 30), // 30 seconds
        'retry_attempts' => env('PAYMENT_GATEWAY_HEALTH_RETRY', 3),
    ],

    /*
    |--------------------------------------------------------------------------
    | Webhook Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for webhook processing
    |
    */

    'webhooks' => [
        'enabled' => env('PAYMENT_GATEWAY_WEBHOOKS_ENABLED', true),
        'verify_signature' => env('PAYMENT_GATEWAY_VERIFY_SIGNATURE', true),
        'rate_limit' => env('PAYMENT_GATEWAY_WEBHOOK_RATE_LIMIT', '60,1'), // 60 per minute
        'timeout' => env('PAYMENT_GATEWAY_WEBHOOK_TIMEOUT', 30),
        'log_requests' => env('PAYMENT_GATEWAY_LOG_WEBHOOKS', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Payment Pages Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for public payment pages
    |
    */

    'payment_pages' => [
        'enabled' => env('PAYMENT_GATEWAY_PAGES_ENABLED', true),
        'require_auth' => env('PAYMENT_GATEWAY_PAGES_AUTH', false),
        'cache_ttl' => env('PAYMENT_GATEWAY_PAGES_CACHE', 3600), // 1 hour
        'theme' => env('PAYMENT_GATEWAY_THEME', 'default'),
        'rtl_support' => env('PAYMENT_GATEWAY_RTL', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Security Configuration
    |--------------------------------------------------------------------------
    |
    | Security settings for the payment gateway
    |
    */

    'security' => [
        'encryption_key' => env('PAYMENT_GATEWAY_ENCRYPTION_KEY'),
        'hmac_secret' => env('PAYMENT_GATEWAY_HMAC_SECRET'),
        'session_timeout' => env('PAYMENT_GATEWAY_SESSION_TIMEOUT', 1800), // 30 minutes
        'max_attempts' => env('PAYMENT_GATEWAY_MAX_ATTEMPTS', 3),
        'lockout_duration' => env('PAYMENT_GATEWAY_LOCKOUT', 900), // 15 minutes
    ],

    /*
    |--------------------------------------------------------------------------
    | Filament Integration
    |--------------------------------------------------------------------------
    |
    | Configuration for Filament admin panel integration
    |
    */

    'filament' => [
        'enabled' => env('PAYMENT_GATEWAY_FILAMENT', true),
        'admin_panel' => env('PAYMENT_GATEWAY_ADMIN_PANEL', true),
        'client_panel' => env('PAYMENT_GATEWAY_CLIENT_PANEL', true),
        'navigation_group' => 'תשלומים',
        'navigation_sort' => 10,
        'navigation_icon' => 'heroicon-o-credit-card',
    ],

    /*
    |--------------------------------------------------------------------------
    | Logging Configuration
    |--------------------------------------------------------------------------
    |
    | Configure logging for payment gateway operations
    |
    */

    'logging' => [
        'enabled' => env('PAYMENT_GATEWAY_LOGGING', true),
        'channel' => env('PAYMENT_GATEWAY_LOG_CHANNEL', 'stack'),
        'level' => env('PAYMENT_GATEWAY_LOG_LEVEL', 'info'),
        'include_sensitive' => env('PAYMENT_GATEWAY_LOG_SENSITIVE', false),
        'retention_days' => env('PAYMENT_GATEWAY_LOG_RETENTION', 30),
    ],

    /*
    |--------------------------------------------------------------------------
    | Cache Configuration
    |--------------------------------------------------------------------------
    |
    | Configure caching for payment gateway data
    |
    */

    'cache' => [
        'enabled' => env('PAYMENT_GATEWAY_CACHE', true),
        'store' => env('PAYMENT_GATEWAY_CACHE_STORE', 'default'),
        'ttl' => env('PAYMENT_GATEWAY_CACHE_TTL', 3600), // 1 hour
        'prefix' => env('PAYMENT_GATEWAY_CACHE_PREFIX', 'payment_gateway'),
        'tags' => ['payment-gateway', 'providers', 'catalog'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Queue Configuration
    |--------------------------------------------------------------------------
    |
    | Configure queue processing for payment operations
    |
    */

    'queue' => [
        'enabled' => env('PAYMENT_GATEWAY_QUEUE', true),
        'connection' => env('PAYMENT_GATEWAY_QUEUE_CONNECTION', 'default'),
        'queue_name' => env('PAYMENT_GATEWAY_QUEUE_NAME', 'payments'),
        'max_tries' => env('PAYMENT_GATEWAY_QUEUE_TRIES', 3),
        'timeout' => env('PAYMENT_GATEWAY_QUEUE_TIMEOUT', 300), // 5 minutes
    ],

    /*
    |--------------------------------------------------------------------------
    | Localization
    |--------------------------------------------------------------------------
    |
    | Localization settings for the payment gateway
    |
    */

    'locale' => [
        'default' => env('PAYMENT_GATEWAY_LOCALE', 'he'),
        'fallback' => env('PAYMENT_GATEWAY_FALLBACK_LOCALE', 'en'),
        'supported' => ['he', 'en', 'fr'],
        'date_format' => env('PAYMENT_GATEWAY_DATE_FORMAT', 'd/m/Y'),
        'currency_format' => env('PAYMENT_GATEWAY_CURRENCY_FORMAT', '₪%s'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Currency Exchange Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for real-time currency exchange rates
    |
    */

    'currency' => [
        'enabled' => env('PAYMENT_GATEWAY_CURRENCY_ENABLED', true),
        'default_provider' => env('PAYMENT_GATEWAY_CURRENCY_PROVIDER', 'fixer'),
        'api_key' => env('PAYMENT_GATEWAY_CURRENCY_API_KEY'),
        'cache_ttl' => env('PAYMENT_GATEWAY_CURRENCY_CACHE_TTL', 3600), // 1 hour
        'fallback_enabled' => env('PAYMENT_GATEWAY_CURRENCY_FALLBACK', true),
        'timeout' => env('PAYMENT_GATEWAY_CURRENCY_TIMEOUT', 10), // seconds
        'providers' => [
            'fixer' => [
                'name' => 'Fixer.io',
                'url' => 'https://api.fixer.io/v1',
                'requires_key' => true,
                'free_tier' => 1000, // requests per month
            ],
            'exchangerate' => [
                'name' => 'ExchangeRate-API',
                'url' => 'https://v6.exchangerate-api.com/v6',
                'requires_key' => true,
                'free_tier' => 1500,
            ],
            'currencylayer' => [
                'name' => 'CurrencyLayer',
                'url' => 'https://api.currencylayer.com',
                'requires_key' => true,
                'free_tier' => 1000,
            ],
            'openexchange' => [
                'name' => 'Open Exchange Rates',
                'url' => 'https://openexchangerates.org/api',
                'requires_key' => true,
                'free_tier' => 1000,
            ],
        ],
        'supported_currencies' => [
            'USD', 'EUR', 'ILS', 'GBP', 'JPY', 'CAD', 'AUD', 'CHF', 'CNY', 'INR'
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Contract Bindings
    |--------------------------------------------------------------------------
    |
    | Override default implementations for package contracts
    |
    */

    'bindings' => [
        // Example: Override currency exchange service
        // NMDigitalHub\PaymentGateway\Contracts\CurrencyExchangeInterface::class => 
        //     App\Services\CustomCurrencyExchangeService::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Development & Debug
    |--------------------------------------------------------------------------
    |
    | Development and debugging configuration
    |
    */

    'debug' => [
        'enabled' => env('PAYMENT_GATEWAY_DEBUG', env('APP_DEBUG', false)),
        'log_queries' => env('PAYMENT_GATEWAY_DEBUG_QUERIES', false),
        'mock_responses' => env('PAYMENT_GATEWAY_MOCK', false),
        'test_mode' => env('PAYMENT_GATEWAY_TEST_MODE', false),
    ],
];