<?php

namespace NMDigitalHub\PaymentGateway\Providers\Services;

use NMDigitalHub\PaymentGateway\Contracts\ServiceProviderInterface;
use App\Models\ServiceProvider;
use App\Models\ApiEndpoint;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class MayaMobileProvider implements ServiceProviderInterface
{
    protected ?ServiceProvider $serviceProvider = null;
    protected array $config = [];
    
    public function __construct()
    {
        $this->loadServiceProvider();
    }

    protected function loadServiceProvider(): void
    {
        $this->serviceProvider = ServiceProvider::where('slug', 'maya-mobile')
            ->where('is_active', true)
            ->first();
            
        if (!$this->serviceProvider) {
            throw new \Exception('Maya Mobile service provider not configured');
        }
        
        $this->config = array_merge(
            $this->getDefaultConfig(),
            $this->serviceProvider->metadata ?? []
        );
    }

    protected function getDefaultConfig(): array
    {
        return [
            'base_url' => 'https://api.maya-mobile.com/v1',
            'timeout' => 30,
            'retry_attempts' => 3,
            'default_country' => 'IL',
            'currency' => 'USD',
        ];
    }

    public function testConnection(): bool
    {
        try {
            $endpoint = $this->getEndpoint('health_check');
            if (!$endpoint) {
                return $this->serviceProvider->testApiConnection()['success'] ?? false;
            }
            
            $response = $endpoint->makeRequest();
            return $response['success'] ?? false;
        } catch (\Exception $e) {
            Log::error('Maya Mobile connection test failed', [
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    public function getBalance(): array
    {
        $endpoint = $this->getEndpoint('get_balance');
        if (!$endpoint) {
            throw new \Exception('Maya Mobile balance endpoint not configured');
        }

        try {
            $response = $endpoint->makeRequest();
            
            if (!$response['success']) {
                throw new \Exception($response['error'] ?? 'Failed to get balance');
            }

            return [
                'balance' => $response['data']['balance'] ?? 0,
                'currency' => $response['data']['currency'] ?? 'USD',
                'last_updated' => now(),
                'credit_limit' => $response['data']['credit_limit'] ?? null,
            ];
        } catch (\Exception $e) {
            throw new \Exception('Maya Mobile balance check failed: ' . $e->getMessage());
        }
    }

    public function getProducts(array $filters = []): array
    {
        $endpoint = $this->getEndpoint('list_products');
        if (!$endpoint) {
            throw new \Exception('Maya Mobile products endpoint not configured');
        }

        try {
            $params = array_merge([
                'country' => $filters['country'] ?? $this->config['default_country'],
                'type' => $filters['type'] ?? 'esim',
                'status' => $filters['status'] ?? 'active',
            ], $filters);
            
            $response = $endpoint->makeRequest($params);
            
            if (!$response['success']) {
                throw new \Exception($response['error'] ?? 'Failed to get products');
            }

            return [
                'products' => $this->transformProducts($response['data']['products'] ?? []),
                'total' => $response['data']['total'] ?? 0,
                'page' => $response['data']['page'] ?? 1,
                'per_page' => $response['data']['per_page'] ?? 50,
            ];
        } catch (\Exception $e) {
            throw new \Exception('Maya Mobile products fetch failed: ' . $e->getMessage());
        }
    }

    public function getProduct(string $productId): array
    {
        $endpoint = $this->getEndpoint('get_product');
        if (!$endpoint) {
            throw new \Exception('Maya Mobile get product endpoint not configured');
        }

        try {
            $response = $endpoint->makeRequest(['product_id' => $productId]);
            
            if (!$response['success']) {
                throw new \Exception($response['error'] ?? 'Product not found');
            }

            return $this->transformProduct($response['data']);
        } catch (\Exception $e) {
            throw new \Exception('Maya Mobile product fetch failed: ' . $e->getMessage());
        }
    }

    public function createOrder(array $orderData): array
    {
        $endpoint = $this->getEndpoint('create_order');
        if (!$endpoint) {
            throw new \Exception('Maya Mobile create order endpoint not configured');
        }

        try {
            $params = $this->prepareOrderData($orderData);
            $response = $endpoint->makeRequest($params);
            
            if (!$response['success']) {
                throw new \Exception($response['error'] ?? 'Order creation failed');
            }

            return [
                'order_id' => $response['data']['orderId'] ?? '',
                'status' => $response['data']['status'] ?? 'pending',
                'iccid' => $response['data']['iccid'] ?? null,
                'qr_code' => $response['data']['qrCode'] ?? null,
                'activation_code' => $response['data']['activationCode'] ?? null,
                'expiry_date' => isset($response['data']['expiryDate']) 
                    ? Carbon::parse($response['data']['expiryDate']) 
                    : null,
                'data_limit' => $response['data']['dataLimit'] ?? null,
                'validity_days' => $response['data']['validityDays'] ?? null,
            ];
        } catch (\Exception $e) {
            throw new \Exception('Maya Mobile order creation failed: ' . $e->getMessage());
        }
    }

    public function getOrderStatus(string $orderId): array
    {
        $endpoint = $this->getEndpoint('get_order_status');
        if (!$endpoint) {
            throw new \Exception('Maya Mobile order status endpoint not configured');
        }

        try {
            $response = $endpoint->makeRequest(['order_id' => $orderId]);
            
            if (!$response['success']) {
                throw new \Exception($response['error'] ?? 'Order status check failed');
            }

            return [
                'order_id' => $orderId,
                'status' => $response['data']['status'] ?? 'unknown',
                'provisioning_status' => $response['data']['provisioningStatus'] ?? null,
                'activation_status' => $response['data']['activationStatus'] ?? null,
                'data_usage' => $response['data']['dataUsage'] ?? null,
                'remaining_data' => $response['data']['remainingData'] ?? null,
                'last_activity' => isset($response['data']['lastActivity']) 
                    ? Carbon::parse($response['data']['lastActivity']) 
                    : null,
            ];
        } catch (\Exception $e) {
            throw new \Exception('Maya Mobile order status check failed: ' . $e->getMessage());
        }
    }

    public function cancelOrder(string $orderId): bool
    {
        $endpoint = $this->getEndpoint('cancel_order');
        if (!$endpoint) {
            throw new \Exception('Maya Mobile cancel order endpoint not configured');
        }

        try {
            $response = $endpoint->makeRequest(['order_id' => $orderId]);
            
            if (!$response['success']) {
                throw new \Exception($response['error'] ?? 'Order cancellation failed');
            }

            return $response['data']['cancelled'] ?? false;
        } catch (\Exception $e) {
            throw new \Exception('Maya Mobile order cancellation failed: ' . $e->getMessage());
        }
    }

    public function updateProduct(string $productId, array $updates): array
    {
        // Maya Mobile לא תומך בעדכון מוצרים
        throw new \Exception('Maya Mobile does not support product updates');
    }

    public function getProviderInfo(): array
    {
        return [
            'name' => 'Maya Mobile',
            'hebrew_name' => 'מאיה מובייל',
            'type' => 'esim',
            'supported_countries' => ['IL', 'US', 'EU', 'GLOBAL'],
            'features' => [
                'esim_provisioning',
                'real_time_activation',
                'data_usage_tracking',
                'multiple_countries',
                'qr_code_generation',
            ],
            'api_version' => 'v1',
            'documentation_url' => 'https://docs.maya-mobile.com',
        ];
    }

    public function getRequiredConfig(): array
    {
        return [
            'api_key' => [
                'label' => 'API Key',
                'type' => 'password',
                'required' => true,
                'description' => 'Maya Mobile API key'
            ],
            'api_secret' => [
                'label' => 'API Secret',
                'type' => 'password',
                'required' => true,
                'description' => 'Maya Mobile API secret'
            ],
            'base_url' => [
                'label' => 'Base URL',
                'type' => 'url',
                'required' => true,
                'default' => 'https://api.maya-mobile.com/v1'
            ]
        ];
    }

    public function validateWebhook(array $payload, string $signature): bool
    {
        $webhookSecret = $this->serviceProvider->webhook_secret;
        
        if (!$webhookSecret) {
            return false;
        }

        $expectedSignature = hash_hmac('sha256', json_encode($payload), $webhookSecret);
        return hash_equals($expectedSignature, $signature);
    }

    public function handleWebhook(array $payload): ?array
    {
        try {
            // Maya Mobile משתמש ב-polling במקום webhooks
            // אך נשמור על תמיכה לעתיד
            
            if (!isset($payload['order_id']) || !isset($payload['status'])) {
                throw new \Exception('Invalid Maya Mobile webhook payload');
            }

            return [
                'order_id' => $payload['order_id'],
                'status' => $payload['status'],
                'event_type' => $payload['event'] ?? 'status_update',
                'timestamp' => $payload['timestamp'] ?? now(),
                'data' => $payload['data'] ?? [],
            ];
            
        } catch (\Exception $e) {
            throw new \Exception('Maya Mobile webhook handling failed: ' . $e->getMessage());
        }
    }

    public function syncProducts(): array
    {
        try {
            $products = $this->getProducts(['status' => 'active']);
            $syncedCount = 0;
            $errors = [];

            foreach ($products['products'] as $product) {
                try {
                    // כאן ניתן לשמור את המוצרים במאגר המידע המקומי
                    // לדוגמה: MayaNetEsimProduct::updateOrCreate(...)
                    $syncedCount++;
                } catch (\Exception $e) {
                    $errors[] = "Product {$product['id']}: {$e->getMessage()}";
                }
            }

            return [
                'synced' => $syncedCount,
                'total' => count($products['products']),
                'errors' => $errors,
                'success' => empty($errors) || $syncedCount > 0
            ];
            
        } catch (\Exception $e) {
            throw new \Exception('Maya Mobile sync failed: ' . $e->getMessage());
        }
    }

    public function syncOrders(string $from = null, string $to = null): array
    {
        // Maya Mobile לא תומך בשליפת היסטוריית הזמנות
        // רק בדיקת סטטוס להזמנות קיימות
        return [
            'synced' => 0,
            'message' => 'Maya Mobile uses polling for order status updates'
        ];
    }

    public function getReports(string $type, array $filters = []): array
    {
        switch ($type) {
            case 'usage':
                return $this->getUsageReport($filters);
            case 'orders':
                return $this->getOrdersReport($filters);
            default:
                throw new \Exception("Unsupported report type: $type");
        }
    }

    protected function getEndpoint(string $name): ?ApiEndpoint
    {
        return $this->serviceProvider->endpoints()
            ->where('name', $name)
            ->where('is_active', true)
            ->first();
    }

    protected function prepareOrderData(array $orderData): array
    {
        return [
            'product_id' => $orderData['product_id'],
            'customer' => [
                'name' => $orderData['customer_name'],
                'email' => $orderData['customer_email'],
                'phone' => $orderData['customer_phone'] ?? null,
            ],
            'destination' => $orderData['destination'] ?? $this->config['default_country'],
            'start_date' => $orderData['start_date'] ?? null,
            'end_date' => $orderData['end_date'] ?? null,
            'metadata' => $orderData['metadata'] ?? [],
        ];
    }

    protected function transformProducts(array $products): array
    {
        return array_map([$this, 'transformProduct'], $products);
    }

    protected function transformProduct(array $product): array
    {
        return [
            'id' => $product['id'] ?? '',
            'name' => $product['name'] ?? '',
            'description' => $product['description'] ?? '',
            'price' => $product['price'] ?? 0,
            'currency' => $product['currency'] ?? 'USD',
            'countries' => $product['countries'] ?? [],
            'data_limit' => $product['dataLimit'] ?? null,
            'validity_days' => $product['validityDays'] ?? null,
            'type' => $product['type'] ?? 'esim',
            'status' => $product['status'] ?? 'active',
            'features' => $product['features'] ?? [],
        ];
    }

    protected function getUsageReport(array $filters): array
    {
        // דוח שימוש בנתונים
        return [
            'type' => 'usage',
            'period' => $filters['period'] ?? '30_days',
            'total_data_used' => 0,
            'active_connections' => 0,
            'details' => []
        ];
    }

    protected function getOrdersReport(array $filters): array
    {
        // דוח הזמנות
        return [
            'type' => 'orders',
            'period' => $filters['period'] ?? '30_days',
            'total_orders' => 0,
            'successful_orders' => 0,
            'failed_orders' => 0,
            'details' => []
        ];
    }
}