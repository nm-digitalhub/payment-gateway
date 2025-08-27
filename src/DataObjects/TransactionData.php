<?php

namespace NMDigitalHub\PaymentGateway\DataObjects;

use Carbon\Carbon;
use Spatie\LaravelData\Data;

class TransactionData extends Data
{
    public function __construct(
        public string $transactionId,
        public string $provider,
        public string $reference,
        public float $amount,
        public string $currency,
        public string $status,
        public ?string $customerEmail,
        public ?string $customerName,
        public ?string $customerPhone,
        public ?array $metadata,
        public ?string $gatewayResponse,
        public Carbon $createdAt,
        public ?Carbon $completedAt = null,
        public ?string $failureReason = null,
        public ?string $gatewayTransactionId = null,
        public ?string $authorizationCode = null,
    ) {
    }

    public function isSuccessful(): bool
    {
        return match (strtolower($this->status)) {
            'success', 'succeeded', 'successful', 'paid', 'approved', 'completed', 'verified' => true,
            default => false
        };
    }

    public function isPending(): bool
    {
        return match (strtolower($this->status)) {
            'pending', 'processing', 'initiated', 'waiting' => true,
            default => false
        };
    }

    public function isFailed(): bool
    {
        return match (strtolower($this->status)) {
            'failed', 'declined', 'cancelled', 'expired', 'error' => true,
            default => false
        };
    }

    public function getStatusInHebrew(): string
    {
        return match (strtolower($this->status)) {
            'success', 'succeeded', 'successful', 'paid', 'approved', 'completed', 'verified' => 'הצלחה',
            'pending', 'processing', 'initiated', 'waiting' => 'בתהליך',
            'failed', 'declined', 'cancelled', 'expired', 'error' => 'נכשל',
            default => 'לא ידוע'
        };
    }

    public function getFormattedAmount(): string
    {
        return match ($this->currency) {
            'ILS' => '₪' . number_format($this->amount, 2),
            'USD' => '$' . number_format($this->amount, 2),
            'EUR' => '€' . number_format($this->amount, 2),
            default => $this->currency . ' ' . number_format($this->amount, 2)
        };
    }
}