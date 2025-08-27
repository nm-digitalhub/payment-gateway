<?php

namespace NMDigitalHub\PaymentGateway\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use NMDigitalHub\PaymentGateway\PaymentGatewayManager;
use NMDigitalHub\PaymentGateway\DataObjects\PaymentRequest;
use NMDigitalHub\PaymentGateway\Events\PaymentProcessed;
use NMDigitalHub\PaymentGateway\Events\PaymentFailed;

class ProcessRetryPayment implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 120; // 2 minutes

    public function __construct(
        public readonly PaymentRequest $paymentRequest,
        public readonly string $originalTransactionId,
        public readonly string $retryReason,
        public readonly int $retryAttempt = 1
    ) {
        $this->onQueue('payment-retries');
        
        // Delay retry based on attempt number (exponential backoff)
        $this->delay(now()->addMinutes($this->calculateDelay()));
    }

    /**
     * Execute the job.
     */
    public function handle(PaymentGatewayManager $paymentManager): void
    {
        try {
            Log::info('Processing payment retry', [
                'original_transaction_id' => $this->originalTransactionId,
                'retry_attempt' => $this->retryAttempt,
                'retry_reason' => $this->retryReason,
                'provider' => $this->paymentRequest->provider,
                'amount' => $this->paymentRequest->amount,
            ]);

            // Get the provider instance
            $provider = $paymentManager->getProvider($this->paymentRequest->provider);
            
            if (!$provider) {
                throw new \Exception("Provider {$this->paymentRequest->provider} not found");
            }

            // Check if we should still retry (business rules)
            if (!$this->shouldRetryPayment()) {
                Log::info('Payment retry cancelled - business rules', [
                    'original_transaction_id' => $this->originalTransactionId,
                    'retry_attempt' => $this->retryAttempt,
                ]);
                return;
            }

            // Add retry metadata
            $paymentRequest = $this->paymentRequest;
            $paymentRequest->metadata = array_merge($paymentRequest->metadata ?? [], [
                'is_retry' => true,
                'original_transaction_id' => $this->originalTransactionId,
                'retry_attempt' => $this->retryAttempt,
                'retry_reason' => $this->retryReason,
                'retry_timestamp' => now()->toISOString(),
            ]);

            // Process the payment retry
            $result = $provider->processPayment($paymentRequest);

            if ($result->isSuccessful()) {
                Log::info('Payment retry successful', [
                    'original_transaction_id' => $this->originalTransactionId,
                    'new_transaction_id' => $result->getTransactionData()->transactionId,
                    'retry_attempt' => $this->retryAttempt,
                ]);

                // Fire success event
                PaymentProcessed::dispatch(
                    $result->getTransactionData(),
                    $this->paymentRequest->provider,
                    [
                        'is_retry' => true,
                        'original_transaction_id' => $this->originalTransactionId,
                        'retry_attempt' => $this->retryAttempt,
                    ]
                );
            } else {
                Log::warning('Payment retry failed', [
                    'original_transaction_id' => $this->originalTransactionId,
                    'retry_attempt' => $this->retryAttempt,
                    'error' => $result->getErrorMessage(),
                ]);

                // Check if we should schedule another retry
                if ($this->retryAttempt < 3 && $this->canRetryAgain($result)) {
                    self::dispatch(
                        $this->paymentRequest,
                        $this->originalTransactionId,
                        $this->retryReason,
                        $this->retryAttempt + 1
                    );
                } else {
                    // Fire final failure event
                    PaymentFailed::dispatch(
                        $result->getTransactionData(),
                        $this->paymentRequest->provider,
                        'Payment retry exhausted: ' . $result->getErrorMessage(),
                        'retry_exhausted',
                        [
                            'original_transaction_id' => $this->originalTransactionId,
                            'total_retry_attempts' => $this->retryAttempt,
                        ]
                    );
                }
            }

        } catch (\Exception $e) {
            Log::error('Payment retry processing failed', [
                'original_transaction_id' => $this->originalTransactionId,
                'retry_attempt' => $this->retryAttempt,
                'error' => $e->getMessage(),
            ]);

            throw $e; // Re-throw to trigger job retry
        }
    }

    /**
     * Handle job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::critical('Payment retry job permanently failed', [
            'original_transaction_id' => $this->originalTransactionId,
            'retry_attempt' => $this->retryAttempt,
            'error' => $exception->getMessage(),
        ]);
    }

    /**
     * Calculate delay for retry based on attempt number
     */
    private function calculateDelay(): int
    {
        // Exponential backoff: 2^attempt minutes, capped at 60 minutes
        return min(60, pow(2, $this->retryAttempt));
    }

    /**
     * Check if payment should still be retried based on business rules
     */
    private function shouldRetryPayment(): bool
    {
        // Don't retry if too much time has passed (24 hours)
        $originalTime = $this->paymentRequest->metadata['created_at'] ?? now();
        if (now()->diffInHours($originalTime) > 24) {
            return false;
        }

        // Don't retry high-value payments without manual approval
        if ($this->paymentRequest->amount > 5000 && $this->retryAttempt > 1) {
            return false;
        }

        // Check if order is still valid and not cancelled
        if (isset($this->paymentRequest->metadata['order_id'])) {
            $order = \App\Models\Order::find($this->paymentRequest->metadata['order_id']);
            if (!$order || $order->status === 'cancelled') {
                return false;
            }
        }

        return true;
    }

    /**
     * Check if we can retry again based on the error
     */
    private function canRetryAgain($result): bool
    {
        $nonRetryableErrors = [
            'invalid_card',
            'insufficient_funds',
            'card_declined',
            'invalid_amount',
            'fraud_detected',
        ];

        return !in_array($result->getErrorCode(), $nonRetryableErrors);
    }

    /**
     * Get job tags for horizon monitoring
     */
    public function tags(): array
    {
        return [
            'payment-retry',
            "provider:{$this->paymentRequest->provider}",
            "attempt:{$this->retryAttempt}",
            'transaction:' . $this->originalTransactionId,
        ];
    }
}