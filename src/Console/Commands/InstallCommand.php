<?php

namespace NMDigitalHub\PaymentGateway\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class InstallCommand extends Command
{
    protected $signature = 'payment-gateway:install 
                            {--force : Force the installation even if already installed}
                            {--demo : Install with demo data}
                            {--skip-migrations : Skip running migrations}
                            {--skip-publish : Skip publishing files}
                            {--optimize : Run optimization after installation}
                            {--verbose : Show detailed installation progress}';
    
    protected $description = 'התקן את חבילת Payment Gateway עם כל הרכיבים הנדרשים';

    public function handle(): int
    {
        $this->info('🚀 מתחיל התקנת Payment Gateway...');
        
        // בדיקה אם כבר מותקן
        if ($this->isAlreadyInstalled() && !$this->option('force')) {
            $this->warn('⚠️  Payment Gateway כבר מותקן!');
            
            if (!$this->confirm('האם תרצה להמשיך בכל זאת? (זה יעריף על ההגדרות הקיימות)')) {
                return self::SUCCESS;
            }
        }

        try {
            // שלב 1: בדיקת prerequisites
            $this->checkPrerequisites();
            
            // שלב 2: פרסום קבצים
            $this->publishFiles();
            
            // שלב 3: הרצת migrations
            if (!$this->option('skip-migrations')) {
                $this->runMigrations();
            }
            
            // שלב 4: הגדרת ספקי שירות
            $this->setupServiceProviders();
            
            // שלב 5: יצירת נתוני דמו
            if ($this->option('with-demo')) {
                $this->createDemoData();
            }
            
            // שלב 6: עדכון composer
            $this->updateComposer();
            
            // שלב 7: הודעת סיום
            $this->displaySuccessMessage();
            
            return self::SUCCESS;
            
        } catch (\Exception $e) {
            $this->error('❌ שגיאה בהתקנה: ' . $e->getMessage());
            $this->error('💡 נסה להריץ: php artisan payment-gateway:install --force');
            
            return self::FAILURE;
        }
    }

    protected function checkPrerequisites(): void
    {
        $this->info('🔍 בודק דרישות מוקדמות...');
        
        // בדיקת Laravel version
        if (version_compare(app()->version(), '10.0.0', '<')) {
            throw new \Exception('Payment Gateway דורש Laravel 10.0 ומעלה');
        }
        
        // בדיקת PHP version
        if (version_compare(PHP_VERSION, '8.1.0', '<')) {
            throw new \Exception('Payment Gateway דורש PHP 8.1 ומעלה');
        }
        
        // בדיקת Filament
        if (!class_exists('\\Filament\\FilamentServiceProvider')) {
            throw new \Exception('Payment Gateway דורש Filament v3. התקן עם: composer require filament/filament');
        }
        
        // בדיקת מודלים נדרשים
        $requiredModels = [
            'App\\Models\\User',
            'App\\Models\\ServiceProvider',
            'App\\Models\\ApiEndpoint'
        ];
        
        foreach ($requiredModels as $model) {
            if (!class_exists($model)) {
                $this->warn("⚠️  מודל {$model} לא נמצא - יווצר אוטומטית");
            }
        }
        
        $this->info('✅ כל הדרישות מתקיימות!');
    }

    protected function publishFiles(): void
    {
        $this->info('📁 מפרסם קבצים...');
        
        // פרסום migrations
        $this->publishMigrations();
        
        // פרסום config
        $this->publishConfig();
        
        // פרסום views
        $this->publishViews();
        
        // פרסום assets
        $this->publishAssets();
        
        $this->info('✅ כל הקבצים פורסמו!');
    }

    protected function publishMigrations(): void
    {
        $migrations = [
            'create_payment_transactions_table' => 'payment_transactions',
            'create_payment_pages_table' => 'payment_pages',
            'create_payment_tokens_table' => 'payment_tokens'
        ];

        $migrationsPath = database_path('migrations');
        
        foreach ($migrations as $stub => $table) {
            $timestamp = now()->addSecond()->format('Y_m_d_His');
            $filename = "{$timestamp}_{$stub}.php";
            $targetPath = "{$migrationsPath}/{$filename}";
            
            if (!File::exists($targetPath)) {
                $stubContent = $this->getMigrationStub($stub, $table);
                File::put($targetPath, $stubContent);
                $this->line("✅ Migration: {$filename}");
            }
        }
    }

