<?php

namespace NMDigitalHub\PaymentGateway\Services;

use NMDigitalHub\PaymentGateway\DataObjects\PaymentSessionData;
use NMDigitalHub\PaymentGateway\DataObjects\TransactionData;
use App\Services\CardCom\CardComOpenFieldsService;
use App\Models\PaymentToken;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Carbon\Carbon;

class CardComLowProfileService
{
    protected string $terminalNumber;
    protected string $apiName;
    protected string $apiPassword;
    protected string $baseUrl;
    protected bool $sandboxMode;

    public function __construct()
    {
        // שימוש בנתונים מהמערכת הקיימת
        $cardcomService = app(CardComOpenFieldsService::class);
        $this->terminalNumber = '172204'; // מספר טרמינל פרודקשן
        $this->apiName = 'wr3UAE33TuvTEULxUYkt';
        $this->apiPassword = 'c7QOyJ5vyiDwz5mbMjUt';
        $this->baseUrl = 'https://secure.cardcom.solutions/api/v11';
        $this->sandboxMode = false;
    }

    /**
     * יצירת LowProfile לתשלום חדש - מתוך המערכת המאוחדת
     */
    public function createLowProfilePayment(array $params): PaymentSessionData
    {
        $orderRef = 'ORD-' . Str::ulid()->toString();
        
        // בדיקה אם צריך לשמור טוקן
        $operation = $this->determineOperation($params);
        
        $requestData = [
            'TerminalNumber' => $this->terminalNumber,
            'ApiName' => $this->apiName,
            'ApiPassword' => $this->apiPassword,
            'Operation' => $operation,
            'Amount' => (float) $params['amount'],
            'Language' => 'he',
            'ISOCoinId' => 1, // ILS
            'SuccessRedirectUrl' => $params['success_url'] ?? 'https://nm-digitalhub.com/esim/payment/success',
            'FailedRedirectUrl' => $params['failed_url'] ?? 'https://nm-digitalhub.com/esim/payment/failed',
            'WebHookUrl' => $params['webhook_url'] ?? 'https://nm-digitalhub.com/cardcom/webhook',
            'ProductName' => $params['description'] ?? $params['product_name'] ?? 'תשלום',
            'ReturnValue' => $orderRef,
            'Document' => [
                'DocumentTypeToCreate' => 'Order',
                'Name' => $params['customer_name'],
                'Email' => $params['customer_email'],
                'Products' => [[
                    'Description' => $params['description'] ?? $params['product_name'] ?? 'תשלום',
                    'UnitCost' => (float) $params['amount']
                ]]
            ]
        ];

        Log::info('Creating CardCom LowProfile', [
            'order_ref' => $orderRef,
            'operation' => $operation,
            'amount' => $params['amount']
        ]);

        $response = Http::timeout(30)
            ->post($this->baseUrl . '/LowProfile/create', $requestData);

        if (!$response->successful()) {
            throw new \Exception('שגיאה ביצירת LowProfile: ' . $response->body());
        }

        $data = $response->json();
        
        if (($data['ResponseCode'] ?? -1) !== 0) {
            throw new \Exception($data['Description'] ?? 'שגיאה לא ידועה מ-CardCom');
        }

        $sessionData = new PaymentSessionData(
            sessionReference: $orderRef,
            checkoutUrl: $this->baseUrl . '/LowProfile/page/' . $data['LowProfileId'],
            lowProfileId: $data['LowProfileId'],
            provider: 'cardcom',
            amount: (float) $params['amount'],
            currency: 'ILS',
            expiresAt: now()->addHour()
        );

        // שמירה ב-cache למעקב
        Cache::put("cardcom_lowprofile_{$orderRef}", $sessionData, 3600);

        return $sessionData;
    }

