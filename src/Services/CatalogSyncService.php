<?php

namespace NMDigitalHub\PaymentGateway\Services;

use NMDigitalHub\PaymentGateway\PaymentGatewayManager;
use App\Models\ServiceProvider;
use App\Models\ApiEndpoint;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Carbon\Carbon;

class CatalogSyncService
{
    protected PaymentGatewayManager $paymentManager;
    
    public function __construct(PaymentGatewayManager $paymentManager)
    {
        $this->paymentManager = $paymentManager;
    }

    /**
     * סנכרון מלא של כל הספקים
     */
    public function syncAllProviders(array $options = []): array
    {
        $results = [];
        $providers = $this->getActiveServiceProviders();

        Log::info('Starting catalog sync for all providers', [
            'providers_count' => $providers->count(),
            'options' => $options
        ]);

        foreach ($providers as $provider) {
            try {
                $result = $this->syncProvider($provider, $options);
                $results[$provider->name] = $result;
                
                // עדכון זמן סנכרון אחרון
                $provider->update([
                    'last_sync_at' => now(),
                    'sync_status' => $result['success'] ? 'success' : 'failed',
                    'sync_message' => $result['message'] ?? null
                ]);
                
            } catch (\Exception $e) {
                Log::error('Provider sync failed', [
                    'provider' => $provider->name,
                    'error' => $e->getMessage()
                ]);
                
                $results[$provider->name] = [
                    'success' => false,
                    'error' => $e->getMessage(),
                    'synced_count' => 0
                ];
                
                $provider->update([
                    'sync_status' => 'failed',
                    'sync_message' => $e->getMessage()
                ]);
            }
        }

        // ניקוי cache
        Cache::tags(['payment-gateway', 'catalog'])->flush();

        Log::info('Catalog sync completed', ['results' => $results]);
        return $results;
    }

    /**
     * סנכרון ספק יחיד
     */
    public function syncProvider(ServiceProvider $provider, array $options = []): array
    {
        $providerService = $this->paymentManager->service($provider->name);
        
        $filters = array_merge([
            'active_only' => $options['active_only'] ?? true,
            'limit' => $options['limit'] ?? 100,
            'sync_mode' => $options['sync_mode'] ?? 'incremental'
        ], $options['filters'] ?? []);

        Log::info('Syncing provider', [
            'provider' => $provider->name,
            'filters' => $filters
        ]);

        // שליפת מוצרים מה-API
        $products = $providerService->getProducts($filters);
        
        $syncedCount = 0;
        $errors = [];
        
        foreach ($products as $productData) {
            try {
                $this->syncProduct($provider, $productData, $options);
                $syncedCount++;
            } catch (\Exception $e) {
                $errors[] = [
                    'product_id' => $productData['id'] ?? 'unknown',
                    'error' => $e->getMessage()
                ];
                
                Log::warning('Product sync failed', [
                    'provider' => $provider->name,
                    'product' => $productData['id'] ?? 'unknown',
                    'error' => $e->getMessage()
                ]);
            }
        }

        return [
            'success' => empty($errors) || (count($errors) / count($products)) < 0.5,
            'synced_count' => $syncedCount,
            'total_products' => count($products),
            'errors_count' => count($errors),
            'errors' => $errors,
            'message' => $this->generateSyncMessage($syncedCount, count($products), count($errors))
        ];
    }

    /**
     * סנכרון מוצר יחיד
     */
    protected function syncProduct(ServiceProvider $provider, array $productData, array $options = []): void
    {
        // מה ספק השירות מקבע איך לשמור מוצרים
        switch ($provider->name) {
            case 'maya_mobile':
                $this->syncMayaMobileProduct($productData, $options);
                break;
                
            case 'resellerclub':
                $this->syncResellerClubProduct($productData, $options);
                break;
                
            default:
                throw new \InvalidArgumentException("Unknown provider: {$provider->name}");
        }
    }

