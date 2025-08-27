<?php

namespace NMDigitalHub\PaymentGateway;

use NMDigitalHub\PaymentGateway\Contracts\PaymentProviderInterface;
use NMDigitalHub\PaymentGateway\Contracts\ServiceProviderInterface;
use NMDigitalHub\PaymentGateway\Enums\PaymentProvider;
use NMDigitalHub\PaymentGateway\Enums\ServiceProvider;
use NMDigitalHub\PaymentGateway\DataObjects\PaymentSessionData;
use NMDigitalHub\PaymentGateway\DataObjects\TransactionData;
use NMDigitalHub\PaymentGateway\DataObjects\PaymentRequest;
use NMDigitalHub\PaymentGateway\Models\PaymentTransaction;
use NMDigitalHub\PaymentGateway\Models\PaymentPage;
use App\Models\ServiceProvider as ServiceProviderModel;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Laravel\SerializableClosure\SerializableClosure;

class PaymentGatewayManager
{
    protected array $paymentProviders = [];
    protected array $serviceProviders = [];
    protected array $config;

    public function __construct(array $config = [])
    {
        $this->config = array_merge($this->getDefaultConfig(), $config);
        $this->loadProviders();
    }

    protected function getDefaultConfig(): array
    {
        return [
            'cache_duration' => 3600,
            'default_currency' => 'ILS',
            'default_language' => 'he',
            'auto_sync' => false,
            'webhook_verification' => true,
        ];
    }

    protected function loadProviders(): void
    {
        // טעינת ספקי תשלומים פעילים
        foreach (PaymentProvider::cases() as $provider) {
            try {
                $instance = $provider->createProvider();
                $this->paymentProviders[$provider->value] = $instance;
            } catch (\Exception $e) {
                Log::warning("Failed to load payment provider {$provider->value}", [
                    'error' => $e->getMessage()
                ]);
            }
        }

        // טעינת ספקי שירותים פעילים
        foreach (ServiceProvider::cases() as $provider) {
            try {
                $instance = $provider->createProvider();
                $this->serviceProviders[$provider->value] = $instance;
            } catch (\Exception $e) {
                Log::warning("Failed to load service provider {$provider->value}", [
                    'error' => $e->getMessage()
                ]);
            }
        }
    }

    /**
     * Payment Provider Methods
     */
    
    public function payment(string $provider = null): PaymentProviderInterface
    {
        if (!$provider) {
            $provider = $this->getBestPaymentProvider();
        }

        if (!isset($this->paymentProviders[$provider])) {
            throw new \InvalidArgumentException("Payment provider '$provider' not found or not configured");
        }

        return $this->paymentProviders[$provider];
    }

    public function service(string $provider = null): ServiceProviderInterface
    {
        if (!$provider) {
            throw new \InvalidArgumentException('Service provider name is required');
        }

        if (!isset($this->serviceProviders[$provider])) {
            throw new \InvalidArgumentException("Service provider '$provider' not found or not configured");
        }

        return $this->serviceProviders[$provider];
    }

    public function getAvailablePaymentProviders(): Collection
    {
        return collect($this->paymentProviders)->map(function ($provider, $key) {
            return [
                'key' => $key,
                'info' => $provider->getProviderInfo(),
                'healthy' => $this->isProviderHealthy($key, 'payment')
            ];
        });
    }

    public function getAvailableServiceProviders(): Collection
    {
        return collect($this->serviceProviders)->map(function ($provider, $key) {
            return [
                'key' => $key,
                'info' => $provider->getProviderInfo(),
                'healthy' => $this->isProviderHealthy($key, 'service')
            ];
        });
    }

    public function getAvailableProviders(): Collection
    {
        $allProviders = collect();
        
        // הוספת ספקי תשלומים
        $paymentProviders = $this->getAvailablePaymentProviders()->map(function ($provider) {
            return [
                'name' => $provider['key'],
                'display_name' => $provider['info']['name'] ?? $provider['key'],
                'type' => 'payment',
                'healthy' => $provider['healthy'],
                'supports_tokens' => $provider['info']['supports_tokens'] ?? false,
                'supports_3ds' => $provider['info']['supports_3ds'] ?? false,
            ];
        });
        
        // הוספת ספקי שירותים
        $serviceProviders = $this->getAvailableServiceProviders()->map(function ($provider) {
            return [
                'name' => $provider['key'], 
                'display_name' => $provider['info']['name'] ?? $provider['key'],
                'type' => 'service',
                'healthy' => $provider['healthy'],
                'supports_tokens' => false,
                'supports_3ds' => false,
            ];
        });
        
        return $allProviders->concat($paymentProviders)->concat($serviceProviders);
    }

