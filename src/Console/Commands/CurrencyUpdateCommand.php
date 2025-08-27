<?php

namespace NMDigitalHub\PaymentGateway\Console\Commands;

use Illuminate\Console\Command;
use NMDigitalHub\PaymentGateway\Contracts\CurrencyExchangeInterface;

class CurrencyUpdateCommand extends Command
{
    protected $signature = 'payment-gateway:currency-update 
                            {--provider= : ספק API ספציפי}
                            {--clear-cache : ניקוי cache שערי המרה}
                            {--validate : בדיקת תקינות API key}';
    
    protected $description = 'עדכון שערי המרת מטבע מ-API חיצוני';

    public function handle(CurrencyExchangeInterface $currencyService): int
    {
        if ($this->option('clear-cache')) {
            $this->info('מנקה cache שערי המרה...');
            $currencyService->clearCache();
            $this->info('✓ Cache נוקה בהצלחה');
        }
        
        if ($this->option('validate')) {
            $provider = $this->option('provider');
            $this->info("בודק תקינות API key...");
            
            if ($currencyService->validateApiKey($provider)) {
                $this->info('✓ API key תקין');
            } else {
                $this->error('✗ API key לא תקין');
                return self::FAILURE;
            }
        }
        
        $this->info('מעדכן שערי המרה...');
        
        $currencies = ['USD', 'EUR', 'ILS', 'GBP'];
        $provider = $this->option('provider');
        
        foreach ($currencies as $from) {
            foreach ($currencies as $to) {
                if ($from !== $to) {
                    try {
                        $rate = $currencyService->getExchangeRate($from, $to, $provider);
                        $this->line("  {$from} → {$to}: {$rate}");
                    } catch (\Exception $e) {
                        $this->warn("  {$from} → {$to}: שגיאה - {$e->getMessage()}");
                    }
                }
            }
        }
        
        $stats = $currencyService->getUsageStats();
        $this->info("סהי בקשות: {$stats['total_requests']}");
        
        return self::SUCCESS;
    }
}
