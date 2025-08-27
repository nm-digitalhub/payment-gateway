<?php

namespace NMDigitalHub\PaymentGateway\Repositories\Config;

use NMDigitalHub\PaymentGateway\Contracts\ApiEndpointRepositoryInterface;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;

/**
 * Config-based implementation of ApiEndpointRepositoryInterface
 */
class ApiEndpointRepository implements ApiEndpointRepositoryInterface
{
    /**
     * קבלת endpoint לפי ספק וסוג
     */
    public function getEndpoint(string $provider, string $type): ?string
    {
        $endpoints = $this->getProviderEndpoints($provider);
        return $endpoints[$type] ?? null;
    }

    /**
     * קבלת כל endpoints של ספק
     */
    public function getProviderEndpoints(string $provider): array
    {
        $cacheKey = "endpoints_{$provider}";
        
        return Cache::remember($cacheKey, 3600, function () use ($provider) {
            $config = Config::get("payment-gateway.providers.{$provider}", []);
            
            $endpoints = [];
            
            // Get endpoints from configuration
            if (isset($config['endpoints'])) {
                $endpoints = $config['endpoints'];
            } else {
                // Default endpoint structure based on provider
                $endpoints = $this->getDefaultEndpoints($provider);
            }

            return $endpoints;
        });
    }

    /**
     * עדכון endpoint (לא זמין בconfig-based repository)
     */
    public function updateEndpoint(string $provider, string $type, string $url): bool
    {
        Log::warning('Attempted to update endpoint in config-based repository', [
            'provider' => $provider,
            'type' => $type,
            'url' => $url
        ]);
        
        return false; // Config-based repos are read-only
    }

    /**
     * הוספת endpoint חדש (לא זמין בconfig-based repository)
     */
    public function addEndpoint(string $provider, string $type, string $url, array $metadata = []): bool
    {
        Log::warning('Attempted to add endpoint in config-based repository', [
            'provider' => $provider,
            'type' => $type,
            'url' => $url
        ]);
        
        return false; // Config-based repos are read-only
    }

    /**
     * מחיקת endpoint (לא זמין בconfig-based repository)
     */
    public function deleteEndpoint(string $provider, string $type): bool
    {
        Log::warning('Attempted to delete endpoint in config-based repository', [
            'provider' => $provider,
            'type' => $type
        ]);
        
        return false; // Config-based repos are read-only
    }

    /**
     * בדיקת זמינות endpoint
     */
    public function checkEndpointHealth(string $provider, string $type): array
    {
        $endpoint = $this->getEndpoint($provider, $type);
        
        if (!$endpoint) {
            return [
                'healthy' => false,
                'error' => 'Endpoint not found',
                'response_time' => null,
            ];
        }

        try {
            $start = microtime(true);
            $response = Http::timeout(10)->get($endpoint);
            $responseTime = round((microtime(true) - $start) * 1000, 2);

            return [
                'healthy' => $response->successful(),
                'status_code' => $response->status(),
                'response_time' => $responseTime,
                'error' => $response->successful() ? null : 'HTTP ' . $response->status(),
            ];
        } catch (\Exception $e) {
            return [
                'healthy' => false,
                'error' => $e->getMessage(),
                'response_time' => null,
            ];
        }
    }

    /**
     * קבלת metadata של endpoint
     */
    public function getEndpointMetadata(string $provider, string $type): array
    {
        $config = Config::get("payment-gateway.providers.{$provider}.metadata.{$type}", []);
        
        return [
            'timeout' => $config['timeout'] ?? 30,
            'retry_attempts' => $config['retry_attempts'] ?? 3,
            'rate_limit' => $config['rate_limit'] ?? null,
            'authentication' => $config['authentication'] ?? 'api_key',
            'content_type' => $config['content_type'] ?? 'application/json',
        ];
    }

    /**
     * רישום פעולה על endpoint
     */
    public function logEndpointActivity(string $provider, string $type, string $activity, array $details = []): void
    {
        Log::info('API Endpoint Activity', [
            'provider' => $provider,
            'endpoint_type' => $type,
            'activity' => $activity,
            'details' => $details,
            'timestamp' => now()->toISOString(),
        ]);
    }

    /**
     * קבלת endpoints default לפי ספק
     */
    protected function getDefaultEndpoints(string $provider): array
    {
        return match ($provider) {
            'cardcom' => [
                'api' => 'https://secure.cardcom.solutions/api/v11',
                'lowprofile' => 'https://secure.cardcom.solutions/api/v11/LowProfile',
                'webhook' => config('app.url') . '/webhooks/payment-gateway/cardcom',
                'sandbox_api' => 'https://sandbox.cardcom.solutions/api/v11',
            ],
            'maya_mobile' => [
                'api' => 'https://api.maya-mobile.com/v1',
                'webhook' => config('app.url') . '/webhooks/payment-gateway/maya-mobile',
                'sandbox_api' => 'https://sandbox-api.maya-mobile.com/v1',
            ],
            'resellerclub' => [
                'api' => 'https://httpapi.com/api',
                'webhook' => config('app.url') . '/webhooks/payment-gateway/resellerclub',
                'sandbox_api' => 'https://test.httpapi.com/api',
            ],
            default => []
        };
    }
}