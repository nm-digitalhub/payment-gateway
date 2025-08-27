<?php

namespace NMDigitalHub\PaymentGateway\DataObjects;

/**
 * DTO אחיד לתגובות תשלום מכל הגייטווייז
 */
class PaymentResponse
{
    public function __construct(
        public readonly string $gateway,
        public readonly bool $success,
        public readonly string $transactionId,
        public readonly ?string $externalId = null,
        public readonly ?string $orderId = null,
        public readonly ?float $amount = null,
        public readonly ?string $currency = null,
        public readonly ?string $status = null,
        public readonly ?string $paymentToken = null,
        public readonly ?string $errorCode = null,
        public readonly ?string $errorMessage = null,
        public readonly ?string $threeDSUrl = null,
        public readonly ?array $rawResponse = null,
        public readonly ?array $metadata = null,
    ) {}

    /**
     * יצירת תגובה מוצלחת
     */
    public static function success(
        string $gateway,
        string $transactionId,
        ?string $externalId = null,
        ?string $orderId = null,
        ?float $amount = null,
        ?string $currency = null,
        ?string $paymentToken = null,
        ?array $rawResponse = null,
        ?array $metadata = null,
    ): self {
        return new self(
            gateway: $gateway,
            success: true,
            transactionId: $transactionId,
            externalId: $externalId,
            orderId: $orderId,
            amount: $amount,
            currency: $currency,
            status: 'completed',
            paymentToken: $paymentToken,
            errorCode: null,
            errorMessage: null,
            threeDSUrl: null,
            rawResponse: $rawResponse,
            metadata: $metadata,
        );
    }

    /**
     * יצירת תגובה כושלת
     */
    public static function failed(
        string $gateway,
        string $transactionId,
        string $errorCode,
        string $errorMessage,
        ?string $externalId = null,
        ?string $orderId = null,
        ?array $rawResponse = null,
        ?array $metadata = null,
    ): self {
        return new self(
            gateway: $gateway,
            success: false,
            transactionId: $transactionId,
            externalId: $externalId,
            orderId: $orderId,
            amount: null,
            currency: null,
            status: 'failed',
            paymentToken: null,
            errorCode: $errorCode,
            errorMessage: $errorMessage,
            threeDSUrl: null,
            rawResponse: $rawResponse,
            metadata: $metadata,
        );
    }

    /**
     * יצירת תגובה עם 3D Secure redirect
     */
    public static function requires3DS(
        string $gateway,
        string $transactionId,
        string $threeDSUrl,
        ?string $externalId = null,
        ?string $orderId = null,
        ?array $rawResponse = null,
        ?array $metadata = null,
    ): self {
        return new self(
            gateway: $gateway,
            success: false, // לא סוכנו עדיין
            transactionId: $transactionId,
            externalId: $externalId,
            orderId: $orderId,
            amount: null,
            currency: null,
            status: 'requires_3ds',
            paymentToken: null,
            errorCode: null,
            errorMessage: null,
            threeDSUrl: $threeDSUrl,
            rawResponse: $rawResponse,
            metadata: $metadata,
        );
    }

    /**
     * יצירה מarray
     */
    public static function fromArray(array $data): self
    {
        return new self(
            gateway: $data['gateway'] ?? '',
            success: (bool) ($data['success'] ?? false),
            transactionId: $data['transaction_id'] ?? $data['transactionId'] ?? '',
            externalId: $data['external_id'] ?? $data['externalId'] ?? null,
            orderId: $data['order_id'] ?? $data['orderId'] ?? null,
            amount: isset($data['amount']) ? (float) $data['amount'] : null,
            currency: $data['currency'] ?? null,
            status: $data['status'] ?? null,
            paymentToken: $data['payment_token'] ?? $data['paymentToken'] ?? null,
            errorCode: $data['error_code'] ?? $data['errorCode'] ?? null,
            errorMessage: $data['error_message'] ?? $data['errorMessage'] ?? null,
            threeDSUrl: $data['three_ds_url'] ?? $data['threeDSUrl'] ?? null,
            rawResponse: $data['raw_response'] ?? $data['rawResponse'] ?? null,
            metadata: $data['metadata'] ?? null,
        );
    }

