<?php

namespace NMDigitalHub\PaymentGateway\Services;

use NMDigitalHub\PaymentGateway\Contracts\ServiceProviderInterface;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Carbon\Carbon;

/**
 * Maya Mobile Connect+ API Service - Based on Maya-Mobile-Connectivity-API-2.yaml
 * API Base: https://api.maya.net/connectivity/v1
 */
class MayaMobileService implements ServiceProviderInterface
{
    protected string $baseUrl;
    protected string $apiKey;
    protected string $apiSecret;
    protected bool $testMode;
    
    public function __construct()
    {
        // קריאת הגדרות מהמערכת הראשית דרך Laravel Settings
        $settings = app(\App\Settings\MayaMobileSettings::class);
        
        $this->baseUrl = $settings->getApiUrl() . '/connectivity/v1';
        $this->apiKey = $settings->api_key ?? '';
        $this->apiSecret = $settings->api_secret ?? '';
        $this->testMode = $settings->test_mode ?? false;
    }

    /**
     * Get available products/plans from Maya Mobile
     */
    public function getProducts(array $filters = []): array
    {
        try {
            $response = $this->baseRequest()
                ->get('/products', $filters);

            if (!$response->successful()) {
                throw new \Exception('Maya Mobile API error: ' . $response->body());
            }

            $products = $response->json();
            
            // Transform to standard format
            return array_map(function($product) {
                return [
                    'id' => $product['uid'] ?? $product['id'],
                    'name' => $product['name'] ?? $product['title'],
                    'description' => $product['description'] ?? '',
                    'price' => $product['price'] ?? 0,
                    'currency' => $product['currency'] ?? 'USD',
                    'data_amount' => $product['data_amount'] ?? null,
                    'validity_days' => $product['validity_days'] ?? null,
                    'regions' => $product['regions'] ?? [],
                    'countries' => $product['countries'] ?? [],
                    'is_active' => $product['active'] ?? true,
                    'provider' => 'maya_mobile',
                    'raw_data' => $product
                ];
            }, $products);

        } catch (\Exception $e) {
            Log::error('Maya Mobile products fetch failed', [
                'error' => $e->getMessage(),
                'filters' => $filters
            ]);
            
            return [];
        }
    }

