<?php

namespace NMDigitalHub\PaymentGateway\Services;

use NMDigitalHub\PaymentGateway\Contracts\PaymentProviderInterface;
use NMDigitalHub\PaymentGateway\DataObjects\PaymentSessionData;
use NMDigitalHub\PaymentGateway\DataObjects\TransactionData;
use NMDigitalHub\PaymentGateway\Enums\PaymentStatus;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Carbon\Carbon;

/**
 * CardCom API v11 Service - Based on production CardComAPI-V11.json spec
 * Terminal: 172204 (Production)
 */
class CardComService implements PaymentProviderInterface
{
    protected string $terminalNumber;
    protected string $apiName;
    protected string $apiPassword;
    protected string $baseUrl;
    protected bool $testMode;

    public function __construct()
    {
        // קריאת הגדרות מהמערכת הראשית דרך Laravel Settings
        $settings = app(\App\Settings\CardComSettings::class);
        
        $this->terminalNumber = $settings->terminal ?? '';
        $this->apiName = $settings->username ?? '';
        $this->apiPassword = $settings->password ?? '';
        $this->baseUrl = ($settings->api_url ?? 'https://secure.cardcom.solutions') . '/api/' . ($settings->api_version ?? 'v11');
        $this->testMode = $settings->test_mode ?? false;
    }

