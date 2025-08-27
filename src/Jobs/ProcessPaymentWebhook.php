<?php

namespace NMDigitalHub\PaymentGateway\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use NMDigitalHub\PaymentGateway\PaymentGatewayManager;
use NMDigitalHub\PaymentGateway\Events\PaymentProcessed;
use NMDigitalHub\PaymentGateway\Events\PaymentFailed;

class ProcessPaymentWebhook implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 300; // 5 minutes

    public function __construct(
        public readonly string $provider,
        public readonly array $webhookData,
        public readonly array $headers = [],
        public readonly ?string $signature = null
    ) {
        $this->onQueue('payment-webhooks');
    }

    /**
     * Execute the job.
     */
    public function handle(PaymentGatewayManager $paymentManager): void
    {
        try {
            Log::info('Processing payment webhook', [
                'provider' => $this->provider,
                'webhook_id' => $this->webhookData['id'] ?? 'unknown',
                'attempt' => $this->attempts(),
            ]);

            // Get the provider instance
            $providerInstance = $paymentManager->getProvider($this->provider);
            
            if (!$providerInstance) {
                throw new \Exception("Provider {$this->provider} not found");
            }

            // Verify webhook signature
            if (!$providerInstance->verifyWebhookSignature($this->webhookData, $this->signature, $this->headers)) {
                throw new \Exception('Invalid webhook signature');
            }

            // Process the webhook
            $result = $providerInstance->processWebhook($this->webhookData);

            if ($result->isSuccessful()) {
                // Fire payment processed event
                PaymentProcessed::dispatch(
                    $result->getTransactionData(),
                    $this->provider,
                    ['webhook_id' => $this->webhookData['id'] ?? null]
                );
            } else {
                // Fire payment failed event
                PaymentFailed::dispatch(
                    $result->getTransactionData(),
                    $this->provider,
                    $result->getErrorMessage(),
                    $result->getErrorCode(),
                    ['webhook_id' => $this->webhookData['id'] ?? null]
                );
            }

            Log::info('Payment webhook processed successfully', [
                'provider' => $this->provider,
                'transaction_id' => $result->getTransactionData()->transactionId,
                'status' => $result->getTransactionData()->status,
            ]);

        } catch (\Exception $e) {
            Log::error('Payment webhook processing failed', [
                'provider' => $this->provider,
                'webhook_data' => $this->webhookData,
                'error' => $e->getMessage(),
                'attempt' => $this->attempts(),
            ]);

            // If this is the last attempt, send admin notification
            if ($this->attempts() >= $this->tries) {
                $this->notifyAdminsOfFailure($e);
            }

            throw $e; // Re-throw to trigger retry
        }
    }

    /**
     * Handle job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::critical('Payment webhook job permanently failed', [
            'provider' => $this->provider,
            'webhook_data' => $this->webhookData,
            'error' => $exception->getMessage(),
            'attempts' => $this->tries,
        ]);

        $this->notifyAdminsOfFailure($exception);
    }

    /**
     * Notify administrators of webhook processing failure
     */
    private function notifyAdminsOfFailure(\Throwable $exception): void
    {
        // Send admin notification about webhook failure
        try {
            \Notification::route('mail', config('payment-gateway.admin_email', 'admin@example.com'))
                ->notify(new \NMDigitalHub\PaymentGateway\Notifications\WebhookFailedNotification(
                    $this->provider,
                    $this->webhookData,
                    $exception->getMessage()
                ));
        } catch (\Exception $e) {
            Log::error('Failed to send webhook failure notification', [
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Get job tags for horizon monitoring
     */
    public function tags(): array
    {
        return [
            'payment-webhook',
            "provider:{$this->provider}",
            'transaction:' . ($this->webhookData['transaction_id'] ?? 'unknown'),
        ];
    }
}