    /**
     * Create customer in Maya Mobile
     * POST /connectivity/v1/customer
     */
    public function createCustomer(array $customerData): array
    {
        try {
            $mayaData = [
                'name' => $customerData['name'] ?? $customerData['client_name'],
                'email' => $customerData['email'] ?? $customerData['client_email'],
                'phone' => $customerData['phone'] ?? $customerData['client_phone'],
                'company' => $customerData['company'] ?? null,
                'address' => [
                    'street' => $customerData['address'] ?? '',
                    'city' => $customerData['city'] ?? '',
                    'country' => $customerData['country'] ?? 'IL',
                    'postal_code' => $customerData['postal_code'] ?? ''
                ]
            ];

            $response = $this->baseRequest()
                ->post('/customer', $mayaData);

            if (!$response->successful()) {
                throw new \Exception('Maya customer creation failed: ' . $response->body());
            }

            $data = $response->json();
            
            return [
                'success' => true,
                'customer_id' => $data['uid'],
                'maya_customer_id' => $data['uid'],
                'raw_data' => $data
            ];

        } catch (\Exception $e) {
            Log::error('Maya Mobile customer creation failed', [
                'error' => $e->getMessage(),
                'customer_data' => array_except($customerData, ['password'])
            ]);
            
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Create eSIM with data plan
     * POST /connectivity/v1/esim
     */
    public function createEsim(array $esimData): array
    {
        try {
            $mayaData = [
                'plan_type_id' => $esimData['plan_type_id'],
                'customer_id' => $esimData['customer_id'] ?? null,
                'tag' => $esimData['tag'] ?? null,
                'region' => $esimData['region'] ?? null
            ];

            // Remove null values
            $mayaData = array_filter($mayaData, fn($value) => $value !== null);

            $response = $this->baseRequest()
                ->post('/esim', $mayaData);

            if (!$response->successful()) {
                throw new \Exception('Maya eSIM creation failed: ' . $response->body());
            }

            $data = $response->json();
            
            return [
                'success' => true,
                'esim_id' => $data['uid'],
                'iccid' => $data['iccid'],
                'qr_code' => $data['qr_code'],
                'activation_code' => $data['activation_code'] ?? null,
                'status' => $data['status'],
                'raw_data' => $data
            ];

        } catch (\Exception $e) {
            Log::error('Maya Mobile eSIM creation failed', [
                'error' => $e->getMessage(),
                'esim_data' => $esimData
            ]);
            
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Get eSIM status and usage
     * GET /connectivity/v1/esim/{esim_id}
     */
    public function getEsimStatus(string $esimId): array
    {
        try {
            $response = $this->baseRequest()
                ->get("/esim/{$esimId}");

            if (!$response->successful()) {
                throw new \Exception('Maya eSIM status check failed: ' . $response->body());
            }

            $data = $response->json();
            
            return [
                'success' => true,
                'esim_id' => $data['uid'],
                'iccid' => $data['iccid'],
                'status' => $data['status'],
                'data_usage' => [
                    'used' => $data['data_usage']['used'] ?? 0,
                    'remaining' => $data['data_usage']['remaining'] ?? 0,
                    'total' => $data['data_usage']['total'] ?? 0,
                    'unit' => $data['data_usage']['unit'] ?? 'MB'
                ],
                'expires_at' => $data['expires_at'] ?? null,
                'raw_data' => $data
            ];

        } catch (\Exception $e) {
            Log::error('Maya Mobile eSIM status check failed', [
                'esim_id' => $esimId,
                'error' => $e->getMessage()
            ]);
            
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Create data plan for existing eSIM
     * POST /connectivity/v1/plan
     */
    public function createDataPlan(array $planData): array
    {
        try {
            $mayaData = [
                'esim_id' => $planData['esim_id'],
                'plan_type_id' => $planData['plan_type_id'],
                'auto_activate' => $planData['auto_activate'] ?? true
            ];

            $response = $this->baseRequest()
                ->post('/plan', $mayaData);

            if (!$response->successful()) {
                throw new \Exception('Maya data plan creation failed: ' . $response->body());
            }

            $data = $response->json();
            
            return [
                'success' => true,
                'plan_id' => $data['uid'],
                'esim_id' => $data['esim_id'],
                'status' => $data['status'],
                'activation_date' => $data['activation_date'] ?? null,
                'expiry_date' => $data['expiry_date'] ?? null,
                'raw_data' => $data
            ];

        } catch (\Exception $e) {
            Log::error('Maya Mobile data plan creation failed', [
                'error' => $e->getMessage(),
                'plan_data' => $planData
            ]);
            
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Get account balance and usage
     * GET /connectivity/v1/account/balance
     */
    public function getAccountBalance(): array
    {
        try {
            $response = $this->baseRequest()
                ->get('/account/balance');

            if (!$response->successful()) {
                throw new \Exception('Maya account balance check failed: ' . $response->body());
            }

            $data = $response->json();
            
            return [
                'success' => true,
                'balance' => $data['balance'],
                'currency' => $data['currency'] ?? 'USD',
                'credit_limit' => $data['credit_limit'] ?? null,
                'last_updated' => $data['last_updated'] ?? now()->toISOString(),
                'raw_data' => $data
            ];

        } catch (\Exception $e) {
            Log::error('Maya Mobile balance check failed', [
                'error' => $e->getMessage()
            ]);
            
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * List all plan types/products
     * GET /connectivity/v1/plan-type
     */
    public function getPlanTypes(array $filters = []): array
    {
        try {
            $response = $this->baseRequest()
                ->get('/plan-type', $filters);

            if (!$response->successful()) {
                throw new \Exception('Maya plan types fetch failed: ' . $response->body());
            }

            return [
                'success' => true,
                'plan_types' => $response->json(),
                'count' => count($response->json())
            ];

        } catch (\Exception $e) {
            Log::error('Maya Mobile plan types fetch failed', [
                'error' => $e->getMessage(),
                'filters' => $filters
            ]);
            
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Test connection to Maya Mobile API
     */
    public function testConnection(): array
    {
        try {
            $startTime = microtime(true);
            
            $response = $this->baseRequest()
                ->timeout(10)
                ->get('/account/balance');

            $responseTime = (microtime(true) - $startTime) * 1000;

            return [
                'success' => $response->successful(),
                'response_time' => round($responseTime, 2),
                'status_code' => $response->status(),
                'configured' => $this->isConfigured()
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'configured' => $this->isConfigured()
            ];
        }
    }

    /**
     * Check if Maya Mobile is properly configured
     */
    public function isConfigured(): bool
    {
        return !empty($this->apiKey) && !empty($this->apiSecret);
    }

    /**
     * Get provider information
     */
    public function getProviderInfo(): array
    {
        return [
            'name' => 'Maya Mobile',
            'type' => 'esim_provider',
            'version' => '1.0',
            'base_url' => $this->baseUrl,
            'supports_tokens' => false,
            'supports_refunds' => false,
            'supports_webhooks' => false, // Maya uses polling
            'test_mode' => $this->testMode
        ];
    }

    /**
     * Create authenticated HTTP request
     */
    protected function baseRequest()
    {
        return Http::withBasicAuth($this->apiKey, $this->apiSecret)
            ->withHeaders([
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
                'User-Agent' => 'NMDigitalHub-PaymentGateway/1.0'
            ])
            ->baseUrl($this->baseUrl)
            ->timeout(30);
    }

    /**
     * Sync products from Maya Mobile API
     */
    public function syncProducts(): array
    {
        $products = $this->getProducts();
        
        if (empty($products)) {
            return [
                'success' => false,
                'synced' => 0,
                'message' => 'No products retrieved from Maya Mobile'
            ];
        }

        $synced = 0;
        foreach ($products as $product) {
            // Cache product data
            Cache::put(
                "maya_product_{$product['id']}", 
                $product, 
                now()->addHours(24)
            );
            $synced++;
        }

        Log::info('Maya Mobile products synced', [
            'total_products' => count($products),
            'synced' => $synced
        ]);

        return [
            'success' => true,
            'synced' => $synced,
            'total' => count($products),
            'message' => "Successfully synced {$synced} products from Maya Mobile"
        ];
    }
}