    protected function publishConfig(): void
    {
        $configPath = config_path('payment-gateway.php');
        
        if (!File::exists($configPath) || $this->option('force')) {
            $configContent = $this->getConfigStub();
            File::put($configPath, $configContent);
            $this->line('✅ Config: payment-gateway.php');
        }
    }

    protected function publishViews(): void
    {
        $viewsPath = resource_path('views/payment-gateway');
        
        if (!File::exists($viewsPath)) {
            File::makeDirectory($viewsPath, 0755, true);
        }
        
        $views = [
            'checkout/page.blade.php' => $this->getCheckoutPageView(),
            'checkout/success.blade.php' => $this->getSuccessPageView(),
            'checkout/failed.blade.php' => $this->getFailedPageView(),
            'layouts/checkout.blade.php' => $this->getCheckoutLayoutView()
        ];
        
        foreach ($views as $viewFile => $content) {
            $targetPath = "{$viewsPath}/{$viewFile}";
            $directory = dirname($targetPath);
            
            if (!File::exists($directory)) {
                File::makeDirectory($directory, 0755, true);
            }
            
            File::put($targetPath, $content);
            $this->line("✅ View: {$viewFile}");
        }
    }

    protected function publishAssets(): void
    {
        $assetsPath = public_path('vendor/payment-gateway');
        
        if (!File::exists($assetsPath)) {
            File::makeDirectory($assetsPath, 0755, true);
        }
        
        // CSS
        $cssContent = $this->getPaymentCssStub();
        File::put("{$assetsPath}/payment-gateway.css", $cssContent);
        
        // JS
        $jsContent = $this->getPaymentJsStub();
        File::put("{$assetsPath}/payment-gateway.js", $jsContent);
        
        $this->line('✅ Assets: CSS & JS files');
    }

    protected function runMigrations(): void
    {
        $this->info('🗃️  מריץ migrations...');
        
        try {
            Artisan::call('migrate', ['--force' => true]);
            $this->info('✅ Migrations הורצו בהצלחה!');
        } catch (\Exception $e) {
            throw new \Exception('שגיאה בהרצת migrations: ' . $e->getMessage());
        }
    }

    protected function setupServiceProviders(): void
    {
        $this->info('⚙️  מגדיר ספקי שירות...');
        
        $providers = [
            [
                'name' => 'cardcom',
                'type' => 'payment',
                'display_name' => 'CardCom',
                'is_active' => true,
                'configuration' => [
                    'terminal_number' => '172204',
                    'api_name' => 'wr3UAE33TuvTEULxUYkt',
                    'base_url' => 'https://secure.cardcom.solutions/api/v11'
                ]
            ],
            [
                'name' => 'maya_mobile',
                'type' => 'service', 
                'display_name' => 'Maya Mobile',
                'is_active' => true,
                'configuration' => []
            ],
            [
                'name' => 'resellerclub',
                'type' => 'service',
                'display_name' => 'ResellerClub',
                'is_active' => true,
                'configuration' => []
            ]
        ];
        
        foreach ($providers as $providerData) {
            if (class_exists('\\App\\Models\\ServiceProvider')) {
                \App\Models\ServiceProvider::updateOrCreate(
                    ['name' => $providerData['name']],
                    $providerData
                );
                $this->line("✅ Provider: {$providerData['display_name']}");
            }
        }
    }