    /**
     * תשלום עם טוקן שמור - Do3DSAndSubmit
     */
    public function processTokenPayment(array $params): TransactionData
    {
        $token = PaymentToken::where('id', $params['saved_token_id'])
            ->where('user_id', auth()->id())
            ->where('is_active', true)
            ->where('expires_at', '>', now())
            ->first();

        if (!$token) {
            throw new \Exception('טוקן לא נמצא או לא תקין');
        }

        $orderRef = 'ORD-' . Str::ulid()->toString();
        
        $requestData = [
            'TerminalNumber' => $this->terminalNumber,
            'ApiName' => $this->apiName,
            'ApiPassword' => $this->apiPassword,
            'Operation' => 'Do3DSAndSubmit', // פעולה בטוקן עם 3D Secure
            'Amount' => (float) $params['amount'],
            'Language' => 'he',
            'ISOCoinId' => 1, // ILS
            'Token' => $token->cardcom_token,
            'CardExpirationYear' => $token->card_year ?? '2026',
            'CardExpirationMonth' => $token->card_month ?? '12',
            'CVV' => $params['cvv'], // נדרש ל-3DS
            'ThreeDSecureState' => 'Auto', // CardCom מחליט אם צריך 3DS
            'SuccessRedirectUrl' => $params['success_url'] ?? 'https://nm-digitalhub.com/esim/payment/success',
            'FailedRedirectUrl' => $params['failed_url'] ?? 'https://nm-digitalhub.com/esim/payment/failed', 
            'WebHookUrl' => $params['webhook_url'] ?? 'https://nm-digitalhub.com/cardcom/webhook',
            'ProductName' => $params['description'] ?? $params['product_name'] ?? 'תשלום עם טוקן',
            'ReturnValue' => $orderRef,
            'Document' => [
                'DocumentTypeToCreate' => 'Order',
                'Name' => $params['customer_name'],
                'Email' => $params['customer_email'],
                'Products' => [[
                    'Description' => $params['description'] ?? $params['product_name'] ?? 'תשלום עם טוקן',
                    'UnitCost' => (float) $params['amount']
                ]]
            ]
        ];

        Log::info('Processing CardCom token payment with 3DS', [
            'order_ref' => $orderRef,
            'token_id' => $token->id,
            'amount' => $params['amount']
        ]);

        $response = Http::timeout(30)
            ->post($this->baseUrl . '/ChargeByToken', $requestData);

        if (!$response->successful()) {
            throw new \Exception('שגיאה בתשלום עם טוקן: ' . $response->body());
        }

        $data = $response->json();
        
        // בדיקה אם נדרש 3D Secure
        if (isset($data['ThreeDSUrl']) && !empty($data['ThreeDSUrl'])) {
            return new TransactionData(
                transactionId: $data['DealId'] ?? $orderRef,
                reference: $orderRef,
                status: '3ds_required',
                amount: (float) $params['amount'],
                currency: 'ILS',
                provider: 'cardcom',
                customerEmail: $params['customer_email'],
                customerName: $params['customer_name'],
                gatewayResponse: $data,
                metadata: [
                    'three_ds_url' => $data['ThreeDSUrl'],
                    'requires_3ds' => true,
                    'token_used' => true
                ]
            );
        }

        // תשלום ישיר ללא 3DS
        $success = ($data['ResponseCode'] ?? -1) === 0;
        
        return new TransactionData(
            transactionId: $data['DealId'] ?? $orderRef,
            reference: $orderRef,
            status: $success ? 'success' : 'failed',
            amount: (float) $params['amount'],
            currency: 'ILS',
            provider: 'cardcom',
            customerEmail: $params['customer_email'],
            customerName: $params['customer_name'],
            gatewayTransactionId: $data['DealId'] ?? null,
            authorizationCode: $data['AuthNumber'] ?? null,
            failureReason: $success ? null : ($data['Description'] ?? 'תשלום נכשל'),
            completedAt: $success ? now() : null,
            gatewayResponse: $data,
            metadata: [
                'token_used' => true,
                'requires_3ds' => false
            ]
        );
    }

    /**
     * עיבוד webhook מ-CardCom עם תמיכה בטוקנים
     */
    public function handleWebhook(array $payload): ?TransactionData
    {
        Log::info('Processing CardCom webhook', $payload);

        $dealId = $payload['DealId'] ?? null;
        $orderRef = $payload['ReturnValue'] ?? null;
        
        if (!$dealId || !$orderRef) {
            Log::warning('Missing required webhook data', $payload);
            return null;
        }

        // בדיקת סטטוס תשלום
        $success = ($payload['ResponseCode'] ?? -1) === 0;
        
        $transactionData = new TransactionData(
            transactionId: $dealId,
            reference: $orderRef,
            status: $success ? 'success' : 'failed',
            amount: (float) ($payload['Amount'] ?? 0),
            currency: 'ILS',
            provider: 'cardcom',
            customerEmail: $payload['Email'] ?? null,
            customerName: $payload['Name'] ?? null,
            gatewayTransactionId: $dealId,
            authorizationCode: $payload['AuthNumber'] ?? null,
            failureReason: $success ? null : ($payload['Description'] ?? 'תשלום נכשל'),
            completedAt: $success ? now() : null,
            gatewayResponse: $payload,
            metadata: [
                'webhook_received' => true,
                'token_created' => isset($payload['Token']),
                'three_ds_completed' => isset($payload['ThreeDSCompleted'])
            ]
        );

        // אם נוצר טוקן חדש
        if (isset($payload['Token']) && $success) {
            $this->savePaymentToken($payload, $transactionData);
        }

        return $transactionData;
    }

