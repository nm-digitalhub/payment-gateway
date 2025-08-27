<?php

namespace NMDigitalHub\PaymentGateway\Providers;

use NMDigitalHub\PaymentGateway\DataObjects\PaymentSessionData;
use NMDigitalHub\PaymentGateway\DataObjects\TransactionData;
use NMDigitalHub\PaymentGateway\Enums\PaymentProvider;
use NMDigitalHub\PaymentGateway\Enums\PaymentStatus;
use App\Models\ServiceProvider;
use App\Models\ApiEndpoint;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Cache;
use Carbon\Carbon;

class CardComProvider extends AbstractPaymentProvider
{
    protected PaymentProvider $provider = PaymentProvider::CARDCOM;
    protected ?ServiceProvider $serviceProvider = null;
    
    public function __construct()
    {
        parent::__construct();
        $this->loadServiceProvider();
    }

    protected function loadServiceProvider(): void
    {
        $this->serviceProvider = ServiceProvider::where('slug', 'cardcom')
            ->where('is_active', true)
            ->first();
            
        if (!$this->serviceProvider) {
            throw new \Exception('CardCom service provider not configured');
        }
    }

    protected function getDefaultConfig(): array
    {
        return array_merge(parent::getDefaultConfig(), [
            'terminal_number' => '172204',
            'force_3ds' => true,
            'j5_protocol' => true,
            'create_invoice' => true,
            'language' => 'he',
            'currency' => 'ILS',
        ]);
    }

    public function createPaymentSession(array $parameters): PaymentSessionData
    {
        $reference = 'CRD_' . Str::ulid();
        
        // חיפוש endpoint ליצירת LowProfile
        $endpoint = $this->serviceProvider->endpoints()
            ->where('name', 'create_lowprofile')
            ->where('is_active', true)
            ->first();
            
        if (!$endpoint) {
            throw new \Exception('CardCom LowProfile endpoint not configured');
        }

        $apiParams = $this->prepareCreateSessionParams($parameters, $reference);
        
        try {
            $response = $endpoint->makeRequest($apiParams);
            
            if (!$response['success']) {
                throw new \Exception($response['error'] ?? 'Failed to create CardCom session');
            }

            $sessionData = new PaymentSessionData(
                provider: $this->provider->value,
                sessionReference: $reference,
                paymentReference: $response['data']['LowProfileId'] ?? '',
                checkoutSecret: null,
                checkoutUrl: $response['data']['url'] ?? null,
                checkoutToken: $response['data']['LowProfileId'] ?? null,
                amount: (float) $parameters['amount'],
                currency: $parameters['currency'] ?? 'ILS',
                customerEmail: $parameters['email'],
                metadata: $parameters['metadata'] ?? [],
                expiresAt: Carbon::now()->addMinutes(30),
                onSuccess: $this->onSuccessCallback,
                onFailed: $this->onFailureCallback
            );

            $this->storePaymentSession($sessionData);
            return $sessionData;
            
        } catch (\Exception $e) {
            throw new \Exception('CardCom session creation failed: ' . $e->getMessage());
        }
    }

    private function prepareCreateSessionParams(array $parameters, string $reference): array
    {
        return [
            'TerminalNumber' => $this->config['terminal_number'],
            'Operation' => $parameters['save_token'] ?? false ? 'ChargeAndCreateToken' : 'ChargeOnly',
            'Amount' => $parameters['amount'],
            'Language' => $this->config['language'],
            'ISOCoinId' => 1, // ILS
            'SuccessRedirectUrl' => $parameters['success_url'] ?? route('payment.success'),
            'FailedRedirectUrl' => $parameters['failed_url'] ?? route('payment.failed'),
            'WebHookUrl' => $parameters['webhook_url'] ?? route('cardcom.webhook'),
            'ProductName' => $parameters['product_name'] ?? 'Product',
            'ReturnValue' => $reference,
            'Document' => [
                'DocumentTypeToCreate' => 'Order',
                'Name' => $parameters['customer_name'],
                'Email' => $parameters['email'],
                'Phone' => $parameters['phone'] ?? '',
                'Products' => [
                    [
                        'Description' => $parameters['product_name'] ?? 'Product',
                        'UnitCost' => (float) $parameters['amount']
                    ]
                ]
            ]
        ];
    }

    public function verifyTransaction(string $reference): TransactionData
    {
        // חיפוש endpoint לאימות עסקה
        $endpoint = $this->serviceProvider->endpoints()
            ->where('name', 'verify_transaction')
            ->where('is_active', true)
            ->first();
            
        if (!$endpoint) {
            throw new \Exception('CardCom verify endpoint not configured');
        }

        try {
            $response = $endpoint->makeRequest(['reference' => $reference]);
            
            if (!$response['success']) {
                throw new \Exception($response['error'] ?? 'Transaction verification failed');
            }

            return $this->mapToTransactionData($response['data'], $reference);
            
        } catch (\Exception $e) {
            throw new \Exception('CardCom transaction verification failed: ' . $e->getMessage());
        }
    }

    public function getTransaction(string $transactionId): TransactionData
    {
        // שליפת עסקה לפי ID
        $endpoint = $this->serviceProvider->endpoints()
            ->where('name', 'get_transaction')
            ->where('is_active', true)
            ->first();
            
        if (!$endpoint) {
            throw new \Exception('CardCom get transaction endpoint not configured');
        }

        try {
            $response = $endpoint->makeRequest(['transaction_id' => $transactionId]);
            
            if (!$response['success']) {
                throw new \Exception($response['error'] ?? 'Failed to retrieve transaction');
            }

            return $this->mapToTransactionData($response['data']);
            
        } catch (\Exception $e) {
            throw new \Exception('CardCom get transaction failed: ' . $e->getMessage());
        }
    }