    protected function getBestPaymentProvider(string $countryCode = 'IL'): string
    {
        $provider = PaymentProvider::getBestForCountry($countryCode);
        
        if (!$provider || !isset($this->paymentProviders[$provider->value])) {
            // חזרה לספק ראשון זמין
            return array_key_first($this->paymentProviders) ?? 'cardcom';
        }

        return $provider->value;
    }

    protected function isProviderHealthy(string $provider, string $type): bool
    {
        $cacheKey = "provider_health_{$type}_{$provider}";
        
        return Cache::remember($cacheKey, 300, function () use ($provider, $type) {
            try {
                if ($type === 'payment' && isset($this->paymentProviders[$provider])) {
                    return $this->paymentProviders[$provider]->testConnection();
                }
                
                if ($type === 'service' && isset($this->serviceProviders[$provider])) {
                    return $this->serviceProviders[$provider]->testConnection();
                }
                
                return false;
            } catch (\Exception $e) {
                return false;
            }
        });
    }

    /**
     * Quick Payment Methods
     */
    
    public function createPayment(array $parameters, string $provider = null): PaymentSessionData
    {
        $paymentProvider = $this->payment($provider);
        
        // הוספת נתוני ברירת מחדל
        $parameters = array_merge([
            'currency' => $this->config['default_currency'],
            'language' => $this->config['default_language'],
            'success_url' => route('payment.success'),
            'failed_url' => route('payment.failed'),
            'webhook_url' => route('payment.webhook'),
        ], $parameters);

        return $paymentProvider->createPaymentSession($parameters);
    }

    public function processPaymentRequest(PaymentRequest $request, string $provider = null): PaymentSessionData
    {
        $errors = $request->validate();
        if (!empty($errors)) {
            throw new \InvalidArgumentException('Payment request validation failed: ' . implode(', ', $errors));
        }

        $paymentProvider = $this->payment($provider ?: $request->getProvider());
        
        // המרת PaymentRequest לפרמטרים
        $parameters = array_merge([
            'currency' => $this->config['default_currency'],
            'language' => $this->config['default_language'],
            'success_url' => route('payment.success'),
            'failed_url' => route('payment.failed'),
            'webhook_url' => route('payment.webhook'),
        ], $request->toArray());

        $session = $paymentProvider->createPaymentSession($parameters);
        
        // שמירת קשר למודל אם קיים
        if ($request->getModel()) {
            $this->linkSessionToModel($session, $request->getModel());
        }

        return $session;
    }

    protected function linkSessionToModel(PaymentSessionData $session, $model): void
    {
        // שמירת הקישור במטאדאטה או במאגר מידע נפרד
        Cache::put("payment_session_model_{$session->sessionReference}", [
            'model_type' => get_class($model),
            'model_id' => $model->getKey(),
        ], $session->expiresAt);
    }

    public function verifyPayment(string $reference, string $provider = null): TransactionData
    {
        $paymentProvider = $this->payment($provider);
        return $paymentProvider->verifyTransaction($reference);
    }

    public function refundPayment(string $transactionId, ?float $amount = null, string $provider = null): TransactionData
    {
        $paymentProvider = $this->payment($provider);
        return $paymentProvider->refundTransaction($transactionId, $amount);
    }

    /**
     * Service Provider Methods
     */
    
    public function getProducts(string $serviceProvider, array $filters = []): array
    {
        return $this->service($serviceProvider)->getProducts($filters);
    }

    public function createServiceOrder(string $serviceProvider, array $orderData): array
    {
        return $this->service($serviceProvider)->createOrder($orderData);
    }

    public function getServiceOrderStatus(string $serviceProvider, string $orderId): array
    {
        return $this->service($serviceProvider)->getOrderStatus($orderId);
    }

    /**
     * Page Management Methods
     */
    
    public function getPaymentPage(string $slug): ?PaymentPage
    {
        return PaymentPage::published()
            ->where('slug', $slug)
            ->first();
    }

    public function createPaymentPage(array $data): PaymentPage
    {
        return PaymentPage::create($data);
    }

    public function getPaymentPagesByType(string $type): Collection
    {
        return PaymentPage::published()
            ->byType($type)
            ->ordered()
            ->get();
    }

    /**
     * Transaction Management
     */
    
    public function getTransaction(string $transactionId): ?PaymentTransaction
    {
        return PaymentTransaction::where('transaction_id', $transactionId)
            ->orWhere('reference', $transactionId)
            ->first();
    }