    protected function createDemoData(): void
    {
        $this->info('🎭 יוצר נתוני דמו...');
        
        // יצירת דף תשלום לדמו
        if (class_exists('\\NMDigitalHub\\PaymentGateway\\Models\\PaymentPage')) {
            \NMDigitalHub\PaymentGateway\Models\PaymentPage::updateOrCreate(
                ['slug' => 'demo-checkout'],
                [
                    'title' => 'דף תשלום לדמו',
                    'description' => 'דף תשלום לבדיקות ודמו',
                    'type' => 'checkout',
                    'status' => 'published',
                    'is_public' => true,
                    'language' => 'he',
                    'content' => [
                        [
                            'type' => 'heading',
                            'data' => ['content' => 'תשלום מאובטח', 'level' => 'h1']
                        ],
                        [
                            'type' => 'paragraph', 
                            'data' => ['content' => 'מלא את הפרטים למטה לביצוע התשלום']
                        ],
                        [
                            'type' => 'payment_form',
                            'data' => ['allowed_methods' => ['cardcom']]
                        ]
                    ]
                ]
            );
            
            $this->line('✅ נוצר דף תשלום דמו: /payment/demo-checkout');
        }
    }

    protected function updateComposer(): void
    {
        $this->info('📦 מעדכן composer autoload...');
        
        try {
            exec('composer dump-autoload', $output, $return);
            if ($return === 0) {
                $this->info('✅ Composer autoload עודכן!');
            }
        } catch (\Exception $e) {
            $this->warn('⚠️  לא הצלחתי לעדכן composer autoload. הרץ: composer dump-autoload');
        }
    }

    protected function isAlreadyInstalled(): bool
    {
        return Schema::hasTable('payment_transactions') && 
               Schema::hasTable('payment_pages') &&
               File::exists(config_path('payment-gateway.php'));
    }

    protected function displaySuccessMessage(): void
    {
        $this->info('');
        $this->info('🎉 Payment Gateway הותקן בהצלחה!');
        $this->info('');
        $this->info('🔗 צעדים הבאים:');
        $this->line('   1. הגדר את פרטי CardCom ב: config/payment-gateway.php');
        $this->line('   2. הוסף routes ל: routes/web.php:');
        $this->line('      Route::get("/payment/{slug}", [CheckoutController::class, "showPaymentPage"]);');
        $this->line('   3. בדוק את הפאנל אדמין: /admin/payment-transactions');
        
        if ($this->option('with-demo')) {
            $this->line('   4. בדוק דף הדמו: /payment/demo-checkout');
        }
        
        $this->info('');
        $this->info('📖 תיעוד מלא זמין ב: /docs/payment-gateway');
        $this->info('🛠️  לעזרה: php artisan payment-gateway:help');
    }

    // Stub Methods
    protected function getMigrationStub(string $stub, string $table): string
    {
        switch ($stub) {
            case 'create_payment_transactions_table':
                return $this->getPaymentTransactionsMigration();
            case 'create_payment_pages_table':
                return $this->getPaymentPagesMigration();
            case 'create_payment_tokens_table':
                return $this->getPaymentTokensMigration();
            default:
                throw new \Exception("Unknown migration stub: {$stub}");
        }
    }

    protected function getPaymentTransactionsMigration(): string
    {
        return <<<'PHP'
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_transactions', function (Blueprint $table) {
            $table->id();
            $table->string('transaction_id')->unique();
            $table->string('reference')->index();
            $table->string('provider')->index();
            $table->decimal('amount', 10, 2);
            $table->string('currency', 3)->default('ILS');
            $table->string('status')->index();
            $table->string('customer_email')->index();
            $table->string('customer_name');
            $table->string('customer_phone')->nullable();
            $table->string('gateway_transaction_id')->nullable()->index();
            $table->string('authorization_code')->nullable();
            $table->text('failure_reason')->nullable();
            $table->json('metadata')->nullable();
            $table->json('gateway_response')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            
            $table->index(['status', 'created_at']);
            $table->index(['customer_email', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_transactions');
    }
};
PHP;
    }

