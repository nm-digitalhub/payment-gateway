<?php

namespace NMDigitalHub\PaymentGateway\DataObjects;

use Carbon\Carbon;
use Spatie\LaravelData\Data;
use Laravel\SerializableClosure\SerializableClosure;

class PaymentSessionData extends Data
{
    public function __construct(
        public string $provider,
        public string $sessionReference,
        public string $paymentReference,
        public ?string $checkoutSecret,
        public ?string $checkoutUrl,
        public ?string $checkoutToken,
        public float $amount,
        public string $currency,
        public string $customerEmail,
        public ?array $metadata,
        public Carbon $expiresAt,
        public ?SerializableClosure $onSuccess = null,
        public ?SerializableClosure $onFailed = null,
    ) {
    }

    public function isExpired(): bool
    {
        return $this->expiresAt->isPast();
    }

    public function getRemainingMinutes(): int
    {
        return max(0, $this->expiresAt->diffInMinutes(now()));
    }

    public function executeSuccessCallback(TransactionData $transaction): mixed
    {
        if (!$this->onSuccess) {
            return null;
        }

        $closure = $this->onSuccess->getClosure();
        return $closure($transaction);
    }

    public function executeFailedCallback(?TransactionData $transaction = null): mixed
    {
        if (!$this->onFailed) {
            return null;
        }

        $closure = $this->onFailed->getClosure();
        return $closure($transaction);
    }
}