    public function refundTransaction(string $transactionId, ?float $amount = null): TransactionData
    {
        $endpoint = $this->serviceProvider->endpoints()
            ->where('name', 'refund_transaction')
            ->where('is_active', true)
            ->first();
            
        if (!$endpoint) {
            throw new \Exception('CardCom refund endpoint not configured');
        }

        try {
            $params = ['transaction_id' => $transactionId];
            if ($amount) {
                $params['amount'] = $amount;
            }
            
            $response = $endpoint->makeRequest($params);
            
            if (!$response['success']) {
                throw new \Exception($response['error'] ?? 'Refund failed');
            }

            return $this->mapToTransactionData($response['data']);
            
        } catch (\Exception $e) {
            throw new \Exception('CardCom refund failed: ' . $e->getMessage());
        }
    }

    public function cancelTransaction(string $transactionId): TransactionData
    {
        // CardCom לא תומך בביטול עסקה, רק בזיכוי
        return $this->refundTransaction($transactionId);
    }

    public function listTransactions(?string $from = null, ?string $to = null, ?int $limit = 100, ?string $status = null): array
    {
        $endpoint = $this->serviceProvider->endpoints()
            ->where('name', 'list_transactions')
            ->where('is_active', true)
            ->first();
            
        if (!$endpoint) {
            throw new \Exception('CardCom list transactions endpoint not configured');
        }

        try {
            $params = array_filter([
                'from_date' => $from,
                'to_date' => $to,
                'limit' => $limit,
                'status' => $status,
            ]);
            
            $response = $endpoint->makeRequest($params);
            
            if (!$response['success']) {
                throw new \Exception($response['error'] ?? 'Failed to list transactions');
            }

            return [
                'transactions' => array_map(
                    fn($item) => $this->mapToTransactionData($item),
                    $response['data']['transactions'] ?? []
                ),
                'total' => $response['data']['total'] ?? 0,
                'page' => $response['data']['page'] ?? 1
            ];
            
        } catch (\Exception $e) {
            throw new \Exception('CardCom list transactions failed: ' . $e->getMessage());
        }
    }

    public function validateWebhookSignature(string $payload, string $signature): bool
    {
        $webhookSecret = $this->serviceProvider->webhook_secret;
        
        if (!$webhookSecret) {
            return false;
        }

        $expectedSignature = hash_hmac('sha256', $payload, $webhookSecret);
        return hash_equals($expectedSignature, $signature);
    }

    public function handleWebhook(array $payload): ?TransactionData
    {
        try {
            // CardCom webhook מכיל פרטים על העסקה
            if (!isset($payload['ReturnValue'])) {
                throw new \Exception('Invalid CardCom webhook payload');
            }

            return $this->mapToTransactionData($payload);
            
        } catch (\Exception $e) {
            throw new \Exception('CardCom webhook handling failed: ' . $e->getMessage());
        }
    }

    public function testConnection(): bool
    {
        try {
            return $this->serviceProvider->testApiConnection()['success'] ?? false;
        } catch (\Exception $e) {
            return false;
        }
    }

    public function getRequiredConfigFields(): array
    {
        return [
            'terminal_number' => [
                'label' => 'מספר טרמינל',
                'type' => 'text',
                'required' => true,
                'description' => 'מספר הטרמינל מ-CardCom'
            ],
            'api_name' => [
                'label' => 'שם משתמש API',
                'type' => 'text',
                'required' => true,
                'description' => 'שם משתמש ל-API של CardCom'
            ],
            'api_password' => [
                'label' => 'סיסמת API',
                'type' => 'password',
                'required' => true,
                'description' => 'סיסמת API של CardCom'
            ],
            'webhook_secret' => [
                'label' => 'סוד Webhook',
                'type' => 'password',
                'required' => false,
                'description' => 'סוד לאימות webhooks'
            ]
        ];
    }

    private function mapToTransactionData(array $data, string $reference = null): TransactionData
    {
        return new TransactionData(
            transactionId: $data['DealId'] ?? $data['TransactionId'] ?? '',
            provider: $this->provider->value,
            reference: $reference ?? $data['ReturnValue'] ?? '',
            amount: (float) ($data['Amount'] ?? 0),
            currency: 'ILS',
            status: $this->mapCardComStatus($data['ResponseCode'] ?? ''),
            customerEmail: $data['Email'] ?? null,
            customerName: $data['Name'] ?? null,
            customerPhone: $data['Phone'] ?? null,
            metadata: $data,
            gatewayResponse: json_encode($data),
            createdAt: Carbon::now(),
            completedAt: isset($data['CompletedAt']) ? Carbon::parse($data['CompletedAt']) : null,
            failureReason: ($data['ResponseCode'] ?? '0') !== '0' ? $data['Description'] ?? null : null,
            gatewayTransactionId: $data['DealId'] ?? null,
            authorizationCode: $data['AuthCode'] ?? null
        );
    }

    private function mapCardComStatus(string $responseCode): string
    {
        return match ($responseCode) {
            '0' => 'success',
            '1', '2', '3' => 'failed',
            '4' => 'pending',
            default => 'failed'
        };
    }
}