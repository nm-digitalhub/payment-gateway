<?php

namespace NMDigitalHub\PaymentGateway\Repositories\Eloquent;

use NMDigitalHub\PaymentGateway\Contracts\ServiceProviderRepositoryInterface;
use NMDigitalHub\PaymentGateway\Models\ProviderSetting;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

/**
 * Eloquent implementation of ServiceProviderRepositoryInterface
 */
class ServiceProviderRepository implements ServiceProviderRepositoryInterface
{
    /**
     * קבלת כל הספקים הפעילים
     */
    public function getActiveProviders(): array
    {
        return Cache::remember('active_providers', 3600, function () {
            return ProviderSetting::where('is_active', true)
                ->select(['provider_name', 'provider_type', 'settings', 'is_active'])
                ->get()
                ->groupBy('provider_name')
                ->map(function ($group) {
                    return $group->first()->toArray();
                })
                ->values()
                ->toArray();
        });
    }

    /**
     * קבלת ספק לפי שם
     */
    public function getProviderByName(string $name): ?array
    {
        $cacheKey = "provider_{$name}";
        
        return Cache::remember($cacheKey, 3600, function () use ($name) {
            $provider = ProviderSetting::where('provider_name', $name)
                ->where('is_active', true)
                ->first();
                
            return $provider?->toArray();
        });
    }

    /**
     * קבלת API endpoints של ספק
     */
    public function getProviderEndpoints(string $providerName): array
    {
        $provider = $this->getProviderByName($providerName);
        
        if (!$provider) {
            return [];
        }

        $settings = $provider['settings'] ?? [];
        
        return [
            'api_url' => $settings['api_url'] ?? '',
            'webhook_url' => $settings['webhook_url'] ?? '',
            'callback_url' => $settings['callback_url'] ?? '',
            'sandbox_url' => $settings['sandbox_url'] ?? '',
        ];
    }

    /**
     * עדכון הגדרות ספק
     */
    public function updateProviderSettings(string $providerName, array $settings): bool
    {
        try {
            $provider = ProviderSetting::where('provider_name', $providerName)->first();
            
            if (!$provider) {
                return false;
            }

            $currentSettings = $provider->settings ?? [];
            $newSettings = array_merge($currentSettings, $settings);
            
            $provider->update(['settings' => $newSettings]);
            
            // Clear cache
            Cache::forget("provider_{$providerName}");
            Cache::forget('active_providers');
            
            Log::info('Provider settings updated', [
                'provider' => $providerName,
                'updated_keys' => array_keys($settings)
            ]);
            
            return true;
        } catch (\Exception $e) {
            Log::error('Failed to update provider settings', [
                'provider' => $providerName,
                'error' => $e->getMessage()
            ]);
            
            return false;
        }
    }

    /**
     * אימות התקשרות לספק
     */
    public function validateProviderConnection(string $providerName): bool
    {
        $provider = $this->getProviderByName($providerName);
        
        if (!$provider) {
            return false;
        }

        $settings = $provider['settings'] ?? [];
        $apiUrl = $settings['api_url'] ?? null;
        
        if (!$apiUrl) {
            return false;
        }

        try {
            $response = \Http::timeout(10)->get($apiUrl . '/health');
            return $response->successful();
        } catch (\Exception $e) {
            Log::warning('Provider connection validation failed', [
                'provider' => $providerName,
                'error' => $e->getMessage()
            ]);
            
            return false;
        }
    }

    /**
     * קבלת היסטוריית sync של ספק
     */
    public function getSyncHistory(string $providerName, int $limit = 10): array
    {
        // This would typically come from a sync_logs table
        // For now, return mock data structure
        return Cache::remember("sync_history_{$providerName}", 600, function () use ($limit) {
            return [
                'total_syncs' => 0,
                'last_sync' => null,
                'recent_syncs' => [],
                'success_rate' => 100.0,
            ];
        });
    }

    /**
     * רישום פעולת sync
     */
    public function recordSyncOperation(string $providerName, string $operation, array $result): void
    {
        Log::info('Sync operation recorded', [
            'provider' => $providerName,
            'operation' => $operation,
            'success' => $result['success'] ?? false,
            'records_processed' => $result['records_processed'] ?? 0,
            'duration' => $result['duration'] ?? 0,
        ]);

        // Clear sync history cache to refresh data
        Cache::forget("sync_history_{$providerName}");
    }
}