<?php

namespace NMDigitalHub\PaymentGateway\DataObjects;

/**
 * DTO אחיד לבקשות זיכוי/החזר לכל הגייטווייז
 */
class RefundRequest
{
    public function __construct(
        public readonly string $gateway,
        public readonly string $originalTransactionId,
        public readonly float $amount,
        public readonly string $currency,
        public readonly string $reason,
        public readonly ?string $orderId = null,
        public readonly ?string $externalId = null,
        public readonly ?string $refundId = null,
        public readonly bool $isPartialRefund = false,
        public readonly ?array $metadata = null,
    ) {}

    /**
     * יצירה מarray
     */
    public static function fromArray(array $data): self
    {
        return new self(
            gateway: $data['gateway'] ?? 'cardcom',
            originalTransactionId: $data['original_transaction_id'] ?? $data['originalTransactionId'] ?? '',
            amount: (float) ($data['amount'] ?? 0),
            currency: $data['currency'] ?? 'ILS',
            reason: $data['reason'] ?? 'Customer request',
            orderId: $data['order_id'] ?? $data['orderId'] ?? null,
            externalId: $data['external_id'] ?? $data['externalId'] ?? null,
            refundId: $data['refund_id'] ?? $data['refundId'] ?? null,
            isPartialRefund: (bool) ($data['is_partial_refund'] ?? $data['isPartialRefund'] ?? false),
            metadata: $data['metadata'] ?? null,
        );
    }

    /**
     * המרה לarray
     */
    public function toArray(): array
    {
        return [
            'gateway' => $this->gateway,
            'original_transaction_id' => $this->originalTransactionId,
            'amount' => $this->amount,
            'currency' => $this->currency,
            'reason' => $this->reason,
            'order_id' => $this->orderId,
            'external_id' => $this->externalId,
            'refund_id' => $this->refundId,
            'is_partial_refund' => $this->isPartialRefund,
            'metadata' => $this->metadata,
        ];
    }

    /**
     * ולידציה בסיסית של הבקשה
     */
    public function validate(): array
    {
        $errors = [];

        if (empty($this->originalTransactionId)) {
            $errors[] = 'Original transaction ID is required';
        }

        if ($this->amount <= 0) {
            $errors[] = 'Refund amount must be greater than zero';
        }

        if (empty($this->reason)) {
            $errors[] = 'Refund reason is required';
        }

        return $errors;
    }

    /**
     * בדיקה אם הבקשה תקינה
     */
    public function isValid(): bool
    {
        return empty($this->validate());
    }

    /**
     * קבלת נתונים למטרות לוגים
     */
    public function toLogArray(): array
    {
        return [
            'gateway' => $this->gateway,
            'original_transaction_id' => $this->originalTransactionId,
            'amount' => $this->amount,
            'currency' => $this->currency,
            'reason' => $this->reason,
            'order_id' => $this->orderId,
            'is_partial_refund' => $this->isPartialRefund,
        ];
    }
}