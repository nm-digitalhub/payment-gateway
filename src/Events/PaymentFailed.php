<?php

namespace NMDigitalHub\PaymentGateway\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use NMDigitalHub\PaymentGateway\DataObjects\TransactionData;

class PaymentFailed
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly TransactionData $transaction,
        public readonly string $provider,
        public readonly string $errorMessage,
        public readonly string $errorCode = '',
        public readonly array $metadata = []
    ) {}

    /**
     * Get the channels the event should broadcast on.
     */
    public function broadcastOn(): array
    {
        return [];
    }

    /**
     * Get error details
     */
    public function getErrorDetails(): array
    {
        return [
            'message' => $this->errorMessage,
            'code' => $this->errorCode,
            'provider' => $this->provider,
            'transaction_id' => $this->transaction->transactionId,
            'amount' => $this->transaction->amount,
            'currency' => $this->transaction->currency,
            'metadata' => $this->metadata,
        ];
    }

    /**
     * Check if error is retryable
     */
    public function isRetryable(): bool
    {
        $retryableCodes = ['timeout', 'network_error', 'temporary_decline'];
        return in_array($this->errorCode, $retryableCodes);
    }
}