    /**
     * Create LowProfile payment session - CardCom API v11
     */
    public function createPaymentSession(array $params): PaymentSessionData
    {
        $orderRef = 'ORD-' . Str::ulid()->toString();
        
        $operation = $this->determineOperation($params);
        
        $requestData = [
            'TerminalNumber' => $this->terminalNumber,
            'ApiName' => $this->apiName,
            'ApiPassword' => $this->apiPassword,
            'Operation' => $operation,
            'Amount' => (float) $params['amount'],
            'Language' => $params['language'] ?? 'he',
            'ISOCoinId' => $params['currency_code'] ?? 1, // ILS = 1
            'SuccessRedirectUrl' => $params['success_url'],
            'FailedRedirectUrl' => $params['failed_url'],
            'WebHookUrl' => $params['webhook_url'],
            'ProductName' => $params['product_name'] ?? 'Product',
            'ReturnValue' => $orderRef,
            'Document' => [
                'DocumentTypeToCreate' => 'Order',
                'Name' => $params['customer_name'],
                'Email' => $params['customer_email'],
                'Products' => [
                    [
                        'Description' => $params['product_name'] ?? 'Product',
                        'UnitCost' => (float) $params['amount']
                    ]
                ]
            ]
        ];

        // Add token-specific fields if needed
        if ($operation === 'ChargeAndCreateToken') {
            $requestData['CreateTokenForChargeOnly'] = true;
        }

        try {
            $response = Http::timeout(30)
                ->post($this->baseUrl . '/LowProfile/Create', $requestData);

            if (!$response->successful()) {
                throw new \Exception('CardCom API error: ' . $response->body());
            }

            $responseData = $response->json();

            if ($responseData['ResponseCode'] !== 0) {
                throw new \Exception('CardCom error: ' . ($responseData['Description'] ?? 'Unknown error'));
            }

            return new PaymentSessionData([
                'session_id' => $responseData['LowProfileId'],
                'redirect_url' => "https://secure.cardcom.solutions/LowProfile/{$responseData['LowProfileId']}",
                'order_reference' => $orderRef,
                'provider' => 'cardcom',
                'expires_at' => now()->addMinutes(30),
                'metadata' => [
                    'low_profile_id' => $responseData['LowProfileId'],
                    'terminal_number' => $this->terminalNumber,
                    'operation' => $operation
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('CardCom LowProfile creation failed', [
                'error' => $e->getMessage(),
                'params' => array_except($requestData, ['ApiPassword'])
            ]);
            
            throw $e;
        }
    }

    /**
     * Process token payment with Do3DSAndSubmit
     */
    public function processTokenPayment(array $params): TransactionData
    {
        $orderRef = 'ORD-' . Str::ulid()->toString();
        
        $requestData = [
            'TerminalNumber' => $this->terminalNumber,
            'ApiName' => $this->apiName,
            'ApiPassword' => $this->apiPassword,
            'Operation' => 'Do3DSAndSubmit',
            'Amount' => (float) $params['amount'],
            'Language' => 'he',
            'ISOCoinId' => 1,
            'Token' => $params['token'],
            'CardExpirationYear' => $params['card_year'] ?? '2026',
            'CardExpirationMonth' => $params['card_month'] ?? '12',
            'CVV' => $params['cvv'],
            'ThreeDSecureState' => 'Auto',
            'SuccessRedirectUrl' => $params['success_url'],
            'FailedRedirectUrl' => $params['failed_url'],
            'WebHookUrl' => $params['webhook_url'],
            'ReturnValue' => $orderRef
        ];

        try {
            $response = Http::timeout(30)
                ->post($this->baseUrl . '/Do3DSAndSubmit', $requestData);

            $responseData = $response->json();

            return new TransactionData([
                'transaction_id' => $responseData['TransactionId'] ?? null,
                'order_reference' => $orderRef,
                'status' => $this->mapStatus($responseData['ResponseCode'] ?? -1),
                'amount' => $params['amount'],
                'provider' => 'cardcom',
                'requires_3ds' => !empty($responseData['ThreeDSUrl']),
                'three_ds_url' => $responseData['ThreeDSUrl'] ?? null,
                'metadata' => $responseData
            ]);

        } catch (\Exception $e) {
            Log::error('CardCom token payment failed', [
                'error' => $e->getMessage(),
                'order_ref' => $orderRef
            ]);
            
            throw $e;
        }
    }

    /**
     * Check transaction status
     */
    public function checkTransactionStatus(string $transactionId): array
    {
        try {
            $response = Http::timeout(15)
                ->get($this->baseUrl . '/GetTransactionStatus', [
                    'TerminalNumber' => $this->terminalNumber,
                    'ApiName' => $this->apiName,
                    'TransactionId' => $transactionId
                ]);

            $data = $response->json();
            
            return [
                'status' => $this->mapStatus($data['ResponseCode'] ?? -1),
                'amount' => $data['Amount'] ?? 0,
                'currency' => $data['Currency'] ?? 'ILS',
                'raw_data' => $data
            ];

        } catch (\Exception $e) {
            Log::error('CardCom status check failed', [
                'transaction_id' => $transactionId,
                'error' => $e->getMessage()
            ]);
            
            return [
                'status' => PaymentStatus::UNKNOWN,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Process refund
     */
    public function processRefund(string $transactionId, float $amount): array
    {
        try {
            $response = Http::timeout(30)
                ->post($this->baseUrl . '/DoRefund', [
                    'TerminalNumber' => $this->terminalNumber,
                    'ApiName' => $this->apiName,
                    'ApiPassword' => $this->apiPassword,
                    'TransactionId' => $transactionId,
                    'Amount' => $amount
                ]);

            $data = $response->json();
            
            return [
                'success' => $data['ResponseCode'] === 0,
                'refund_id' => $data['RefundId'] ?? null,
                'message' => $data['Description'] ?? 'Refund processed',
                'raw_data' => $data
            ];

        } catch (\Exception $e) {
            Log::error('CardCom refund failed', [
                'transaction_id' => $transactionId,
                'amount' => $amount,
                'error' => $e->getMessage()
            ]);
            
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Determine operation type based on parameters
     */
    protected function determineOperation(array $params): string
    {
        // Token saving requested
        if (isset($params['save_payment_method']) && $params['save_payment_method']) {
            return 'ChargeAndCreateToken';
        }

        // Business logic token creation
        if (isset($params['should_create_token']) && $params['should_create_token']) {
            return 'ChargeAndCreateToken';
        }

        // Default charge only
        return 'ChargeOnly';
    }

    /**
     * Map CardCom response codes to our PaymentStatus enum
     */
    protected function mapStatus(int $responseCode): PaymentStatus
    {
        return match ($responseCode) {
            0 => PaymentStatus::COMPLETED,
            1 => PaymentStatus::PENDING,
            2 => PaymentStatus::FAILED,
            -1 => PaymentStatus::CANCELLED,
            default => PaymentStatus::UNKNOWN
        };
    }

    /**
     * Check if CardCom is properly configured
     */
    public function isConfigured(): bool
    {
        return !empty($this->terminalNumber) 
            && !empty($this->apiName) 
            && !empty($this->baseUrl);
    }

    /**
     * Test connection to CardCom
     */
    public function testConnection(): array
    {
        try {
            // Simple ping test
            $response = Http::timeout(10)
                ->get($this->baseUrl . '/Ping', [
                    'TerminalNumber' => $this->terminalNumber,
                    'ApiName' => $this->apiName
                ]);

            return [
                'success' => $response->successful(),
                'response_time' => $response->transferStats?->getTransferTime() ?? 0,
                'status' => $response->status()
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Get provider information
     */
    public function getProviderInfo(): array
    {
        return [
            'name' => 'CardCom',
            'version' => '11.0',
            'terminal' => $this->terminalNumber,
            'supports_tokens' => true,
            'supports_3ds' => true,
            'supports_refunds' => true,
            'currencies' => ['ILS', 'USD', 'EUR'],
            'test_mode' => $this->testMode
        ];
    }
}