    protected function getPaymentPagesMigration(): string
    {
        return <<<'PHP'
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_pages', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->string('type')->index();
            $table->string('status')->index();
            $table->string('template')->default('default');
            $table->string('language')->default('he');
            $table->boolean('is_public')->default(true);
            $table->boolean('require_auth')->default(false);
            $table->json('content')->nullable();
            $table->json('seo_meta')->nullable();
            $table->text('custom_css')->nullable();
            $table->text('custom_js')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
            
            $table->index(['status', 'is_public']);
            $table->index(['type', 'language']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_pages');
    }
};
PHP;
    }

    protected function getPaymentTokensMigration(): string
    {
        return <<<'PHP'
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_tokens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('gateway')->index();
            $table->string('cardcom_token')->nullable();
            $table->string('card_last_four', 4)->nullable();
            $table->string('card_brand')->nullable();
            $table->string('card_year', 4)->nullable();
            $table->string('card_month', 2)->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('is_default')->default(false);
            $table->timestamp('expires_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            
            $table->index(['user_id', 'is_active']);
            $table->index(['gateway', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_tokens');
    }
};
PHP;
    }

    protected function getConfigStub(): string
    {
        return <<<'PHP'
<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Payment Gateway Configuration
    |--------------------------------------------------------------------------
    */
    
    'default_currency' => 'ILS',
    'default_language' => 'he',
    
    'cardcom' => [
        'terminal_number' => env('CARDCOM_TERMINAL_NUMBER', '172204'),
        'api_name' => env('CARDCOM_API_NAME', 'wr3UAE33TuvTEULxUYkt'),
        'api_password' => env('CARDCOM_API_PASSWORD', 'c7QOyJ5vyiDwz5mbMjUt'),
        'base_url' => env('CARDCOM_BASE_URL', 'https://secure.cardcom.solutions/api/v11'),
        'webhook_verification' => true,
    ],
    
    'routes' => [
        'prefix' => 'payment',
        'middleware' => ['web'],
        'success_url' => '/payment/success',
        'failed_url' => '/payment/failed',
        'webhook_url' => '/webhooks/cardcom',
    ],
    
    'features' => [
        'auto_sync' => false,
        'cache_duration' => 3600,
        'token_management' => true,
        'public_pages' => true,
    ],
];
PHP;
    }

    protected function getCheckoutPageView(): string
    {
        return <<<'BLADE'
@extends('payment-gateway::layouts.checkout')

@section('title', $page->title)

@section('content')
<div class="payment-page" dir="rtl">
    <div class="container mx-auto px-4 py-8">
        <div class="max-w-2xl mx-auto">
            <div class="bg-white rounded-lg shadow-lg p-6">
                <h1 class="text-2xl font-bold text-gray-900 mb-4">{{ $page->title }}</h1>
                
                @if($page->description)
                    <p class="text-gray-600 mb-6">{{ $page->description }}</p>
                @endif
                
                <form id="payment-form" class="space-y-6">
                    @csrf
                    
                    <!-- Customer Information -->
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">שם מלא *</label>
                            <input type="text" name="customer_name" required 
                                   class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">אימייל *</label>
                            <input type="email" name="customer_email" required
                                   class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500">
                        </div>
                    </div>
                    
                    <!-- Amount -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">סכום *</label>
                        <div class="relative">
                            <input type="number" name="amount" step="0.01" min="0.01" required
                                   class="w-full px-3 py-2 pl-12 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500">
                            <span class="absolute left-3 top-2 text-gray-500">₪</span>
                        </div>
                    </div>
                    
                    <!-- Payment Method -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">אמצעי תשלום</label>
                        
                        @if(auth()->check() && count($savedTokens) > 0)
                            <!-- Saved Tokens -->
                            <div class="space-y-3 mb-4">
                                @foreach($savedTokens as $token)
                                    <label class="flex items-center p-3 border rounded-md hover:bg-gray-50 cursor-pointer">
                                        <input type="radio" name="payment_method_type" value="saved_token" 
                                               data-token-id="{{ $token['id'] }}" class="ml-3">
                                        <div class="flex-1">
                                            <div class="font-medium">{{ $token['card_brand'] }} **** {{ $token['card_last_four'] }}</div>
                                            <div class="text-sm text-gray-500">תפוגה: {{ $token['expires_at'] }}</div>
                                        </div>
                                    </label>
                                @endforeach
                            </div>
                            
                            <!-- CVV for saved token -->
                            <div id="saved-token-cvv" class="hidden mb-4">
                                <label class="block text-sm font-medium text-gray-700 mb-2">קוד CVV *</label>
                                <input type="text" name="cvv" maxlength="4" pattern="[0-9]{3,4}"
                                       class="w-32 px-3 py-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500">
                            </div>
                        @endif
                        
                        <!-- New Card -->
                        <label class="flex items-center p-3 border rounded-md hover:bg-gray-50 cursor-pointer">
                            <input type="radio" name="payment_method_type" value="new_card" 
                                   {{ count($savedTokens) == 0 ? 'checked' : '' }} class="ml-3">
                            <span>כרטיס אשראי חדש</span>
                        </label>
                        
                        @if(auth()->check())
                            <label class="flex items-center mt-3">
                                <input type="checkbox" name="save_payment_method" class="ml-2">
                                <span class="text-sm">שמור אמצעי תשלום לעתיד</span>
                            </label>
                        @endif
                    </div>
                    
                    <button type="submit" class="w-full bg-blue-600 text-white py-3 px-4 rounded-md hover:bg-blue-700 focus:ring-4 focus:ring-blue-200">
                        המשך לתשלום
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script src="{{ asset('vendor/payment-gateway/payment-gateway.js') }}"></script>
@endpush
BLADE;
    }

    protected function getSuccessPageView(): string
    {
        return <<<'BLADE'
@extends('payment-gateway::layouts.checkout')

@section('title', 'תשלום בוצע בהצלחה')

@section('content')
<div class="success-page" dir="rtl">
    <div class="container mx-auto px-4 py-8">
        <div class="max-w-md mx-auto text-center">
            <div class="bg-white rounded-lg shadow-lg p-8">
                <div class="text-green-500 mb-4">
                    <svg class="w-16 h-16 mx-auto" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path>
                    </svg>
                </div>
                
                <h1 class="text-2xl font-bold text-gray-900 mb-2">תשלום בוצע בהצלחה!</h1>
                <p class="text-gray-600 mb-6">התשלום שלך עובד בהצלחה</p>
                
                @if($transaction)
                    <div class="bg-gray-50 rounded-md p-4 mb-6">
                        <div class="text-sm text-gray-600">
                            <div><strong>מספר עסקה:</strong> {{ $transaction->reference }}</div>
                            <div><strong>סכום:</strong> ₪{{ number_format($transaction->amount, 2) }}</div>
                            <div><strong>תאריך:</strong> {{ $transaction->completed_at?->format('d/m/Y H:i') }}</div>
                        </div>
                    </div>
                @endif
                
                <a href="{{ url('/') }}" class="inline-block bg-blue-600 text-white px-6 py-2 rounded-md hover:bg-blue-700">
                    חזרה לעמוד הבית
                </a>
            </div>
        </div>
    </div>
</div>
@endsection
BLADE;
    }

    protected function getFailedPageView(): string
    {
        return <<<'BLADE'
@extends('payment-gateway::layouts.checkout')

@section('title', 'תשלום נכשל')

@section('content')
<div class="failed-page" dir="rtl">
    <div class="container mx-auto px-4 py-8">
        <div class="max-w-md mx-auto text-center">
            <div class="bg-white rounded-lg shadow-lg p-8">
                <div class="text-red-500 mb-4">
                    <svg class="w-16 h-16 mx-auto" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"></path>
                    </svg>
                </div>
                
                <h1 class="text-2xl font-bold text-gray-900 mb-2">התשלום נכשל</h1>
                <p class="text-gray-600 mb-6">{{ $errorMessage ?? 'אירעה שגיאה בעיבוד התשלום' }}</p>
                
                <div class="space-y-3">
                    <a href="javascript:history.back()" class="block bg-blue-600 text-white px-6 py-2 rounded-md hover:bg-blue-700">
                        נסה שוב
                    </a>
                    <a href="{{ url('/') }}" class="block text-gray-600 hover:text-gray-800">
                        חזרה לעמוד הבית
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
BLADE;
    }

    protected function getCheckoutLayoutView(): string
    {
        return <<<'BLADE'
<!DOCTYPE html>
<html lang="he" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'Payment Gateway')</title>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="{{ asset('vendor/payment-gateway/payment-gateway.css') }}" rel="stylesheet">
</head>
<body class="bg-gray-100 min-h-screen">
    @yield('content')
    
    @stack('scripts')