    public function getTransactionsByCustomer(string $customerEmail): Collection
    {
        return PaymentTransaction::where('customer_email', $customerEmail)
            ->orderBy('created_at', 'desc')
            ->get();
    }

    public function getTransactionsByStatus(string $status, int $limit = 100): Collection
    {
        return PaymentTransaction::where('status', $status)
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();
    }

    /**
     * Webhook Handling
     */
    
    public function handleWebhook(string $provider, array $payload, string $signature = ''): ?TransactionData
    {
        try {
            $paymentProvider = $this->payment($provider);
            
            // אימות חתימה אם נדרש
            if ($this->config['webhook_verification'] && $signature) {
                if (!$paymentProvider->validateWebhookSignature(json_encode($payload), $signature)) {
                    throw new \Exception('Invalid webhook signature');
                }
            }

            $transactionData = $paymentProvider->handleWebhook($payload);
            
            if ($transactionData) {
                // שמירת העסקה במאגר המידע
                $this->saveTransaction($transactionData);
                
                // ביצוע callbacks אם קיימים
                $this->executeTransactionCallbacks($transactionData);
            }

            return $transactionData;
            
        } catch (\Exception $e) {
            Log::error('Webhook handling failed', [
                'provider' => $provider,
                'error' => $e->getMessage(),
                'payload' => $payload
            ]);
            throw $e;
        }
    }

    protected function saveTransaction(TransactionData $transactionData): PaymentTransaction
    {
        return PaymentTransaction::updateOrCreate(
            [
                'provider' => $transactionData->provider,
                'transaction_id' => $transactionData->transactionId,
            ],
            [
                'reference' => $transactionData->reference,
                'amount' => $transactionData->amount,
                'currency' => $transactionData->currency,
                'status' => $transactionData->status,
                'customer_email' => $transactionData->customerEmail,
                'customer_name' => $transactionData->customerName,
                'customer_phone' => $transactionData->customerPhone,
                'metadata' => $transactionData->metadata,
                'gateway_response' => $transactionData->gatewayResponse,
                'completed_at' => $transactionData->completedAt,
                'failure_reason' => $transactionData->failureReason,
                'gateway_transaction_id' => $transactionData->gatewayTransactionId,
                'authorization_code' => $transactionData->authorizationCode,
            ]
        );
    }

    protected function executeTransactionCallbacks(TransactionData $transactionData): void
    {
        try {
            $session = $this->getPaymentSession($transactionData->reference);
            
            if ($session) {
                if ($transactionData->isSuccessful()) {
                    $session->executeSuccessCallback($transactionData);
                } else if ($transactionData->isFailed()) {
                    $session->executeFailedCallback($transactionData);
                }
            }
        } catch (\Exception $e) {
            Log::warning('Transaction callback execution failed', [
                'transaction_id' => $transactionData->transactionId,
                'error' => $e->getMessage()
            ]);
        }
    }

    protected function getPaymentSession(string $reference): ?PaymentSessionData
    {
        // חיפוש ב-cache של כל הספקים
        foreach ($this->paymentProviders as $providerKey => $provider) {
            $cacheKey = "payment_session_{$providerKey}_{$reference}";
            $session = Cache::get($cacheKey);
            
            if ($session instanceof PaymentSessionData) {
                return $session;
            }
        }
        
        return null;
    }

    /**
     * Statistics and Reporting
     */
    
    public function getPaymentStats(array $filters = []): array
    {
        $query = PaymentTransaction::query();
        
        if (isset($filters['from'])) {
            $query->whereDate('created_at', '>=', $filters['from']);
        }
        
        if (isset($filters['to'])) {
            $query->whereDate('created_at', '<=', $filters['to']);
        }
        
        if (isset($filters['provider'])) {
            $query->where('provider', $filters['provider']);
        }

        $total = $query->count();
        $successful = $query->where('status', 'success')->count();
        $failed = $query->where('status', 'failed')->count();
        $totalAmount = $query->where('status', 'success')->sum('amount');

        return [
            'total_transactions' => $total,
            'successful_transactions' => $successful,
            'failed_transactions' => $failed,
            'success_rate' => $total > 0 ? round(($successful / $total) * 100, 2) : 0,
            'total_revenue' => $totalAmount,
            'average_transaction' => $successful > 0 ? round($totalAmount / $successful, 2) : 0,
        ];
    }

