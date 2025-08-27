<?php

namespace NMDigitalHub\PaymentGateway\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use NMDigitalHub\PaymentGateway\Services\CatalogSyncService;
use App\Models\ServiceProvider;

class SyncProviderCatalog implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;
    public int $timeout = 1800; // 30 minutes
    public int $maxExceptions = 3;

    public function __construct(
        public readonly string $providerName,
        public readonly array $options = []
    ) {
        $this->onQueue('catalog-sync');
    }

    /**
     * Execute the job.
     */
    public function handle(CatalogSyncService $catalogSync): void
    {
        $startTime = microtime(true);
        $lockKey = "catalog_sync_{$this->providerName}";
        
        // Prevent concurrent syncs of the same provider
        if (!Cache::lock($lockKey, 3600)->get()) {
            Log::warning('Catalog sync already running for provider', [
                'provider' => $this->providerName
            ]);
            return;
        }

        try {
            Log::info('Starting catalog sync', [
                'provider' => $this->providerName,
                'options' => $this->options,
                'attempt' => $this->attempts(),
            ]);

            // Get provider model
            $provider = ServiceProvider::where('name', $this->providerName)
                ->where('is_active', true)
                ->first();

            if (!$provider) {
                throw new \Exception("Active provider {$this->providerName} not found");
            }

            // Perform the sync
            $result = $catalogSync->syncProvider($provider, $this->options);

            $duration = round(microtime(true) - $startTime, 2);

            Log::info('Catalog sync completed', [
                'provider' => $this->providerName,
                'duration' => $duration,
                'items_synced' => $result['synced'] ?? 0,
                'items_updated' => $result['updated'] ?? 0,
                'items_created' => $result['created'] ?? 0,
                'items_disabled' => $result['disabled'] ?? 0,
                'errors' => $result['errors'] ?? [],
            ]);

            // Update provider last sync timestamp
            $provider->update([
                'last_sync_at' => now(),
                'sync_status' => 'completed',
                'last_sync_duration' => $duration,
                'last_sync_items' => $result['synced'] ?? 0,
            ]);

            // Cache sync results for dashboard
            Cache::put(
                "catalog_sync_result_{$this->providerName}",
                $result,
                now()->addHours(24)
            );

        } catch (\Exception $e) {
            $duration = round(microtime(true) - $startTime, 2);
            
            Log::error('Catalog sync failed', [
                'provider' => $this->providerName,
                'duration' => $duration,
                'error' => $e->getMessage(),
                'attempt' => $this->attempts(),
                'trace' => $e->getTraceAsString(),
            ]);

            // Update provider sync status
            if ($provider ?? null) {
                $provider->update([
                    'sync_status' => 'failed',
                    'last_sync_error' => $e->getMessage(),
                    'last_sync_duration' => $duration,
                ]);
            }

            throw $e; // Re-throw to trigger retry
        } finally {
            // Release the lock
            Cache::lock($lockKey)->release();
        }
    }

    /**
     * Handle job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::critical('Catalog sync job permanently failed', [
            'provider' => $this->providerName,
            'options' => $this->options,
            'error' => $exception->getMessage(),
            'attempts' => $this->tries,
        ]);

        // Update provider status
        try {
            ServiceProvider::where('name', $this->providerName)
                ->update([
                    'sync_status' => 'failed',
                    'last_sync_error' => $exception->getMessage(),
                ]);
        } catch (\Exception $e) {
            Log::error('Failed to update provider status after job failure', [
                'provider' => $this->providerName,
                'error' => $e->getMessage()
            ]);
        }

        // Notify administrators
        $this->notifyAdminsOfFailure($exception);
    }

    /**
     * Notify administrators of sync failure
     */
    private function notifyAdminsOfFailure(\Throwable $exception): void
    {
        try {
            \Notification::route('mail', config('payment-gateway.admin_email', 'admin@example.com'))
                ->notify(new \NMDigitalHub\PaymentGateway\Notifications\CatalogSyncFailedNotification(
                    $this->providerName,
                    $this->options,
                    $exception->getMessage()
                ));
        } catch (\Exception $e) {
            Log::error('Failed to send catalog sync failure notification', [
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Calculate the number of seconds to wait before retrying the job.
     */
    public function backoff(): array
    {
        return [60, 300]; // 1 minute, then 5 minutes
    }

    /**
     * Get job tags for horizon monitoring
     */
    public function tags(): array
    {
        return [
            'catalog-sync',
            "provider:{$this->providerName}",
            'scheduled',
        ];
    }

    /**
     * Determine if the job should be retried based on the exception
     */
    public function retryUntil(): \DateTime
    {
        return now()->addHours(2); // Don't retry after 2 hours
    }
}