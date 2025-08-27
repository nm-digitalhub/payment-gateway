<?php

namespace NMDigitalHub\PaymentGateway\Console\Commands;

use Illuminate\Console\Command;
use NMDigitalHub\PaymentGateway\Services\CatalogSyncService;

class SyncProvidersCommand extends Command
{
    protected $signature = 'payment-gateway:sync 
                            {--provider= : Sync specific provider only}
                            {--force : Force sync even if recently synced}
                            {--limit=100 : Limit number of items to sync}';
    
    protected $description = 'סנכרון קטלוגים מספקי השירות';

    public function handle(CatalogSyncService $syncService): int
    {
        $this->info('🔄 מתחיל סנכרון ספקים...');
        
        $provider = $this->option('provider');
        $options = [
            'force' => $this->option('force'),
            'limit' => (int) $this->option('limit'),
        ];

        try {
            if ($provider) {
                $this->info("סנכרון ספק: {$provider}");
                $results = $syncService->syncProvider($provider, $options);
            } else {
                $this->info('סנכרון כל הספקים...');
                $results = $syncService->syncAllProviders($options);
            }

            foreach ($results as $providerName => $result) {
                if ($result['success']) {
                    $this->info("✅ {$providerName}: {$result['synced_count']} פריטים");
                } else {
                    $this->error("❌ {$providerName}: {$result['error']}");
                }
            }

            $this->info('🎉 סנכרון הושלם!');
            return self::SUCCESS;

        } catch (\Exception $e) {
            $this->error("❌ שגיאה בסנכרון: {$e->getMessage()}");
            return self::FAILURE;
        }
    }
}