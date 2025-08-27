<?php

namespace NMDigitalHub\PaymentGateway\Providers;

use NMDigitalHub\PaymentGateway\Contracts\PaymentProviderInterface;
use NMDigitalHub\PaymentGateway\DataObjects\PaymentSessionData;
use NMDigitalHub\PaymentGateway\DataObjects\TransactionData;
use NMDigitalHub\PaymentGateway\Enums\PaymentProvider;
use NMDigitalHub\PaymentGateway\Enums\PaymentStatus;
use NMDigitalHub\PaymentGateway\Models\PaymentTransaction;
use NMDigitalHub\PaymentGateway\Models\ProviderSetting;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Laravel\SerializableClosure\SerializableClosure;
use Carbon\Carbon;

abstract class AbstractPaymentProvider implements PaymentProviderInterface
{
    protected PaymentProvider $provider;
    protected array $config = [];
    protected ?SerializableClosure $onSuccessCallback = null;
    protected ?SerializableClosure $onFailureCallback = null;

    public function __construct()
    {
        $this->loadConfiguration();
    }

    /**
     * טעינת תצורה ממאגר המידע
     */
    protected function loadConfiguration(): void
    {
        $settings = ProviderSetting::where('provider', $this->provider->value)
            ->where('is_active', true)
            ->get()
            ->keyBy('key')
            ->map(fn($setting) => $setting->getValue())
            ->toArray();

        $this->config = array_merge($this->getDefaultConfig(), $settings);
    }

    /**
     * תצורת ברירת מחדל
     */
    protected function getDefaultConfig(): array
    {
        return [
            'sandbox_mode' => true,
            'currency' => 'ILS',
            'timeout' => 30,
            'retry_attempts' => 3,
        ];
    }

    /**
     * בקשת HTTP מאובטחת
     */
    protected function makeRequest(string $method, string $url, array $data = [], array $headers = []): Response
    {
        $http = Http::timeout($this->config['timeout'] ?? 30)
            ->retry($this->config['retry_attempts'] ?? 3, 1000)
            ->withHeaders(array_merge([
                'User-Agent' => 'NM-DigitalHub-PaymentGateway/1.0',
                'Accept' => 'application/json',
            ], $headers));

        $response = match (strtoupper($method)) {
            'GET' => $http->get($url, $data),
            'POST' => $http->post($url, $data),
            'PUT' => $http->put($url, $data),
            'DELETE' => $http->delete($url, $data),
            default => throw new \InvalidArgumentException("Unsupported HTTP method: $method")
        };

        // לוגינג
        if ($this->config['debug_mode'] ?? false) {
            Log::info('Payment Provider API Request', [
                'provider' => $this->provider->value,
                'method' => $method,
                'url' => $url,
                'status' => $response->status(),
                'response' => $response->json(),
            ]);
        }

        return $response;
    }

    /**
     * שמירת עסקה במאגר המידע
     */
    protected function saveTransaction(TransactionData $transaction): PaymentTransaction
    {
        return PaymentTransaction::updateOrCreate(
            [
                'provider' => $this->provider->value,
                'transaction_id' => $transaction->transactionId,
            ],
            [
                'reference' => $transaction->reference,
                'amount' => $transaction->amount,
                'currency' => $transaction->currency,
                'status' => PaymentStatus::from($transaction->status),
                'customer_email' => $transaction->customerEmail,
                'customer_name' => $transaction->customerName,
                'customer_phone' => $transaction->customerPhone,
                'metadata' => $transaction->metadata,
                'gateway_response' => $transaction->gatewayResponse,
                'completed_at' => $transaction->completedAt,
                'failure_reason' => $transaction->failureReason,
                'gateway_transaction_id' => $transaction->gatewayTransactionId,
                'authorization_code' => $transaction->authorizationCode,
            ]
        );
    }

    /**
     * קבלת עסקה ממאגר המידע
     */
    protected function loadTransaction(string $reference): ?PaymentTransaction
    {
        return PaymentTransaction::where('provider', $this->provider->value)
            ->where('reference', $reference)
            ->first();
    }

    /**
     * שמירת סשן תשלום ב-Cache
     */
    protected function storePaymentSession(PaymentSessionData $session): void
    {
        $cacheKey = "payment_session_{$this->provider->value}_{$session->sessionReference}";
        Cache::put($cacheKey, $session, $session->expiresAt);
    }

    /**
     * שליפת סשן תשלום מ-Cache
     */
    protected function getPaymentSession(string $sessionReference): ?PaymentSessionData
    {
        $cacheKey = "payment_session_{$this->provider->value}_{$sessionReference}";
        return Cache::get($cacheKey);
    }

    /**
     * מחיקת סשן תשלום
     */
    protected function destroyPaymentSession(string $sessionReference): void
    {
        $cacheKey = "payment_session_{$this->provider->value}_{$sessionReference}";
        Cache::forget($cacheKey);
    }

    /**
     * Implementation of interface methods
     */
    public function onSuccess(SerializableClosure $callback): self
    {
        $this->onSuccessCallback = $callback;
        return $this;
    }

    public function onFailure(SerializableClosure $callback): self
    {
        $this->onFailureCallback = $callback;
        return $this;
    }

    public function getSupportedCurrencies(): array
    {
        return $this->provider->getSupportedCurrencies();
    }

    public function supportsCurrency(string $currency): bool
    {
        return $this->provider->supportsCurrency($currency);
    }

    public function getProviderInfo(): array
    {
        return [
            'name' => $this->provider->getDisplayName(),
            'hebrew_name' => $this->provider->getHebrewName(),
            'supported_currencies' => $this->getSupportedCurrencies(),
            'supported_countries' => $this->provider->getCountries(),
            'sandbox_mode' => $this->config['sandbox_mode'] ?? true,
        ];
    }

    /**
     * מתודות אבסטרקטיות ליישום בספקים ספציפיים
     */
    abstract public function createPaymentSession(array $parameters): PaymentSessionData;
    abstract public function verifyTransaction(string $reference): TransactionData;
    abstract public function getTransaction(string $transactionId): TransactionData;
    abstract public function refundTransaction(string $transactionId, ?float $amount = null): TransactionData;
    abstract public function cancelTransaction(string $transactionId): TransactionData;
    abstract public function validateWebhookSignature(string $payload, string $signature): bool;
    abstract public function handleWebhook(array $payload): ?TransactionData;
    abstract public function testConnection(): bool;
    abstract public function getRequiredConfigFields(): array;
    abstract public function listTransactions(?string $from = null, ?string $to = null, ?int $limit = 100, ?string $status = null): array;
}