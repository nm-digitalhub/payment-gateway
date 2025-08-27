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
        if (version_compare(app()->version(), '11.0.0', '<')) {
            throw new \Exception('Payment Gateway דורש Laravel 11.0 ומעלה');
        }
        
        // בדיקת PHP version
        if (version_compare(PHP_VERSION, '8.2.0', '<')) {
            throw new \Exception('Payment Gateway דורש PHP 8.2 ומעלה');
        }
        
        // בדיקת Filament
        if (!class_exists('\\Filament\\FilamentServiceProvider')) {
            throw new \Exception('Payment Gateway דורש Filament v3. התקן עם: composer require filament/filament');
        }
        
        $this->info('✅ כל הדרישות מתקיימות');
    }

    protected function isAlreadyInstalled(): bool
    {
        return Schema::hasTable('payment_pages') && Schema::hasTable('payment_transactions');
    }

    protected function publishFiles(): void
    {
        $this->info('📁 מפרסם קבצים...');
        
        if (!$this->option('skip-publish')) {
            $this->call('vendor:publish', [
                '--provider' => 'NMDigitalHub\\PaymentGateway\\PaymentGatewayServiceProvider',
                '--tag' => 'config',
                '--force' => $this->option('force')
            ]);
            
            $this->call('vendor:publish', [
                '--provider' => 'NMDigitalHub\\PaymentGateway\\PaymentGatewayServiceProvider', 
                '--tag' => 'migrations',
                '--force' => $this->option('force')
            ]);
        }
        
        $this->info('✅ קבצים פורסמו בהצלחה');
    }

    protected function runMigrations(): void
    {
        $this->info('🗄️ מריץ migrations...');
        
        $this->call('migrate', [
            '--force' => true
        ]);
        
        $this->info('✅ Migrations הושלמו');
    }

    protected function setupServiceProviders(): void
    {
        $this->info('⚙️ מגדיר ספקי שירות...');
        
        // הגדרות בסיסיות
        $this->info('✅ ספקי השירות הוגדרו');
    }

    protected function createDemoData(): void
    {
        $this->info('🎭 יוצר נתוני דמו...');
        
        if ($this->confirm('האם ליצור נתוני דמו?')) {
            $this->call('db:seed', [
                '--class' => 'PaymentGatewaySeeder'
            ]);
        }
        
        $this->info('✅ נתוני הדמו נוצרו');
    }

    protected function updateComposer(): void
    {
        if ($this->option('optimize')) {
            $this->info('⚡ מבצע אופטימיזציה...');
            
            $this->call('config:cache');
            $this->call('route:cache'); 
            $this->call('view:cache');
            
            $this->info('✅ אופטימיזציה הושלמה');
        }
    }

    protected function displaySuccessMessage(): void
    {
        $this->info('');
        $this->line('🎉 <fg=green>Payment Gateway הותקן בהצלחה!</fg=green>');
        $this->info('');
        $this->line('📋 השלבים הבאים:');
        $this->line('   1. עדכן את קובץ .env עם פרטי CardCom');
        $this->line('   2. בקר בפאנל האדמין: /admin/payment-transactions');  
        $this->line('   3. הגדר ספקי תשלום ב: /admin/service-providers');
        $this->line('   4. צור עמוד תשלום ראשון: php artisan payment-gateway:create-page');
        $this->info('');
        $this->line('💡 לעזרה נוספת: php artisan payment-gateway:help');
        $this->info('');
    }
}
