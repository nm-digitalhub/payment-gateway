<?php

namespace NMDigitalHub\PaymentGateway\Listeners;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;
use NMDigitalHub\PaymentGateway\Events\PaymentProcessed;
use NMDigitalHub\PaymentGateway\Events\PaymentFailed;
use NMDigitalHub\PaymentGateway\Events\TokenCreated;

class LogPaymentActivity implements ShouldQueue
{
    use InteractsWithQueue;

    /**
     * Handle payment processed events
     */
    public function handlePaymentProcessed(PaymentProcessed $event): void
    {
        Log::channel('payment-gateway')->info('Payment processed successfully', [
            'transaction_id' => $event->getTransactionId(),
            'provider' => $event->provider,
            'amount' => $event->getAmount(),
            'currency' => $event->getCurrency(),
            'status' => $event->transaction->status,
            'timestamp' => now()->toISOString(),
            'metadata' => $event->metadata,
        ]);
    }

    /**
     * Handle payment failed events
     */
    public function handlePaymentFailed(PaymentFailed $event): void
    {
        Log::channel('payment-gateway')->error('Payment failed', [
            'error_details' => $event->getErrorDetails(),
            'is_retryable' => $event->isRetryable(),
            'timestamp' => now()->toISOString(),
        ]);
    }

    /**
     * Handle token created events
     */
    public function handleTokenCreated(TokenCreated $event): void
    {
        Log::channel('payment-gateway')->info('Payment token created', [
            'token_details' => $event->getTokenDetails(),
            'is_first_token' => $event->isFirstToken(),
            'timestamp' => now()->toISOString(),
        ]);
    }

    /**
     * Register the listeners for the subscriber.
     */
    public function subscribe($events): array
    {
        return [
            PaymentProcessed::class => 'handlePaymentProcessed',
            PaymentFailed::class => 'handlePaymentFailed',
            TokenCreated::class => 'handleTokenCreated',
        ];
    }
}