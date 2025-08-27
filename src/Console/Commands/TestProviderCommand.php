<?php

namespace NMDigitalHub\PaymentGateway\Console\Commands;

use Illuminate\Console\Command;
use NMDigitalHub\PaymentGateway\PaymentGatewayManager;

class TestProviderCommand extends Command
{
    protected $signature = 'payment-gateway:test 
                            {provider : Provider name to test}
                            {--amount=1.00 : Test amount}
                            {--dry-run : Only test connection without actual transaction}';
    
    protected $description = 'בדיקת ספק תשלום';

    public function handle(PaymentGatewayManager $manager): int
    {
        $providerName = $this->argument('provider');
        $amount = (float) $this->option('amount');
        $dryRun = $this->option('dry-run');

        $this->info("🧪 בדיקת ספק: {$providerName}");
        
        try {
            $provider = $manager->getProvider($providerName);
            
            if (!$provider) {
                $this->error("❌ ספק '{$providerName}' לא נמצא");
                return self::FAILURE;
            }

            // בדיקת חיבור
            $this->info("🔌 בודק חיבור...");
            $connected = $manager->testProviderConnection($providerName);
            
            if (!$connected) {
                $this->error("❌ חיבור נכשל");
                return self::FAILURE;
            }
            
            $this->info("✅ חיבור תקין");

            if ($dryRun) {
                $this->info("✅ בדיקה הושלמה (dry-run)");
                return self::SUCCESS;
            }

            // בדיקת תשלום מבחן
            $this->info("💳 יוצר תשלום מבחן: ₪{$amount}...");
            
            $testPayment = $manager->createTestPayment($providerName, [
                'amount' => $amount,
                'customer_email' => 'test@example.com',
                'customer_name' => 'Test Customer',
                'description' => 'Payment Gateway Test'
            ]);

            if ($testPayment->success) {
                $this->info("✅ תשלום מבחן נוצר בהצלחה");
                $this->info("🔗 URL: {$testPayment->checkout_url}");
                $this->info("📋 Reference: {$testPayment->reference}");
            } else {
                $this->error("❌ יצירת תשלום נכשלה: {$testPayment->error}");
                return self::FAILURE;
            }

            $this->info("🎉 בדיקה הושלמה בהצלחה!");
            return self::SUCCESS;

        } catch (\Exception $e) {
            $this->error("❌ שגיאה בבדיקה: {$e->getMessage()}");
            return self::FAILURE;
        }
    }
}