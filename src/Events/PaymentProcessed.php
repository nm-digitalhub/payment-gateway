<?php

namespace NMDigitalHub\PaymentGateway\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use NMDigitalHub\PaymentGateway\DataObjects\TransactionData;

class PaymentProcessed
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly TransactionData $transaction,
        public readonly string $provider,
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
     * Determine if the payment was successful
     */
    public function isSuccessful(): bool
    {
        return $this->transaction->status === 'completed';
    }

    /**
     * Get transaction ID for reference
     */
    public function getTransactionId(): ?string
    {
        return $this->transaction->transactionId;
    }

    /**
     * Get payment amount
     */
    public function getAmount(): float
    {
        return $this->transaction->amount;
    }

    /**
     * Get currency code
     */
    public function getCurrency(): string
    {
        return $this->transaction->currency;
    }
}