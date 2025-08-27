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
                \NMDigitalHub\PaymentGateway\Console\Commands\TestApiConnectionsCommand::class,
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

        // רישום שירותי API חדשים
        $this->registerApiServices();
        
        // רישום קונטרולרי חבילות
        $this->registerPackageControllers();

        // רישום Contracts Bindings (P1 Critical)
        $this->registerContractBindings();
    }

    public function packageBooted(): void
    {
        // רישום Middleware
        $this->registerMiddleware();
        
        // רישום Filament Assets
        $this->registerFilamentAssets();
        
        // רישום Filament Resources - חדש!
        $this->registerFilamentResources();

        // רישום משימות מתוזמנות
        $this->scheduleCommands();

        // טעינת Blade Directives
        $this->registerBladeDirectives();

        // רישום Event Listeners
        $this->registerEventListeners();
    }

    /**
     * רישום שירותי API המותאמים אישית
     */
    protected function registerApiServices(): void
    {
        // רישום CardComService
        $this->app->singleton(\NMDigitalHub\PaymentGateway\Services\CardComService::class, function ($app) {
            return new \NMDigitalHub\PaymentGateway\Services\CardComService();
        });

        // רישום MayaMobileService
        $this->app->singleton(\NMDigitalHub\PaymentGateway\Services\MayaMobileService::class, function ($app) {
            return new \NMDigitalHub\PaymentGateway\Services\MayaMobileService();
        });

        // רישום ResellerClubService
        $this->app->singleton(\NMDigitalHub\PaymentGateway\Services\ResellerClubService::class, function ($app) {
            return new \NMDigitalHub\PaymentGateway\Services\ResellerClubService();
        });

        \Log::info('Payment Gateway: API Services registered successfully', [
            'services' => [
                'CardComService',
                'MayaMobileService', 
                'ResellerClubService'
            ]
        ]);
    }

    /**
     * רישום קונטרולרי חבילות החדשים
     */
    protected function registerPackageControllers(): void
    {
        // רישום PackageCheckoutController
        $this->app->singleton(\NMDigitalHub\PaymentGateway\Http\Controllers\PackageCheckoutController::class, function ($app) {
            return new \NMDigitalHub\PaymentGateway\Http\Controllers\PackageCheckoutController(
                $app->make(\NMDigitalHub\PaymentGateway\Services\CardComService::class),
                $app->make(\NMDigitalHub\PaymentGateway\Services\MayaMobileService::class),
                $app->make(\NMDigitalHub\PaymentGateway\Services\ResellerClubService::class),
                $app->make(PaymentGatewayManager::class)
            );
        });

        // רישום PackageCatalogController
        $this->app->singleton(\NMDigitalHub\PaymentGateway\Http\Controllers\PackageCatalogController::class, function ($app) {
            return new \NMDigitalHub\PaymentGateway\Http\Controllers\PackageCatalogController(
                $app->make(\NMDigitalHub\PaymentGateway\Services\CardComService::class),
                $app->make(\NMDigitalHub\PaymentGateway\Services\MayaMobileService::class),
                $app->make(\NMDigitalHub\PaymentGateway\Services\ResellerClubService::class)
            );
        });

        // רישום PaymentHandlerController
        $this->app->singleton(\NMDigitalHub\PaymentGateway\Http\Controllers\PaymentHandlerController::class, function ($app) {
            return new \NMDigitalHub\PaymentGateway\Http\Controllers\PaymentHandlerController(
                $app->make(\NMDigitalHub\PaymentGateway\Services\CardComService::class),
                $app->make(PaymentGatewayManager::class)
            );
        });

        \Log::info('Payment Gateway: Package Controllers registered successfully', [
            'controllers' => [
                'PackageCheckoutController',
                'PackageCatalogController',
                'PaymentHandlerController'
            ]
        ]);
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

    /**
     * רישום משאבי Filament באופן אוטומטי
     */
    protected function registerFilamentResources(): void
    {
        // בדיקה שFilament קיים ומותקן
        if (!class_exists('\\Filament\\Filament')) {
            return;
        }

        // בדיקת הגדרות החבילה
        if (!config('payment-gateway.filament.enabled', true)) {
            return;
        }

        // רישום משאבי פאנל אדמין
        if (config('payment-gateway.filament.admin_panel', true)) {
            $this->registerAdminResources();
        }

        // רישום משאבי פאנל לקוחות
        if (config('payment-gateway.filament.client_panel', true)) {
            $this->registerClientResources();
        }
    }

    /**
     * רישום משאבי פאנל אדמין - Filament v3 compatible
     */
    protected function registerAdminResources(): void
    {
        // בדיקה שפאנל האדמין קיים
        if (!class_exists('\\App\\Filament\\AdminPanelProvider')) {
            return;
        }

        try {
            // רישום ישיר של המשאבים לפאנל האדמין
            $adminPanel = \Filament\Facades\Filament::getPanel('admin');
            
            if ($adminPanel) {
                // רישום PaymentPageResource
                if (class_exists('\\NMDigitalHub\\PaymentGateway\\Filament\\Resources\\PaymentPageResource')) {
                    $adminPanel->resources([
                        \NMDigitalHub\PaymentGateway\Filament\Resources\PaymentPageResource::class,
                    ]);
                }

                // רישום PaymentTransactionResource
                if (class_exists('\\NMDigitalHub\\PaymentGateway\\Filament\\Resources\\PaymentTransactionResource')) {
                    $adminPanel->resources([
                        \NMDigitalHub\PaymentGateway\Filament\Resources\PaymentTransactionResource::class,
                    ]);
                }
            }

            \Log::info('Payment Gateway: Admin resources registered successfully');

        } catch (\Exception $e) {
            \Log::warning('Payment Gateway: Failed to register admin resources', [
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * רישום משאבי פאנל לקוחות
     */
    protected function registerClientResources(): void
    {
        // בדיקה שפאנל הלקוחות קיים
        if (!class_exists('\\App\\Filament\\ClientPanelProvider')) {
            return;
        }

        try {
            // רישום ClientPaymentPageResource
            if (class_exists('\\NMDigitalHub\\PaymentGateway\\Filament\\Client\\Resources\\ClientPaymentPageResource')) {
                \Filament\Facades\Filament::serving(function () {
                    \Filament\Facades\Filament::registerResources([
                        \NMDigitalHub\PaymentGateway\Filament\Client\Resources\ClientPaymentPageResource::class,
                    ]);
                });
            }

            // רישום ClientPaymentTransactionResource
            if (class_exists('\\NMDigitalHub\\PaymentGateway\\Filament\\Client\\Resources\\ClientPaymentTransactionResource')) {
                \Filament\Facades\Filament::serving(function () {
                    \Filament\Facades\Filament::registerResources([
                        \NMDigitalHub\PaymentGateway\Filament\Client\Resources\ClientPaymentTransactionResource::class,
                    ]);
                });
            }

            \Log::info('Payment Gateway: Client resources registered successfully');

        } catch (\Exception $e) {
            \Log::warning('Payment Gateway: Failed to register client resources', [
                'error' => $e->getMessage()
            ]);
        }
    }
}