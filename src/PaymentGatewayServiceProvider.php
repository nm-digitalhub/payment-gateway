<?php

namespace NMDigitalHub\PaymentGateway;

use Illuminate\Console\Scheduling\Schedule;
use NMDigitalHub\PaymentGateway\PaymentGatewayManager;
use NMDigitalHub\PaymentGateway\Console\Commands\SyncProvidersCommand;
use NMDigitalHub\PaymentGateway\Console\Commands\HealthCheckCommand;
use NMDigitalHub\PaymentGateway\Console\Commands\TestProviderCommand;
use NMDigitalHub\PaymentGateway\Console\Commands\CreatePaymentPageCommand;
use NMDigitalHub\PaymentGateway\Console\Commands\InstallCommand;
use Filament\Support\Assets\Css;
use Filament\Support\Assets\Js;
use Filament\Support\Facades\FilamentAsset;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;
use Spatie\LaravelPackageTools\Commands\InstallCommand as SpatieInstallCommand;

class PaymentGatewayServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        /*
         * חבילת שער התשלום המתקדמת עם אינטגרציית CardCom, Maya Mobile, ו-ResellerClub
         * Advanced Payment Gateway Package with CardCom, Maya Mobile, and ResellerClub Integration
         */
        $package
            ->name('payment-gateway')
            ->hasConfigFile('payment-gateway')
            ->hasViews('payment-gateway')
            ->hasMigrations([
                'create_payment_pages_table',
                'create_payment_transactions_table',
                'create_payment_tokens_table',
                'create_service_providers_table',
                'create_webhook_logs_table',
                'create_payment_error_logs_table',
                'create_page_status_logs_table'
            ])
            ->hasAssets()
            ->hasCommands([
                InstallCommand::class,
                SyncProvidersCommand::class,
                HealthCheckCommand::class,
                TestProviderCommand::class,
                CreatePaymentPageCommand::class,
                \NMDigitalHub\PaymentGateway\Console\Commands\CurrencyUpdateCommand::class,
                \NMDigitalHub\PaymentGateway\Console\Commands\SyncPackagesCommand::class,
            ])
            ->hasRoute('web')
            ->hasRoute('api')
            ->hasRoute('client')
            ->hasInstallCommand(function(SpatieInstallCommand $command) {
                $command
                    ->publishConfigFile()
                    ->publishMigrations()
                    ->publishAssets()
                    ->askToRunMigrations()
                    ->askToStarRepoOnGitHub('nmdigitalhub/payment-gateway');
            });
    }

    public function packageRegistered(): void
    {
        // רישום מנהל החבילה
        $this->app->singleton(PaymentGatewayManager::class, function ($app) {
            return new PaymentGatewayManager(
                $app['config']->get('payment-gateway', [])
            );
        });

        // רישום Facade
        $this->app->alias(PaymentGatewayManager::class, 'payment-gateway');

        // רישום Contracts Bindings (P1 Critical)
        $this->registerContractBindings();
    }

    public function packageBooted(): void
    {
        // רישום Middleware
        $this->registerMiddleware();
        
        // רישום Filament Assets
        $this->registerFilamentAssets();

        // רישום משימות מתוזמנות
        $this->scheduleCommands();

        // טעינת Blade Directives
        $this->registerBladeDirectives();

        // רישום Event Listeners
        $this->registerEventListeners();
    }

    /**
     * רישום קישורי Contracts (P1 - חוסם קריטי)
     */
    protected function registerContractBindings(): void
    {
        // Default bindings - מיפוי ברירת מחדל
        $defaults = [
            \NMDigitalHub\PaymentGateway\Contracts\ServiceProviderRepositoryInterface::class => 
                \NMDigitalHub\PaymentGateway\Repositories\Eloquent\ServiceProviderRepository::class,
            \NMDigitalHub\PaymentGateway\Contracts\ApiEndpointRepositoryInterface::class => 
                \NMDigitalHub\PaymentGateway\Repositories\Config\ApiEndpointRepository::class,
            \NMDigitalHub\PaymentGateway\Contracts\PaymentTokenRepositoryInterface::class => 
                \NMDigitalHub\PaymentGateway\Repositories\Eloquent\PaymentTokenRepository::class,
            \NMDigitalHub\PaymentGateway\Contracts\PaymentProviderInterface::class =>
                \NMDigitalHub\PaymentGateway\Providers\CardComProvider::class,
            \NMDigitalHub\PaymentGateway\Contracts\SyncProviderInterface::class =>
                \NMDigitalHub\PaymentGateway\Providers\Services\ResellerClubProvider::class,
            \NMDigitalHub\PaymentGateway\Contracts\SlugGeneratorInterface::class =>
                \NMDigitalHub\PaymentGateway\Services\SlugGeneratorService::class,
            \NMDigitalHub\PaymentGateway\Contracts\PagePublisherInterface::class =>
                \NMDigitalHub\PaymentGateway\Services\PagePublisherService::class,
            \NMDigitalHub\PaymentGateway\Contracts\PricingPolicyInterface::class =>
                \NMDigitalHub\PaymentGateway\Services\SimplePricingPolicyService::class,
            \NMDigitalHub\PaymentGateway\Contracts\ServiceProviderInterface::class =>
                \NMDigitalHub\PaymentGateway\Providers\Services\ResellerClubProvider::class,
            \NMDigitalHub\PaymentGateway\Contracts\CurrencyExchangeInterface::class =>
                \NMDigitalHub\PaymentGateway\Services\CurrencyExchangeService::class,
        ];

        // Register defaults first
        foreach ($defaults as $contract => $implementation) {
            $this->app->bind($contract, $implementation);
        }

        // Override with config bindings if provided
        $configBindings = config('payment-gateway.bindings', []);
        foreach ($configBindings as $contract => $implementation) {
            if (interface_exists($contract) && class_exists($implementation)) {
                $this->app->bind($contract, $implementation);
                \Log::info('Contract binding overridden', [
                    'contract' => $contract,
                    'implementation' => $implementation
                ]);
            } else {
                \Log::warning('Invalid contract binding in config', [
                    'contract' => $contract,
                    'implementation' => $implementation,
                    'interface_exists' => interface_exists($contract),
                    'class_exists' => class_exists($implementation),
                ]);
            }
        }
    }

    protected function registerMiddleware(): void
    {
        // רישום Middleware
        $this->app['router']->aliasMiddleware(
            'payment.page',
            \NMDigitalHub\PaymentGateway\Http\Middleware\PaymentPageMiddleware::class
        );
        
        $this->app['router']->aliasMiddleware(
            'payment.security',
            \NMDigitalHub\PaymentGateway\Http\Middleware\PaymentSecurityMiddleware::class
        );
        
        $this->app['router']->aliasMiddleware(
            'page.redirect',
            \NMDigitalHub\PaymentGateway\Http\Middleware\PageRedirectMiddleware::class
        );

        // רישום ולידטורים בעברית
        if (class_exists('\NMDigitalHub\PaymentGateway\Validators\HebrewValidator')) {
            \NMDigitalHub\PaymentGateway\Validators\HebrewValidator::register();
        }
    }

    protected function registerFilamentAssets(): void
    {
        if (class_exists('\\Filament\\Support\\Facades\\FilamentAsset')) {
            FilamentAsset::register([
                Css::make('payment-gateway-styles', __DIR__.'/../resources/css/payment-gateway.css')
                    ->loadedOnRequest(),
                Js::make('payment-gateway-scripts', __DIR__.'/../resources/js/payment-gateway.js')
                    ->loadedOnRequest(),
            ], package: 'nmdigitalhub/payment-gateway');
        }
    }

    protected function scheduleCommands(): void
    {
        $this->app->booted(function () {
            $schedule = $this->app->make(Schedule::class);

            // בדיקת בריאות ספקים כל 15 דקות
            $schedule->command('payment-gateway:health-check')
                ->everyFifteenMinutes()
                ->withoutOverlapping()
                ->runInBackground();

            // סינכרון אוטומטי פעם ביום
            if (config('payment-gateway.auto_sync', false)) {
                $schedule->command('payment-gateway:sync')
                    ->daily()
                    ->at('02:00')
                    ->withoutOverlapping()
                    ->runInBackground();
            }

            // עדכון שערי המרה כל שעה
            $schedule->command('payment-gateway:currency-update')
                ->hourly()
                ->withoutOverlapping();
        });
    }

    protected function registerBladeDirectives(): void
    {
        \Blade::directive('paymentForm', function ($expression) {
            return "<?php echo app('payment-gateway')->createPaymentForm({$expression}); ?>";
        });

        \Blade::directive('paymentProviders', function () {
            return "<?php echo app('payment-gateway')->getAvailablePaymentProviders()->toJson(); ?>";
        });

        \Blade::directive('paymentPage', function ($expression) {
            return "<?php echo app('payment-gateway')->renderPaymentPage({$expression}); ?>";
        });

        \Blade::directive('currency', function ($expression) {
            return "<?php echo \\NMDigitalHub\\PaymentGateway\\Helpers\\CurrencyHelper::format({$expression}); ?>";
        });

        // הוספת הדירקטיבה החדשה לטוקנים
        \Blade::directive('savedTokens', function ($expression) {
            return "<?php echo app('payment-gateway')->renderSavedTokens({$expression}); ?>";
        });
    }

    protected function registerEventListeners(): void
    {
        // Payment event listeners
        \Event::listen(
            \NMDigitalHub\PaymentGateway\Events\PaymentProcessed::class,
            [\NMDigitalHub\PaymentGateway\Listeners\LogPaymentActivity::class, 'handlePaymentProcessed']
        );
        
        \Event::listen(
            \NMDigitalHub\PaymentGateway\Events\PaymentFailed::class,
            [\NMDigitalHub\PaymentGateway\Listeners\LogPaymentActivity::class, 'handlePaymentFailed']
        );
        
        \Event::listen(
            \NMDigitalHub\PaymentGateway\Events\TokenCreated::class,
            [\NMDigitalHub\PaymentGateway\Listeners\LogPaymentActivity::class, 'handleTokenCreated']
        );

        // Notification listeners
        \Event::listen(
            \NMDigitalHub\PaymentGateway\Events\PaymentProcessed::class,
            [\NMDigitalHub\PaymentGateway\Listeners\SendPaymentNotifications::class, 'handlePaymentProcessed']
        );
        
        \Event::listen(
            \NMDigitalHub\PaymentGateway\Events\PaymentFailed::class,
            [\NMDigitalHub\PaymentGateway\Listeners\SendPaymentNotifications::class, 'handlePaymentFailed']
        );
        
        \Event::listen(
            \NMDigitalHub\PaymentGateway\Events\TokenCreated::class,
            [\NMDigitalHub\PaymentGateway\Listeners\SendPaymentNotifications::class, 'handleTokenCreated']
        );

        // Package sync events
        \Event::listen(
            \NMDigitalHub\PaymentGateway\Events\PackagesSynced::class,
            [\NMDigitalHub\PaymentGateway\Listeners\LogSyncActivity::class, 'handlePackagesSynced']
        );
    }
}