    public function getProviderStats(): array
    {
        $stats = [];
        
        foreach ($this->paymentProviders as $providerKey => $provider) {
            $transactions = PaymentTransaction::where('provider', $providerKey);
            
            $stats[$providerKey] = [
                'name' => $provider->getProviderInfo()['name'] ?? $providerKey,
                'total' => $transactions->count(),
                'successful' => $transactions->where('status', 'success')->count(),
                'revenue' => $transactions->where('status', 'success')->sum('amount'),
                'healthy' => $this->isProviderHealthy($providerKey, 'payment')
            ];
        }
        
        return $stats;
    }

    /**
     * Sync Operations
     */
    
    public function syncAllProviders(): array
    {
        $results = [];
        
        foreach ($this->serviceProviders as $providerKey => $provider) {
            try {
                $result = $provider->syncProducts();
                $results[$providerKey] = array_merge(['success' => true], $result);
            } catch (\Exception $e) {
                $results[$providerKey] = [
                    'success' => false,
                    'error' => $e->getMessage()
                ];
            }
        }
        
        return $results;
    }

    /**
     * Configuration Methods
     */
    
    public function setConfig(array $config): void
    {
        $this->config = array_merge($this->config, $config);
    }

    public function getConfig(string $key = null)
    {
        return $key ? ($this->config[$key] ?? null) : $this->config;
    }

    /**
     * Health Check
     */
    
    public function healthCheck(): array
    {
        $health = [
            'overall' => 'healthy',
            'payment_providers' => [],
            'service_providers' => [],
            'timestamp' => now(),
        ];

        $issues = 0;

        // בדיקת ספקי תשלומים
        foreach ($this->paymentProviders as $key => $provider) {
            $healthy = $this->isProviderHealthy($key, 'payment');
            $health['payment_providers'][$key] = [
                'healthy' => $healthy,
                'name' => $provider->getProviderInfo()['name'] ?? $key
            ];
            
            if (!$healthy) $issues++;
        }

        // בדיקת ספקי שירותים
        foreach ($this->serviceProviders as $key => $provider) {
            $healthy = $this->isProviderHealthy($key, 'service');
            $health['service_providers'][$key] = [
                'healthy' => $healthy,
                'name' => $provider->getProviderInfo()['name'] ?? $key
            ];
            
            if (!$healthy) $issues++;
        }

        if ($issues > 0) {
            $totalProviders = count($this->paymentProviders) + count($this->serviceProviders);
            $health['overall'] = $issues >= $totalProviders / 2 ? 'critical' : 'warning';
        }

        return $health;
    }

    /**
     * בדיקת בריאות ספק בודד
     */
    public function checkProviderHealth(string $providerName): bool
    {
        // בדיקה אם זה ספק תשלומים
        if (isset($this->paymentProviders[$providerName])) {
            return $this->isProviderHealthy($providerName, 'payment');
        }
        
        // בדיקה אם זה ספק שירותים
        if (isset($this->serviceProviders[$providerName])) {
            return $this->isProviderHealthy($providerName, 'service');
        }
        
        return false;
    }

    /**
     * קבלת סטטיסטיקות ספק בודד
     */
    public function getProviderStats(string $providerName): array
    {
        $baseStats = [
            'weekly_transactions' => 0,
            'monthly_transactions' => 0,
            'success_rate' => 0,
            'last_health_check' => 'אף פעם',
            'total_revenue' => 0,
        ];

        try {
            // חישוב סטטיסטיקות מהשבוע האחרון
            $weeklyTransactions = PaymentTransaction::where('provider', $providerName)
                ->whereBetween('created_at', [now()->subWeek(), now()])
                ->count();
            
            // חישוב סטטיסטיקות מהחודש האחרון
            $monthlyTransactions = PaymentTransaction::where('provider', $providerName)
                ->whereBetween('created_at', [now()->subMonth(), now()])
                ->count();
            
            $successfulTransactions = PaymentTransaction::where('provider', $providerName)
                ->where('status', 'success')
                ->whereBetween('created_at', [now()->subMonth(), now()])
                ->count();
            
            $totalRevenue = PaymentTransaction::where('provider', $providerName)
                ->where('status', 'success')
                ->whereBetween('created_at', [now()->subMonth(), now()])
                ->sum('amount');

            $successRate = $monthlyTransactions > 0 
                ? round(($successfulTransactions / $monthlyTransactions) * 100, 2) 
                : 0;

            return array_merge($baseStats, [
                'weekly_transactions' => $weeklyTransactions,
                'monthly_transactions' => $monthlyTransactions,
                'success_rate' => $successRate,
                'last_health_check' => now()->format('Y-m-d H:i:s'),
                'total_revenue' => $totalRevenue,
            ]);
        } catch (\Exception $e) {
            return $baseStats;
        }
    }
}