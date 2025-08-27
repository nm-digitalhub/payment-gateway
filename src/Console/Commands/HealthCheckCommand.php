<?php

namespace NMDigitalHub\PaymentGateway\Console\Commands;

use Illuminate\Console\Command;
use NMDigitalHub\PaymentGateway\PaymentGatewayManager;

class HealthCheckCommand extends Command
{
    protected $signature = 'payment-gateway:health-check
                            {--provider= : Check specific provider only}
                            {--detailed : Show detailed health information}';
    
    protected $description = 'בדיקת בריאות ספקי התשלום';

    public function handle(PaymentGatewayManager $manager): int
    {
        $this->info('🔍 בודק בריאות ספקי תשלום...');
        
        $provider = $this->option('provider');
        $detailed = $this->option('detailed');

        try {
            $providers = $manager->getAvailableProviders();
            
            if ($provider) {
                $providers = $providers->where('name', $provider);
            }
            
            $allHealthy = true;

            foreach ($providers as $providerData) {
                $this->newLine();
                $this->info("📊 בדיקת ספק: {$providerData['display_name']}");
                
                $isHealthy = $manager->checkProviderHealth($providerData['name']);
                
                if ($isHealthy) {
                    $this->info("✅ {$providerData['name']}: תקין");
                } else {
                    $this->error("❌ {$providerData['name']}: לא זמין");
                    $allHealthy = false;
                }
                
                if ($detailed) {
                    $this->showDetailedHealth($providerData, $manager);
                }
            }

            $this->newLine();
            if ($allHealthy) {
                $this->info('🎉 כל הספקים תקינים!');
                return self::SUCCESS;
            } else {
                $this->warn('⚠️  יש בעיות בחלק מהספקים');
                return self::FAILURE;
            }

        } catch (\Exception $e) {
            $this->error("❌ שגיאה בבדיקת בריאות: {$e->getMessage()}");
            return self::FAILURE;
        }
    }

    protected function showDetailedHealth($provider, PaymentGatewayManager $manager): void
    {
        try {
            $stats = $manager->getProviderStats($provider['name']);
            $this->line("  • תמיכה בטוקנים: " . ($provider['supports_tokens'] ? 'כן' : 'לא'));
            $this->line("  • תמיכה ב-3DS: " . ($provider['supports_3ds'] ? 'כן' : 'לא'));
            $this->line("  • עסקאות השבוע: " . ($stats['weekly_transactions'] ?? 0));
            $this->line("  • בדיקה אחרונה: " . ($stats['last_health_check'] ?? 'אף פעם'));
        } catch (\Exception $e) {
            $this->line("  • שגיאה בקבלת פרטים: " . $e->getMessage());
        }
    }
}