    /**
     * יצירה מתגובת CardCom
     */
    public static function fromCardCom(array $response): self
    {
        $responseCode = (int) ($response['ResponseCode'] ?? -1);
        $success = $responseCode === 0;
        
        // Extract transaction details
        $transactionId = $response['InternalDealNumber'] ?? $response['TransactionId'] ?? '';
        $externalId = $response['DealId'] ?? null;
        $amount = isset($response['Amount']) ? (float) $response['Amount'] : null;
        $paymentToken = $response['Token'] ?? null;
        
        // Handle 3D Secure
        $threeDSUrl = $response['url'] ?? null;
        $requires3DS = !empty($threeDSUrl);

        if ($success) {
            return self::success(
                gateway: 'cardcom',
                transactionId: $transactionId,
                externalId: $externalId,
                amount: $amount,
                currency: 'ILS',
                paymentToken: $paymentToken,
                rawResponse: $response,
            );
        } elseif ($requires3DS) {
            return self::requires3DS(
                gateway: 'cardcom',
                transactionId: $transactionId,
                threeDSUrl: $threeDSUrl,
                externalId: $externalId,
                rawResponse: $response,
            );
        } else {
            return self::failed(
                gateway: 'cardcom',
                transactionId: $transactionId,
                errorCode: (string) $responseCode,
                errorMessage: $response['Description'] ?? 'Unknown error',
                externalId: $externalId,
                rawResponse: $response,
            );
        }
    }

    /**
     * יצירה מתגובת Maya Mobile
     */
    public static function fromMayaMobile(array $response): self
    {
        $success = ($response['status'] ?? '') === 'success';
        $transactionId = $response['transaction_id'] ?? '';
        $externalId = $response['reference_id'] ?? null;

        if ($success) {
            return self::success(
                gateway: 'maya_mobile',
                transactionId: $transactionId,
                externalId: $externalId,
                rawResponse: $response,
            );
        } else {
            return self::failed(
                gateway: 'maya_mobile',
                transactionId: $transactionId,
                errorCode: $response['error_code'] ?? 'unknown',
                errorMessage: $response['error_message'] ?? 'Unknown error',
                externalId: $externalId,
                rawResponse: $response,
            );
        }
    }

    /**
     * יצירה מתגובת ResellerClub
     */
    public static function fromResellerClub(array $response): self
    {
        $success = ($response['status'] ?? '') === 'Success';
        $transactionId = $response['invoiceid'] ?? '';
        $externalId = $response['entityid'] ?? null;

        if ($success) {
            return self::success(
                gateway: 'resellerclub',
                transactionId: $transactionId,
                externalId: $externalId,
                rawResponse: $response,
            );
        } else {
            return self::failed(
                gateway: 'resellerclub',
                transactionId: $transactionId,
                errorCode: $response['error'] ?? 'unknown',
                errorMessage: $response['message'] ?? 'Unknown error',
                externalId: $externalId,
                rawResponse: $response,
            );
        }
    }

    /**
     * המרה לarray
     */
    public function toArray(): array
    {
        return [
            'gateway' => $this->gateway,
            'success' => $this->success,
            'transaction_id' => $this->transactionId,
            'external_id' => $this->externalId,
            'order_id' => $this->orderId,
            'amount' => $this->amount,
            'currency' => $this->currency,
            'status' => $this->status,
            'payment_token' => $this->paymentToken,
            'error_code' => $this->errorCode,
            'error_message' => $this->errorMessage,
            'three_ds_url' => $this->threeDSUrl,
            'raw_response' => $this->rawResponse,
            'metadata' => $this->metadata,
        ];
    }

    /**
     * בדיקה אם נדרש 3D Secure
     */
    public function requires3DS(): bool
    {
        return !empty($this->threeDSUrl) || $this->status === 'requires_3ds';
    }

    /**
     * בדיקה אם נוצר payment token
     */
    public function hasPaymentToken(): bool
    {
        return !empty($this->paymentToken);
    }

    /**
     * קבלת נתונים למטרות לוגים (ללא נתונים רגישים)
     */
    public function toLogArray(): array
    {
        return [
            'gateway' => $this->gateway,
            'success' => $this->success,
            'transaction_id' => $this->transactionId,
            'external_id' => $this->externalId,
            'order_id' => $this->orderId,
            'amount' => $this->amount,
            'currency' => $this->currency,
            'status' => $this->status,
            'has_payment_token' => $this->hasPaymentToken(),
            'requires_3ds' => $this->requires3DS(),
            'error_code' => $this->errorCode,
            'error_message' => $this->errorMessage,
        ];
    }

    /**
     * קבלת הודעת סטטוס ידידותית למשתמש
     */
    public function getUserMessage(): string
    {
        if ($this->success) {
            return 'התשלום בוצע בהצלחה';
        }

        if ($this->requires3DS()) {
            return 'נדרש אימות נוסף - מפנה לאימות 3D Secure';
        }

        return match ($this->errorCode) {
            '33' => 'הכרטיס נדחה - אנא נסה כרטיס אחר',
            '51' => 'אין כיסוי בכרטיס',
            '54' => 'הכרטיס פג תוקף',
            default => $this->errorMessage ?? 'אירעה שגיאה בתשלום'
        };
    }
}