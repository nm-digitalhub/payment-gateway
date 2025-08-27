<?php

namespace NMDigitalHub\PaymentGateway\Services;

use NMDigitalHub\PaymentGateway\Contracts\SyncProviderInterface;
use NMDigitalHub\PaymentGateway\Contracts\ServiceProviderRepositoryInterface;
use NMDigitalHub\PaymentGateway\Models\Package;
use NMDigitalHub\PaymentGateway\Models\SyncLink;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Collection;

/**
 * שירות סנכרון מרכזי מרובה ספקים
 * מממש SyncProviderInterface ומנהל את כל ספקי השירות
 */
class PackageSyncService implements SyncProviderInterface
{
    protected ServiceProviderRepositoryInterface $providerRepository;
    protected array $registeredProviders = [];
    protected array $syncStats = [];

    public function __construct(ServiceProviderRepositoryInterface $providerRepository)
    {
        $this->providerRepository = $providerRepository;
        $this->initializeProviders();
    }

    /**
     * אתחול ספקים רשומים
     */
    protected function initializeProviders(): void
    {
        $activeProviders = $this->providerRepository->getActiveProviders();
        
        foreach ($activeProviders as $provider) {
            $providerClass = $this->getProviderClass($provider['provider_name']);
            if ($providerClass && class_exists($providerClass)) {
                $this->registerProvider($provider['provider_name'], new $providerClass());
            }
        }

        Log::info('PackageSyncService initialized', [
            'providers_count' => count($this->registeredProviders),
            'providers' => array_keys($this->registeredProviders),
        ]);
    }

    /**
     * רישום ספק חדש
     */
    public function registerProvider(string $name, SyncProviderInterface $provider): void
    {
        $this->registeredProviders[$name] = $provider;
        Log::info('Provider registered with PackageSyncService', ['provider' => $name]);
    }

    /**
     * קבלת מחלקת ספק לפי שם
     */
    protected function getProviderClass(string $providerName): ?string
    {
        return match ($providerName) {
            'resellerclub' => \NMDigitalHub\PaymentGateway\Providers\Services\ResellerClubProvider::class,
            'maya_mobile' => \NMDigitalHub\PaymentGateway\Providers\Services\MayaMobileProvider::class,
            default => null
        };
    }

    /**
     * סנכרון חבילות מספק מסוים
     */
    public function syncProviderPackages(string $providerName, array $options = []): array
    {
        $startTime = microtime(true);
        
        if (!isset($this->registeredProviders[$providerName])) {
            Log::error('Provider not found for sync', ['provider' => $providerName]);
            return [
                'success' => false,
                'error' => 'Provider not found',
                'provider' => $providerName,
            ];
        }

        try {
            $provider = $this->registeredProviders[$providerName];
            $limit = $options['limit'] ?? 100;
            $dryRun = $options['dry_run'] ?? false;

            Log::info('Starting package sync', [
                'provider' => $providerName,
                'limit' => $limit,
                'dry_run' => $dryRun,
            ]);

            // קבלת חבילות מהספק
            $remotePackages = $provider->getPackages($limit);
            
            if (!$remotePackages['success']) {
                throw new \Exception('Failed to fetch packages from provider: ' . ($remotePackages['error'] ?? 'Unknown error'));
            }

            $packages = $remotePackages['data'] ?? [];
            $syncResults = [
                'success' => true,
                'provider' => $providerName,
                'total_fetched' => count($packages),
                'created' => 0,
                'updated' => 0,
                'skipped' => 0,
                'errors' => [],
                'dry_run' => $dryRun,
            ];

            // עיבוד כל חבילה
            foreach ($packages as $packageData) {
                try {
                    $result = $this->processPackage($providerName, $packageData, $dryRun);
                    $syncResults[$result['action']]++;
                    
                    if ($result['action'] === 'error') {
                        $syncResults['errors'][] = $result['message'];
                    }
                } catch (\Exception $e) {
                    $syncResults['errors'][] = $e->getMessage();
                }
            }

            $duration = round(microtime(true) - $startTime, 3);
            $syncResults['duration'] = $duration;

            // רישום תוצאות
            $this->providerRepository->recordSyncOperation($providerName, 'package_sync', $syncResults);

            Log::info('Package sync completed', $syncResults);

            return $syncResults;

        } catch (\Exception $e) {
            $duration = round(microtime(true) - $startTime, 3);
            $error = [
                'success' => false,
                'provider' => $providerName,
                'error' => $e->getMessage(),
                'duration' => $duration,
            ];

            Log::error('Package sync failed', $error);
            return $error;
        }
    }

