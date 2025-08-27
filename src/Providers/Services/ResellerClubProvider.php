<?php

namespace NMDigitalHub\PaymentGateway\Providers\Services;

use NMDigitalHub\PaymentGateway\Contracts\ServiceProviderInterface;
use App\Models\ServiceProvider;
use App\Models\ApiEndpoint;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class ResellerClubProvider implements ServiceProviderInterface
{
    protected ?ServiceProvider $serviceProvider = null;
    protected array $config = [];
    
    public function __construct()
    {
        $this->loadServiceProvider();
    }

    protected function loadServiceProvider(): void
    {
        $this->serviceProvider = ServiceProvider::where('slug', 'resellerclub')
            ->where('is_active', true)
            ->first();
            
        if (!$this->serviceProvider) {
            throw new \Exception('ResellerClub service provider not configured');
        }
        
        $this->config = array_merge(
            $this->getDefaultConfig(),
            $this->serviceProvider->metadata ?? []
        );
    }

    protected function getDefaultConfig(): array
    {
        return [
            'base_url' => 'https://httpapi.com/api',
            'test_mode' => false,
            'default_currency' => 'USD',
            'default_period' => 1,
            'timeout' => 45,
            'retry_attempts' => 2,
        ];
    }

    public function testConnection(): bool
    {
        try {
            $endpoint = $this->getEndpoint('get_balance');
            if (!$endpoint) {
                return $this->serviceProvider->testApiConnection()['success'] ?? false;
            }
            
            $response = $endpoint->makeRequest();
            return $response['success'] ?? false;
        } catch (\Exception $e) {
            Log::error('ResellerClub connection test failed', [
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    public function getBalance(): array
    {
        $endpoint = $this->getEndpoint('get_balance');
        if (!$endpoint) {
            throw new \Exception('ResellerClub balance endpoint not configured');
        }

        try {
            $response = $endpoint->makeRequest();
            
            if (!$response['success']) {
                throw new \Exception($response['error'] ?? 'Failed to get balance');
            }

            return [
                'balance' => (float) ($response['data']['sellingcurrencybalance'] ?? 0),
                'currency' => $response['data']['sellingcurrency'] ?? 'USD',
                'available_balance' => (float) ($response['data']['availablebalance'] ?? 0),
                'last_updated' => now(),
            ];
        } catch (\Exception $e) {
            throw new \Exception('ResellerClub balance check failed: ' . $e->getMessage());
        }
    }

    public function getProducts(array $filters = []): array
    {
        $serviceType = $filters['service_type'] ?? 'domains';
        
        return match ($serviceType) {
            'domains' => $this->getDomainProducts($filters),
            'hosting' => $this->getHostingProducts($filters),
            'ssl' => $this->getSSLProducts($filters),
            default => throw new \Exception("Unsupported service type: $serviceType")
        };
    }

    protected function getDomainProducts(array $filters = []): array
    {
        $endpoint = $this->getEndpoint('domain_pricing');
        if (!$endpoint) {
            throw new \Exception('ResellerClub domain pricing endpoint not configured');
        }

        try {
            $params = [
                'tlds' => $filters['tlds'] ?? ['com', 'net', 'org', 'info'],
            ];
            
            $response = $endpoint->makeRequest($params);
            
            if (!$response['success']) {
                throw new \Exception($response['error'] ?? 'Failed to get domain products');
            }

            $products = [];
            foreach ($response['data'] as $tld => $pricing) {
                $products[] = [
                    'id' => 'domain_' . $tld,
                    'name' => '.' . $tld . ' Domain',
                    'type' => 'domain',
                    'tld' => $tld,
                    'pricing' => $this->transformDomainPricing($pricing),
                    'features' => [
                        'whois_privacy',
                        'dns_management',
                        'email_forwarding',
                        'url_forwarding'
                    ]
                ];
            }

            return [
                'products' => $products,
                'total' => count($products),
                'type' => 'domains'
            ];
        } catch (\Exception $e) {
            throw new \Exception('ResellerClub domain products fetch failed: ' . $e->getMessage());
        }
    }

    protected function getHostingProducts(array $filters = []): array
    {
        $endpoint = $this->getEndpoint('hosting_plans');
        if (!$endpoint) {
            throw new \Exception('ResellerClub hosting plans endpoint not configured');
        }

        try {
            $response = $endpoint->makeRequest($filters);
            
            if (!$response['success']) {
                throw new \Exception($response['error'] ?? 'Failed to get hosting products');
            }

            return [
                'products' => array_map([$this, 'transformHostingPlan'], $response['data'] ?? []),
                'total' => count($response['data'] ?? []),
                'type' => 'hosting'
            ];
        } catch (\Exception $e) {
            throw new \Exception('ResellerClub hosting products fetch failed: ' . $e->getMessage());
        }
    }

    protected function getSSLProducts(array $filters = []): array
    {
        $endpoint = $this->getEndpoint('ssl_products');
        if (!$endpoint) {
            throw new \Exception('ResellerClub SSL products endpoint not configured');
        }

        try {
            $response = $endpoint->makeRequest($filters);
            
            if (!$response['success']) {
                throw new \Exception($response['error'] ?? 'Failed to get SSL products');
            }

            return [
                'products' => array_map([$this, 'transformSSLProduct'], $response['data'] ?? []),
                'total' => count($response['data'] ?? []),
                'type' => 'ssl'
            ];
        } catch (\Exception $e) {
            throw new \Exception('ResellerClub SSL products fetch failed: ' . $e->getMessage());
        }
    }

    public function getProduct(string $productId): array
    {
        // פירוק סוג המוצר מה-ID
        if (str_starts_with($productId, 'domain_')) {
            return $this->getDomainProduct(str_replace('domain_', '', $productId));
        }
        
        throw new \Exception('Product type not supported for individual fetch');
    }

    protected function getDomainProduct(string $tld): array
    {
        $endpoint = $this->getEndpoint('domain_pricing');
        if (!$endpoint) {
            throw new \Exception('ResellerClub domain pricing endpoint not configured');
        }

        try {
            $response = $endpoint->makeRequest(['tlds' => [$tld]]);
            
            if (!$response['success'] || !isset($response['data'][$tld])) {
                throw new \Exception('Domain TLD not found');
            }

            return [
                'id' => 'domain_' . $tld,
                'name' => '.' . $tld . ' Domain',
                'type' => 'domain',
                'tld' => $tld,
                'pricing' => $this->transformDomainPricing($response['data'][$tld]),
                'features' => [
                    'whois_privacy',
                    'dns_management', 
                    'email_forwarding',
                    'url_forwarding'
                ]
            ];
        } catch (\Exception $e) {
            throw new \Exception('ResellerClub domain product fetch failed: ' . $e->getMessage());
        }
    }

    public function createOrder(array $orderData): array
    {
        $serviceType = $orderData['service_type'] ?? 'domain';
        
        return match ($serviceType) {
            'domain' => $this->registerDomain($orderData),
            'hosting' => $this->orderHosting($orderData),
            'ssl' => $this->orderSSL($orderData),
            default => throw new \Exception("Unsupported service type: $serviceType")
        };
    }

    protected function registerDomain(array $orderData): array
    {
        $endpoint = $this->getEndpoint('register_domain');
        if (!$endpoint) {
            throw new \Exception('ResellerClub domain registration endpoint not configured');
        }

        try {
            $params = $this->prepareDomainRegistrationData($orderData);
            $response = $endpoint->makeRequest($params);
            
            if (!$response['success']) {
                throw new \Exception($response['error'] ?? 'Domain registration failed');
            }

            return [
                'order_id' => $response['data']['orderid'] ?? '',
                'domain' => $orderData['domain_name'],
                'status' => $response['data']['status'] ?? 'pending',
                'expiry_date' => isset($response['data']['endtime']) 
                    ? Carbon::createFromTimestamp($response['data']['endtime']) 
                    : null,
                'nameservers' => $response['data']['ns'] ?? [],
                'invoice_id' => $response['data']['invoiceid'] ?? null,
            ];
        } catch (\Exception $e) {
            throw new \Exception('ResellerClub domain registration failed: ' . $e->getMessage());
        }
    }

    protected function orderHosting(array $orderData): array
    {
        $endpoint = $this->getEndpoint('order_hosting');
        if (!$endpoint) {
            throw new \Exception('ResellerClub hosting order endpoint not configured');
        }

        try {
            $params = $this->prepareHostingOrderData($orderData);
            $response = $endpoint->makeRequest($params);
            
            if (!$response['success']) {
                throw new \Exception($response['error'] ?? 'Hosting order failed');
            }

            return [
                'order_id' => $response['data']['orderid'] ?? '',
                'status' => $response['data']['status'] ?? 'pending',
                'server_details' => $response['data']['server'] ?? [],
                'control_panel_url' => $response['data']['cp_url'] ?? null,
                'login_details' => $response['data']['login'] ?? [],
            ];
        } catch (\Exception $e) {
            throw new \Exception('ResellerClub hosting order failed: ' . $e->getMessage());
        }
    }

    public function getOrderStatus(string $orderId): array
    {
        $endpoint = $this->getEndpoint('order_details');
        if (!$endpoint) {
            throw new \Exception('ResellerClub order details endpoint not configured');
        }

        try {
            $response = $endpoint->makeRequest(['order_id' => $orderId]);
            
            if (!$response['success']) {
                throw new \Exception($response['error'] ?? 'Order status check failed');
            }

            return [
                'order_id' => $orderId,
                'status' => $response['data']['orderstatus'] ?? 'unknown',
                'creation_date' => isset($response['data']['creationtime']) 
                    ? Carbon::createFromTimestamp($response['data']['creationtime']) 
                    : null,
                'expiry_date' => isset($response['data']['endtime']) 
                    ? Carbon::createFromTimestamp($response['data']['endtime']) 
                    : null,
                'auto_renew' => $response['data']['autorenew'] ?? false,
                'invoice_id' => $response['data']['invoiceid'] ?? null,
            ];
        } catch (\Exception $e) {
            throw new \Exception('ResellerClub order status check failed: ' . $e->getMessage());
        }
    }

    public function cancelOrder(string $orderId): bool
    {
        $endpoint = $this->getEndpoint('cancel_order');
        if (!$endpoint) {
            throw new \Exception('ResellerClub cancel order endpoint not configured');
        }

        try {
            $response = $endpoint->makeRequest(['order_id' => $orderId]);
            
            if (!$response['success']) {
                throw new \Exception($response['error'] ?? 'Order cancellation failed');
            }

            return $response['data']['result'] === 'success';
        } catch (\Exception $e) {
            throw new \Exception('ResellerClub order cancellation failed: ' . $e->getMessage());
        }
    }

    public function updateProduct(string $productId, array $updates): array
    {
        // ResellerClub תומך בעדכונים מסוימים
        $endpoint = $this->getEndpoint('modify_order');
        if (!$endpoint) {
            throw new \Exception('ResellerClub modify order endpoint not configured');
        }

        try {
            $params = array_merge(['order_id' => $productId], $updates);
            $response = $endpoint->makeRequest($params);
            
            if (!$response['success']) {
                throw new \Exception($response['error'] ?? 'Product update failed');
            }

            return $response['data'];
        } catch (\Exception $e) {
            throw new \Exception('ResellerClub product update failed: ' . $e->getMessage());
        }
    }

    public function getProviderInfo(): array
    {
        return [
            'name' => 'ResellerClub',
            'hebrew_name' => 'ריסלר קלאב',
            'type' => 'multi_service',
            'services' => ['domains', 'hosting', 'ssl', 'email'],
            'supported_tlds' => [
                'com', 'net', 'org', 'info', 'biz', 'co.uk', 'de', 'eu', 'in', 
                'co.in', 'net.in', 'org.in', 'firm.in', 'gen.in', 'ind.in'
            ],
            'features' => [
                'domain_registration',
                'domain_transfer',
                'whois_privacy',
                'dns_management',
                'hosting_services',
                'ssl_certificates',
                'email_hosting'
            ],
            'api_version' => 'v4',
            'documentation_url' => 'https://manage.resellerclub.com/kb/answer/751',
        ];
    }

    public function getRequiredConfig(): array
    {
        return [
            'api_key' => [
                'label' => 'API Key',
                'type' => 'password',
                'required' => true,
                'description' => 'ResellerClub API key (Reseller ID)'
            ],
            'api_secret' => [
                'label' => 'API Password',
                'type' => 'password',
                'required' => true,
                'description' => 'ResellerClub API password'
            ],
            'base_url' => [
                'label' => 'API Base URL',
                'type' => 'url',
                'required' => true,
                'default' => 'https://httpapi.com/api'
            ],
            'test_mode' => [
                'label' => 'Test Mode',
                'type' => 'boolean',
                'required' => false,
                'description' => 'Use demo API environment'
            ]
        ];
    }

    public function validateWebhook(array $payload, string $signature): bool
    {
        // ResellerClub לא משתמש ב-webhooks באופן סטנדרטי
        return true;
    }

    public function handleWebhook(array $payload): ?array
    {
        // ResellerClub לא שולח webhooks
        return null;
    }

    public function syncProducts(): array
    {
        try {
            $syncedCount = 0;
            $errors = [];
            $services = ['domains', 'hosting', 'ssl'];

            foreach ($services as $service) {
                try {
                    $products = $this->getProducts(['service_type' => $service]);
                    // כאן ניתן לשמור במאגר מידע מקומי
                    $syncedCount += count($products['products']);
                } catch (\Exception $e) {
                    $errors[] = "Service $service: {$e->getMessage()}";
                }
            }

            return [
                'synced' => $syncedCount,
                'services' => $services,
                'errors' => $errors,
                'success' => empty($errors) || $syncedCount > 0
            ];
            
        } catch (\Exception $e) {
            throw new \Exception('ResellerClub sync failed: ' . $e->getMessage());
        }
    }

    public function syncOrders(string $from = null, string $to = null): array
    {
        $endpoint = $this->getEndpoint('search_orders');
        if (!$endpoint) {
            throw new \Exception('ResellerClub search orders endpoint not configured');
        }

        try {
            $params = array_filter([
                'creation-date-start' => $from ? Carbon::parse($from)->timestamp : null,
                'creation-date-end' => $to ? Carbon::parse($to)->timestamp : null,
                'no-of-records' => 100,
                'page-no' => 1,
            ]);
            
            $response = $endpoint->makeRequest($params);
            
            if (!$response['success']) {
                throw new \Exception($response['error'] ?? 'Orders sync failed');
            }

            return [
                'synced' => count($response['data'] ?? []),
                'orders' => $response['data'] ?? [],
                'from' => $from,
                'to' => $to
            ];
        } catch (\Exception $e) {
            throw new \Exception('ResellerClub orders sync failed: ' . $e->getMessage());
        }
    }

    public function getReports(string $type, array $filters = []): array
    {
        switch ($type) {
            case 'sales':
                return $this->getSalesReport($filters);
            case 'domains':
                return $this->getDomainsReport($filters);
            default:
                throw new \Exception("Unsupported report type: $type");
        }
    }

    // Helper Methods
    protected function getEndpoint(string $name): ?ApiEndpoint
    {
        return $this->serviceProvider->endpoints()
            ->where('name', $name)
            ->where('is_active', true)
            ->first();
    }

    protected function prepareDomainRegistrationData(array $orderData): array
    {
        return [
            'domain-name' => $orderData['domain_name'],
            'years' => $orderData['period'] ?? $this->config['default_period'],
            'ns' => $orderData['nameservers'] ?? ['ns1.resellerclub.com', 'ns2.resellerclub.com'],
            'customer-id' => $orderData['customer_id'],
            'reg-contact-id' => $orderData['contact_id'],
            'admin-contact-id' => $orderData['contact_id'],
            'tech-contact-id' => $orderData['contact_id'],
            'billing-contact-id' => $orderData['contact_id'],
            'invoice-option' => 'NoInvoice',
            'protect-privacy' => $orderData['whois_privacy'] ?? true,
        ];
    }

    protected function prepareHostingOrderData(array $orderData): array
    {
        return [
            'domain-name' => $orderData['domain_name'],
            'plan-id' => $orderData['plan_id'],
            'months' => $orderData['period'] ?? 12,
            'customer-id' => $orderData['customer_id'],
        ];
    }

    protected function transformDomainPricing(array $pricing): array
    {
        $transformed = [];
        foreach ($pricing as $period => $price) {
            $transformed[] = [
                'period' => (int) $period,
                'price' => (float) $price['addnewdomain']['selling_price'] ?? 0,
                'currency' => $price['addnewdomain']['selling_currency'] ?? 'USD',
                'action' => 'register'
            ];
        }
        return $transformed;
    }

    protected function transformHostingPlan(array $plan): array
    {
        return [
            'id' => $plan['planid'] ?? '',
            'name' => $plan['name'] ?? '',
            'description' => $plan['description'] ?? '',
            'price' => (float) ($plan['price'] ?? 0),
            'currency' => $plan['currency'] ?? 'USD',
            'features' => $plan['features'] ?? [],
            'bandwidth' => $plan['bandwidth'] ?? null,
            'storage' => $plan['storage'] ?? null,
        ];
    }

    protected function transformSSLProduct(array $product): array
    {
        return [
            'id' => $product['productid'] ?? '',
            'name' => $product['productname'] ?? '',
            'type' => $product['type'] ?? 'ssl',
            'validation_type' => $product['validation'] ?? 'dv',
            'warranty' => $product['warranty'] ?? null,
            'price' => (float) ($product['price'] ?? 0),
            'currency' => $product['currency'] ?? 'USD',
        ];
    }

    protected function getSalesReport(array $filters): array
    {
        return [
            'type' => 'sales',
            'period' => $filters['period'] ?? '30_days',
            'total_sales' => 0,
            'total_revenue' => 0,
            'currency' => 'USD',
            'breakdown' => []
        ];
    }

    protected function getDomainsReport(array $filters): array
    {
        return [
            'type' => 'domains',
            'period' => $filters['period'] ?? '30_days',
            'total_domains' => 0,
            'new_registrations' => 0,
            'renewals' => 0,
            'transfers' => 0,
            'details' => []
        ];
    }
}