    /**
     * סנכרון מוצרי Maya Mobile
     */
    protected function syncMayaMobileProduct(array $productData, array $options = []): void
    {
        $model = \App\Models\MayaNetEsimProduct::class;
        
        // חיפוש מוצר קיים
        $product = $model::where('external_id', $productData['id'])
            ->orWhere('slug', $productData['slug'] ?? null)
            ->first();

        $productData['provider'] = 'maya_mobile';
        $productData['is_active'] = $productData['active'] ?? true;
        $productData['sync_at'] = now();
        
        // עדכון או יצירה
        if ($product) {
            $product->update($productData);
        } else {
            $model::create($productData);
        }
    }

    /**
     * סנכרון מוצרי ResellerClub
     */
    protected function syncResellerClubProduct(array $productData, array $options = []): void
    {
        // אם יש מודל ספציפי למוצרי ResellerClub
        $model = \App\Models\ResellerClubProduct::class;
        
        if (!class_exists($model)) {
            // שימוש במודל כללי
            $model = \App\Models\Product::class;
        }
        
        $product = $model::where('external_id', $productData['id'])
            ->where('provider', 'resellerclub')
            ->first();

        $productData['provider'] = 'resellerclub';
        $productData['is_active'] = $productData['active'] ?? true;
        $productData['sync_at'] = now();
        
        if ($product) {
            $product->update($productData);
        } else {
            $model::create($productData);
        }
    }

    /**
     * קבלת ספקי שירות פעילים
     */
    protected function getActiveServiceProviders(): Collection
    {
        return ServiceProvider::where('type', 'service')
            ->where('is_active', true)
            ->whereNotNull('api_endpoint')
            ->get();
    }

    /**
     * יצירת הודעת סנכרון
     */
    protected function generateSyncMessage(int $synced, int $total, int $errors): string
    {
        if ($errors === 0) {
            return "סונכרנו {$synced} מתוך {$total} מוצרים בהצלחה";
        }
        
        return "סונכרנו {$synced} מתוך {$total} מוצרים. {$errors} שגיאות";
    }

    /**
     * קבלת סטטיסטיקות סנכרון
     */
    public function getSyncStats(): array
    {
        $providers = $this->getActiveServiceProviders();
        $stats = [];
        
        foreach ($providers as $provider) {
            $lastSync = $provider->last_sync_at;
            $status = $provider->sync_status ?? 'never';
            
            // ספירת מוצרים לפי ספק
            $productsCount = $this->getProviderProductsCount($provider->name);
            
            $stats[$provider->name] = [
                'name' => $provider->display_name ?? $provider->name,
                'status' => $status,
                'last_sync' => $lastSync?->format('d/m/Y H:i'),
                'products_count' => $productsCount,
                'health' => $this->getProviderHealth($provider),
                'next_sync' => $this->getNextSyncTime($provider)
            ];
        }
        
        return $stats;
    }

    /**
     * ספירת מוצרים לפי ספק
     */
    protected function getProviderProductsCount(string $providerName): int
    {
        switch ($providerName) {
            case 'maya_mobile':
                if (class_exists(\App\Models\MayaNetEsimProduct::class)) {
                    return \App\Models\MayaNetEsimProduct::where('is_active', true)->count();
                }
                break;
                
            case 'resellerclub':
                if (class_exists(\App\Models\Product::class)) {
                    return \App\Models\Product::where('provider', 'resellerclub')
                        ->where('is_active', true)->count();
                }
                break;
        }
        
        return 0;
    }

    /**
     * בדיקת בריאות ספק
     */
    protected function getProviderHealth(ServiceProvider $provider): string
    {
        try {
            $providerService = $this->paymentManager->service($provider->name);
            $healthy = $providerService->testConnection();
            return $healthy ? 'healthy' : 'unhealthy';
        } catch (\Exception $e) {
            return 'error';
        }
    }

    /**
     * חישוב זמן סנכרון בא
     */
    protected function getNextSyncTime(ServiceProvider $provider): ?string
    {
        if (!$provider->last_sync_at) {
            return 'דרוש סנכרון ראשון';
        }
        
        // סנכרון כל 24 שעות
        $nextSync = $provider->last_sync_at->addDay();
        
        if ($nextSync->isPast()) {
            return 'דרוש עכשיו';
        }
        
        return $nextSync->format('d/m/Y H:i');
    }
}