    /**
     * עיבוד חבילה יחידה
     */
    protected function processPackage(string $providerName, array $packageData, bool $dryRun = false): array
    {
        try {
            $externalId = $packageData['external_id'] ?? $packageData['id'] ?? null;
            
            if (!$externalId) {
                return ['action' => 'error', 'message' => 'Package missing external ID'];
            }

            // חיפוש חבילה קיימת
            $existingPackage = Package::where('provider_name', $providerName)
                ->where('external_id', $externalId)
                ->first();

            $packageAttributes = [
                'provider_name' => $providerName,
                'external_id' => $externalId,
                'name' => $packageData['name'] ?? '',
                'description' => $packageData['description'] ?? '',
                'price' => $packageData['price'] ?? 0,
                'currency' => $packageData['currency'] ?? 'USD',
                'category' => $packageData['category'] ?? 'general',
                'is_active' => $packageData['is_active'] ?? true,
                'metadata' => $packageData['metadata'] ?? [],
                'last_synced_at' => now(),
            ];

            if ($dryRun) {
                return [
                    'action' => $existingPackage ? 'would_update' : 'would_create',
                    'package_id' => $existingPackage?->id,
                    'external_id' => $externalId,
                ];
            }

            if ($existingPackage) {
                // עדכון חבילה קיימת
                $existingPackage->update($packageAttributes);
                
                return [
                    'action' => 'updated',
                    'package_id' => $existingPackage->id,
                    'external_id' => $externalId,
                ];
            } else {
                // יצירת חבילה חדשה
                $package = Package::create($packageAttributes);
                
                return [
                    'action' => 'created',
                    'package_id' => $package->id,
                    'external_id' => $externalId,
                ];
            }

        } catch (\Exception $e) {
            return [
                'action' => 'error',
                'message' => $e->getMessage(),
                'external_id' => $externalId ?? 'unknown',
            ];
        }
    }

    /**
     * סנכרון כל הספקים
     */
    public function syncAllProviders(array $options = []): array
    {
        $overallResults = [
            'success' => true,
            'providers' => [],
            'total_duration' => 0,
            'total_packages' => 0,
            'summary' => [
                'created' => 0,
                'updated' => 0,
                'skipped' => 0,
                'errors' => 0,
            ],
        ];

        $startTime = microtime(true);

        foreach ($this->registeredProviders as $providerName => $provider) {
            $result = $this->syncProviderPackages($providerName, $options);
            $overallResults['providers'][$providerName] = $result;

            if ($result['success']) {
                $overallResults['total_packages'] += $result['total_fetched'] ?? 0;
                $overallResults['summary']['created'] += $result['created'] ?? 0;
                $overallResults['summary']['updated'] += $result['updated'] ?? 0;
                $overallResults['summary']['skipped'] += $result['skipped'] ?? 0;
                $overallResults['summary']['errors'] += count($result['errors'] ?? []);
            } else {
                $overallResults['success'] = false;
                $overallResults['summary']['errors']++;
            }
        }

        $overallResults['total_duration'] = round(microtime(true) - $startTime, 3);

        Log::info('All providers sync completed', $overallResults);

        return $overallResults;
    }

    /**
     * קבלת סטטוס סנכרון
     */
    public function getSyncStatus(): array
    {
        $status = [];

        foreach (array_keys($this->registeredProviders) as $providerName) {
            $lastSync = $this->providerRepository->getSyncHistory($providerName, 1);
            $packagesCount = Package::where('provider_name', $providerName)
                ->where('is_active', true)
                ->count();

            $status[$providerName] = [
                'packages_count' => $packagesCount,
                'last_sync' => $lastSync['recent_syncs'][0] ?? null,
                'health' => $this->checkProviderHealth($providerName),
            ];
        }

        return [
            'providers' => $status,
            'total_packages' => Package::where('is_active', true)->count(),
            'total_providers' => count($this->registeredProviders),
        ];
    }

    /**
     * בדיקת בריאות ספק
     */
    protected function checkProviderHealth(string $providerName): array
    {
        try {
            $isHealthy = $this->providerRepository->validateProviderConnection($providerName);
            
            return [
                'status' => $isHealthy ? 'healthy' : 'unhealthy',
                'last_checked' => now()->toISOString(),
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'error',
                'error' => $e->getMessage(),
                'last_checked' => now()->toISOString(),
            ];
        }
    }

    /**
     * Implementation of SyncProviderInterface methods
     */

    /**
     * קבלת חבילות (מממש SyncProviderInterface)
     */
    public function getPackages(int $limit = 100): array
    {
        try {
            $packages = Package::with('syncLinks')
                ->where('is_active', true)
                ->limit($limit)
                ->get()
                ->map(function (Package $package) {
                    return [
                        'id' => $package->id,
                        'external_id' => $package->external_id,
                        'provider_name' => $package->provider_name,
                        'name' => $package->name,
                        'description' => $package->description,
                        'price' => $package->price,
                        'currency' => $package->currency,
                        'category' => $package->category,
                        'metadata' => $package->metadata,
                        'last_synced_at' => $package->last_synced_at,
                    ];
                })
                ->toArray();

            return [
                'success' => true,
                'data' => $packages,
                'total' => count($packages),
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'data' => [],
            ];
        }
    }

    /**
     * יצירת הזמנה (מממש SyncProviderInterface)
     */
    public function createOrder(array $orderData): array
    {
        // לא רלוונטי לשירות המרכזי - יופנה לספק הרלוונטי
        return [
            'success' => false,
            'error' => 'Use specific provider for order creation',
        ];
    }

    /**
     * בדיקת סטטוס הזמנה (מממש SyncProviderInterface)
     */
    public function checkOrderStatus(string $orderId): array
    {
        // לא רלוונטי לשירות המרכזי - יופנה לספק הרלוונטי
        return [
            'success' => false,
            'error' => 'Use specific provider for order status check',
        ];
    }
}