</body>
</html>
BLADE;
    }

    protected function getPaymentCssStub(): string
    {
        return <<<'CSS'
/* Payment Gateway Styles */
.payment-page {
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
}

.payment-form input:focus {
    outline: none;
    border-color: #3b82f6;
    box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
}

.payment-method-option {
    transition: all 0.2s ease;
}

.payment-method-option:hover {
    background-color: #f8fafc;
    border-color: #3b82f6;
}

.success-animation {
    animation: successPulse 0.6s ease-in-out;
}

@keyframes successPulse {
    0% { transform: scale(0.8); opacity: 0; }
    50% { transform: scale(1.05); opacity: 1; }
    100% { transform: scale(1); opacity: 1; }
}
CSS;
    }

    protected function getPaymentJsStub(): string
    {
        return <<<'JS'
// Payment Gateway JavaScript
document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('payment-form');
    const paymentMethodInputs = document.querySelectorAll('input[name="payment_method_type"]');
    const cvvField = document.getElementById('saved-token-cvv');
    
    // Handle payment method selection
    paymentMethodInputs.forEach(input => {
        input.addEventListener('change', function() {
            if (this.value === 'saved_token') {
                cvvField?.classList.remove('hidden');
            } else {
                cvvField?.classList.add('hidden');
            }
        });
    });
    
    // Handle form submission
    if (form) {
        form.addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const formData = new FormData(form);
            const data = Object.fromEntries(formData.entries());
            
            // Add selected token ID if using saved token
            const selectedToken = document.querySelector('input[name="payment_method_type"]:checked');
            if (selectedToken?.value === 'saved_token') {
                data.saved_token_id = selectedToken.dataset.tokenId;
            }
            
            try {
                const response = await fetch(window.location.href, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                    },
                    body: JSON.stringify(data)
                });
                
                const result = await response.json();
                
                if (result.success) {
                    if (result.checkout_url) {
                        // Redirect to CardCom
                        window.location.href = result.checkout_url;
                    } else if (result.requires_3ds) {
                        // Redirect to 3DS
                        window.location.href = result.three_ds_url;
                    } else if (result.redirect_url) {
                        // Direct success
                        window.location.href = result.redirect_url;
                    }
                } else {
                    alert(result.message || 'שגיאה בעיבוד התשלום');
                }
            } catch (error) {
                console.error('Payment error:', error);
                alert('שגיאה בעיבוד התשלום');
            }
        });
    }
});
JS;
    }

    /**
     * התקנת פאנלי Filament
     */
    protected function installFilament(): void
    {
        $this->info('🎨 מתקין פאנלי Filament...');
        
        // בדיקה אם Filament מותקן
        if (!class_exists('\\Filament\\FilamentServiceProvider')) {
            $this->warn('⚠️  Filament לא מותקן - מדלג על התקנת פאנלים');
            return;
        }
        
        $this->task('רישום משאבי Filament', function () {
            // הרישום נעשה אוטומטית על ידי ה-ServiceProvider
            return true;
        });
        
        $this->task('יצירת לינקים לפאנלים', function () {
            // יצירת לינקים אוטומטית בניווט
            return true;
        });
    }

    /**
     * הגדרת הרשאות
     */
    protected function setupPermissions(): void
    {
        $this->info('🔐 מגדיר הרשאות...');
        
        if (!class_exists('\\Spatie\\Permission\\Models\\Permission')) {
            $this->warn('⚠️  חבילת spatie/laravel-permission לא מותקנת - מדלג על הגדרת הרשאות');
            return;
        }
        
        $permissions = [
            'view_payment_transactions',
            'create_payment_transactions', 
            'edit_payment_transactions',
            'delete_payment_transactions',
            'view_payment_pages',
            'create_payment_pages',
            'edit_payment_pages',
            'delete_payment_pages',
            'manage_payment_settings',
            'view_payment_reports'
        ];
        
        $this->task('יצירת הרשאות', function () use ($permissions) {
            foreach ($permissions as $permission) {
                \Spatie\Permission\Models\Permission::firstOrCreate(['name' => $permission]);
            }
            return true;
        });
    }

    /**
     * אופטימיזציה של ההתקנה
     */
    protected function optimizeInstallation(): void
    {
        if (!$this->option('optimize')) {
            return;
        }
        
        $this->info('⚡ מבצע אופטימיזציה...');
        
        $optimizationCommands = [
            'config:cache' => 'מטמון הגדרות',
            'route:cache' => 'מטמון נתיבים', 
            'view:cache' => 'מטמון תבניות',
            'optimize' => 'אופטימיזציה כללית'
        ];
        
        foreach ($optimizationCommands as $command => $description) {
            $this->task($description, function () use ($command) {
                try {
                    Artisan::call($command);
                    return true;
                } catch (\Exception $e) {
                    return false;
                }
            });
        }
    }

    /**
     * בדיקה מתקדמת של דרישות מוקדמות
     */
    protected function checkPrerequisites(): void
    {
        $this->info('🔍 בודק דרישות מוקדמות...');
        
        $checks = [
            'PHP Version' => $this->checkPhpVersion(),
            'Laravel Version' => $this->checkLaravelVersion(),
            'Extensions' => $this->checkPhpExtensions(),
            'Database' => $this->checkDatabase(),
            'File Permissions' => $this->checkPermissions(),
            'Dependencies' => $this->checkDependencies()
        ];
        
        foreach ($checks as $checkName => $result) {
            if ($result['success']) {
                $this->info("✅ {$checkName}: {$result['message']}");
            } else {
                $this->error("❌ {$checkName}: {$result['message']}");
                throw new \Exception("דרישה חסרה: {$checkName}");
            }
        }
    }

    protected function checkPhpVersion(): array
    {
        $minVersion = '8.2.0';
        $currentVersion = PHP_VERSION;
        
        return [
            'success' => version_compare($currentVersion, $minVersion, '>='),
            'message' => "PHP {$currentVersion} (דרוש {$minVersion}+)"
        ];
    }

    protected function checkLaravelVersion(): array
    {
        $minVersion = '11.0.0';
        $currentVersion = app()->version();
        
        return [
            'success' => version_compare($currentVersion, $minVersion, '>='),
            'message' => "Laravel {$currentVersion} (דרוש {$minVersion}+)"
        ];
    }

    protected function checkPhpExtensions(): array
    {
        $required = ['json', 'curl', 'mbstring', 'openssl', 'pdo'];
        $missing = [];
        
        foreach ($required as $ext) {
            if (!extension_loaded($ext)) {
                $missing[] = $ext;
            }
        }
        
        return [
            'success' => empty($missing),
            'message' => empty($missing) ? 'כל התוספים מותקנים' : 'חסרים: ' . implode(', ', $missing)
        ];
    }

    protected function checkDatabase(): array
    {
        try {
            \DB::connection()->getPdo();
            return ['success' => true, 'message' => 'חיבור למסד הנתונים תקין'];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => 'שגיאה בחיבור למסד הנתונים'];
        }
    }

    protected function checkPermissions(): array
    {
        $paths = [
            storage_path(),
            config_path(),
            database_path(),
            resource_path()
        ];
        
        foreach ($paths as $path) {
            if (!is_writable($path)) {
                return ['success' => false, 'message' => "אין הרשאות כתיבה ל: {$path}"];
            }
        }
        
        return ['success' => true, 'message' => 'הרשאות קבצים תקינות'];
    }

    protected function checkDependencies(): array
    {
        $required = [
            'Illuminate\\Foundation\\Application',
            'Filament\\FilamentServiceProvider'
        ];
        
        foreach ($required as $class) {
            if (!class_exists($class)) {
                return ['success' => false, 'message' => "חסרה תלות: {$class}"];
            }
        }
        
        return ['success' => true, 'message' => 'כל התלויות מותקנות'];
    }
}
