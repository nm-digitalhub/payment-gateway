<?php

namespace NMDigitalHub\PaymentGateway\Services;

use NMDigitalHub\PaymentGateway\Contracts\ServiceProviderInterface;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

/**
 * ResellerClub API Service - Based on openapi_resellerclub_updated.yaml
 * Configuration managed via admin panel settings
 * API Base: https://httpapi.com/api/ (Production) | https://test.httpapi.com/api/ (Test)
 */
class ResellerClubService implements ServiceProviderInterface
{
    protected string $baseUrl;
    protected string $userId;
    protected string $apiKey;
    protected bool $testMode;
    
    public function __construct()
    {
        // קריאת הגדרות מהמערכת הראשית דרך Laravel Settings
        $settings = app(\App\Settings\ResellerClubSettings::class);
        
        $this->testMode = $settings->test_mode ?? false;
        $this->baseUrl = $settings->getApiUrl();
        $this->userId = $settings->reseller_id ?? '';
        $this->apiKey = $settings->api_key ?? '';
    }

    /**
     * Check domain availability
     * GET /domains/available.json
     */
    public function checkDomainAvailability(array $domains): array
    {
        if (!$this->isConfigured()) {
            return [
                'success' => false,
                'error' => 'ResellerClub not configured. Please update settings in admin panel.'
            ];
        }

        try {
            $response = $this->baseRequest()
                ->get('/domains/available.json', [
                    'domain-name' => $domains,
                    'tlds' => ['com', 'net', 'org', 'co.il', 'org.il']
                ]);

            if (!$response->successful()) {
                throw new \Exception('ResellerClub API error: ' . $response->body());
            }

            $data = $response->json();
            $results = [];
            
            foreach ($domains as $domain) {
                $results[$domain] = [
                    'available' => $data[$domain]['status'] === 'available',
                    'price' => $data[$domain]['price'] ?? 0,
                    'currency' => $data[$domain]['currency'] ?? 'USD',
                    'tlds' => $data[$domain]['tlds'] ?? []
                ];
            }

            return [
                'success' => true,
                'results' => $results,
                'checked_at' => now()->toISOString()
            ];

        } catch (\Exception $e) {
            Log::error('ResellerClub domain check failed', [
                'domains' => $domains,
                'error' => $e->getMessage(),
                'configured' => $this->isConfigured()
            ]);
            
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Register domain
     * POST /domains/register.json
     */
    public function registerDomain(array $domainData): array
    {
        if (!$this->isConfigured()) {
            return [
                'success' => false,
                'error' => 'ResellerClub not configured. Please update settings in admin panel.'
            ];
        }

        try {
            $requestData = [
                'domain-name' => $domainData['domain'],
                'years' => $domainData['years'] ?? 1,
                'ns' => $domainData['nameservers'] ?? [],
                'customer-id' => $domainData['customer_id'],
                'reg-contact-id' => $domainData['contact_id'],
                'admin-contact-id' => $domainData['contact_id'],
                'tech-contact-id' => $domainData['contact_id'],
                'billing-contact-id' => $domainData['contact_id'],
                'invoice-option' => 'NoInvoice',
                'protect-privacy' => $domainData['privacy_protection'] ?? false
            ];

            $response = $this->baseRequest()
                ->post('/domains/register.json', $requestData);

            if (!$response->successful()) {
                throw new \Exception('Domain registration failed: ' . $response->body());
            }

            $data = $response->json();
            
            return [
                'success' => true,
                'order_id' => $data['entityid'],
                'domain' => $domainData['domain'],
                'status' => $data['status'] ?? 'pending',
                'expires_at' => $data['endtime'] ?? null,
                'raw_data' => $data
            ];

        } catch (\Exception $e) {
            Log::error('ResellerClub domain registration failed', [
                'domain' => $domainData['domain'] ?? 'unknown',
                'error' => $e->getMessage()
            ]);
            
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Create customer contact
     * POST /contacts/add.json
     */
    public function createContact(array $contactData): array
    {
        if (!$this->isConfigured()) {
            return [
                'success' => false,
                'error' => 'ResellerClub not configured. Please update settings in admin panel.'
            ];
        }

        try {
            $requestData = [
                'name' => $contactData['name'],
                'company' => $contactData['company'] ?? $contactData['name'],
                'email' => $contactData['email'],
                'address-line-1' => $contactData['address'],
                'city' => $contactData['city'],
                'state' => $contactData['state'] ?? $contactData['city'],
                'zipcode' => $contactData['postal_code'] ?? '00000',
                'country' => $contactData['country'] ?? 'IL',
                'phone-cc' => $contactData['phone_country_code'] ?? '972',
                'phone' => $contactData['phone'],
                'customer-id' => $contactData['customer_id'],
                'type' => 'Contact'
            ];

            $response = $this->baseRequest()
                ->post('/contacts/add.json', $requestData);

            if (!$response->successful()) {
                throw new \Exception('Contact creation failed: ' . $response->body());
            }

            $data = $response->json();
            
            return [
                'success' => true,
                'contact_id' => $data,
                'raw_data' => ['contact_id' => $data]
            ];

        } catch (\Exception $e) {
            Log::error('ResellerClub contact creation failed', [
                'email' => $contactData['email'] ?? 'unknown',
                'error' => $e->getMessage()
            ]);
            
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Create customer
     * POST /customers/signup.json
     */
    public function createCustomer(array $customerData): array
    {
        if (!$this->isConfigured()) {
            return [
                'success' => false,
                'error' => 'ResellerClub not configured. Please update settings in admin panel.'
            ];
        }

        try {
            $requestData = [
                'username' => $customerData['email'],
                'passwd' => $customerData['password'] ?? \Str::random(12),
                'name' => $customerData['name'],
                'company' => $customerData['company'] ?? $customerData['name'],
                'address-line-1' => $customerData['address'] ?? '',
                'city' => $customerData['city'] ?? '',
                'state' => $customerData['state'] ?? $customerData['city'] ?? '',
                'zipcode' => $customerData['postal_code'] ?? '00000',
                'country' => $customerData['country'] ?? 'IL',
                'phone-cc' => $customerData['phone_country_code'] ?? '972',
                'phone' => $customerData['phone'] ?? '',
                'lang-pref' => $customerData['language'] ?? 'en'
            ];

            $response = $this->baseRequest()
                ->post('/customers/signup.json', $requestData);

            if (!$response->successful()) {
                throw new \Exception('Customer creation failed: ' . $response->body());
            }

            $data = $response->json();
            
            return [
                'success' => true,
                'customer_id' => $data,
                'username' => $customerData['email'],
                'raw_data' => ['customer_id' => $data]
            ];

        } catch (\Exception $e) {
            Log::error('ResellerClub customer creation failed', [
                'email' => $customerData['email'] ?? 'unknown',
                'error' => $e->getMessage()
            ]);
            
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Get reseller details - Admin can verify their account
     * GET /resellers/details.json
     */
    public function getResellerDetails(): array
    {
        if (!$this->isConfigured()) {
            return [
                'success' => false,
                'error' => 'ResellerClub not configured. Please update settings in admin panel.'
            ];
        }

        try {
            $response = $this->baseRequest()
                ->get('/resellers/details.json');

            if (!$response->successful()) {
                throw new \Exception('Reseller details fetch failed: ' . $response->body());
            }

            $data = $response->json();
            
            return [
                'success' => true,
                'reseller_id' => $data['resellerid'],
                'name' => $data['name'],
                'company' => $data['company'],
                'email' => $data['emailaddr'],
                'balance' => $data['accountbalance'] ?? 0,
                'currency' => $data['currency'] ?? 'USD',
                'location' => $data['city'] . ', ' . $data['state'] . ', ' . $data['country'],
                'raw_data' => $data
            ];

        } catch (\Exception $e) {
            Log::error('ResellerClub reseller details fetch failed', [
                'error' => $e->getMessage()
            ]);
            
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Test connection to ResellerClub API
     */
    public function testConnection(): array
    {
        if (!$this->isConfigured()) {
            return [
                'success' => false,
                'error' => 'ResellerClub not configured. Please update User ID and API Key in admin panel.',
                'configured' => false
            ];
        }

        try {
            $startTime = microtime(true);
            
            $response = $this->baseRequest()
                ->timeout(10)
                ->get('/resellers/details.json');

            $responseTime = (microtime(true) - $startTime) * 1000;

            return [
                'success' => $response->successful(),
                'response_time' => round($responseTime, 2),
                'status_code' => $response->status(),
                'configured' => true,
                'test_mode' => $this->testMode
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'configured' => true,
                'test_mode' => $this->testMode
            ];
        }
    }

    /**
     * Check if ResellerClub is properly configured
     */
    public function isConfigured(): bool
    {
        return !empty($this->userId) && !empty($this->apiKey);
    }

    /**
     * Get provider information
     */
    public function getProviderInfo(): array
    {
        return [
            'name' => 'ResellerClub',
            'type' => 'domain_hosting_provider',
            'version' => '3.16',
            'base_url' => $this->baseUrl,
            'configured' => $this->isConfigured(),
            'test_mode' => $this->testMode,
            'configuration_required' => [
                'user_id' => 'ResellerClub User ID',
                'api_key' => 'ResellerClub API Key'
            ],
            'services' => [
                'domains' => true,
                'hosting' => true,
                'ssl_certificates' => true,
                'email_hosting' => true
            ]
        ];
    }

    /**
     * Create authenticated HTTP request
     */
    protected function baseRequest()
    {
        return Http::asForm()
            ->withHeaders([
                'Accept' => 'application/json',
                'User-Agent' => 'NMDigitalHub-PaymentGateway/1.0'
            ])
            ->baseUrl($this->baseUrl)
            ->timeout(30)
            ->withOptions([
                'query' => [
                    'auth-userid' => $this->userId,
                    'api-key' => $this->apiKey
                ]
            ]);
    }

    /**
     * Sync available services from ResellerClub
     */
    public function syncServices(): array
    {
        if (!$this->isConfigured()) {
            return [
                'success' => false,
                'error' => 'ResellerClub not configured. Please update settings in admin panel.'
            ];
        }

        try {
            // Get reseller details as basic test
            $details = $this->getResellerDetails();
            
            if (!$details['success']) {
                return $details;
            }
            
            // Cache reseller info
            Cache::put('resellerclub_reseller_info', $details, now()->addHours(24));
            
            Log::info('ResellerClub basic sync completed', [
                'reseller_id' => $details['reseller_id'],
                'company' => $details['company']
            ]);

            return [
                'success' => true,
                'message' => 'ResellerClub connection verified and basic info synced',
                'reseller' => $details['company']
            ];

        } catch (\Exception $e) {
            Log::error('ResellerClub sync failed', [
                'error' => $e->getMessage()
            ]);
            
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
}