    /**
     * שמירת טוקן תשלום חדש
     */
    protected function savePaymentToken(array $webhookData, TransactionData $transactionData): void
    {
        try {
            $userId = auth()->id() ?? $this->findUserByEmail($transactionData->customerEmail);
            
            if (!$userId) {
                Log::warning('Cannot save token - no user found', [
                    'email' => $transactionData->customerEmail
                ]);
                return;
            }

            PaymentToken::create([
                'user_id' => $userId,
                'gateway' => 'cardcom',
                'cardcom_token' => $webhookData['Token'],
                'card_last_four' => $webhookData['LastFourDigits'] ?? null,
                'card_year' => $webhookData['CardExpirationYear'] ?? null,
                'card_month' => $webhookData['CardExpirationMonth'] ?? null,
                'card_brand' => $this->detectCardBrand($webhookData['CardNumber'] ?? ''),
                'expires_at' => $this->calculateTokenExpiry($webhookData),
                'is_active' => true,
                'is_default' => false,
                'metadata' => [
                    'created_from_deal' => $transactionData->transactionId,
                    'webhook_data' => $webhookData
                ]
            ]);

            Log::info('Payment token saved successfully', [
                'user_id' => $userId,
                'token_last_four' => $webhookData['LastFourDigits'] ?? null
            ]);
            
        } catch (\Exception $e) {
            Log::error('Failed to save payment token', [
                'error' => $e->getMessage(),
                'webhook_data' => $webhookData
            ]);
        }
    }

    /**
     * קביעת סוג פעולה (ChargeOnly / ChargeAndCreateToken / Do3DSAndSubmit)
     * לפי התיעוד הרשמי של CardCom API v11
     */
    protected function determineOperation(array $params): string
    {
        // שימוש בטוכן קיים = Do3DSAndSubmit (חובה!)
        if (isset($params['saved_token_id']) && $params['saved_token_id']) {
            return 'Do3DSAndSubmit';
        }
        
        // כרטיס חדש + שמירת טוכן = ChargeAndCreateToken
        if (isset($params['save_payment_method']) && $params['save_payment_method']) {
            return 'ChargeAndCreateToken';
        }
        
        if (isset($params['should_create_token']) && $params['should_create_token']) {
            return 'ChargeAndCreateToken';
        }
        
        // כרטיס חדש ללא שמירה = ChargeOnly
        return 'ChargeOnly';
    }

    /**
     * חישוב תאריך תפוגת טוקן
     */
    protected function calculateTokenExpiry(array $webhookData): Carbon
    {
        $year = (int) ($webhookData['CardExpirationYear'] ?? date('Y') + 2);
        $month = (int) ($webhookData['CardExpirationMonth'] ?? 12);
        
        return Carbon::create($year, $month, 1)->endOfMonth();
    }

    /**
     * זיהוי סוג כרטיס
     */
    protected function detectCardBrand(string $cardNumber): string
    {
        $cardNumber = preg_replace('/\D/', '', $cardNumber);
        
        if (preg_match('/^4/', $cardNumber)) return 'Visa';
        if (preg_match('/^5[1-5]/', $cardNumber)) return 'MasterCard';
        if (preg_match('/^3[47]/', $cardNumber)) return 'American Express';
        if (preg_match('/^6(?:011|5)/', $cardNumber)) return 'Discover';
        
        return 'Unknown';
    }

    /**
     * מציאת משתמש לפי כתובת מייל
     */
    protected function findUserByEmail(string $email): ?int
    {
        $userModel = class_exists(\App\Models\User::class) ? \App\Models\User::class : null;
        
        if (!$userModel) return null;
        
        $user = $userModel::where('email', $email)->first();
        return $user?->id;
    }

    /**
     * בדיקת חיבור ל-CardCom API
     */
    public function testConnection(): bool
    {
        try {
            $response = Http::timeout(10)
                ->post($this->baseUrl . '/ChargeByToken', [
                    'TerminalNumber' => $this->terminalNumber,
                    'ApiName' => $this->apiName,
                    'ApiPassword' => $this->apiPassword
                ]);
            
            return $response->successful();
        } catch (\Exception $e) {
            Log::warning('CardCom connection test failed', ['error' => $e->getMessage()]);
            return false;
        }
    }
}
