<?php

namespace NMDigitalHub\PaymentGateway\Listeners;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use App\Models\User;
use NMDigitalHub\PaymentGateway\Events\PaymentProcessed;
use NMDigitalHub\PaymentGateway\Events\PaymentFailed;
use NMDigitalHub\PaymentGateway\Events\TokenCreated;
use NMDigitalHub\PaymentGateway\Notifications\PaymentSuccessNotification;
use NMDigitalHub\PaymentGateway\Notifications\PaymentFailedNotification;
use NMDigitalHub\PaymentGateway\Notifications\TokenCreatedNotification;

class SendPaymentNotifications implements ShouldQueue
{
    use InteractsWithQueue;

    /**
     * Handle payment processed events
     */
    public function handlePaymentProcessed(PaymentProcessed $event): void
    {
        if (!$event->isSuccessful()) {
            return;
        }

        // Get user from transaction metadata or order
        $user = $this->getUserFromTransaction($event->transaction);
        
        if ($user) {
            $user->notify(new PaymentSuccessNotification(
                $event->transaction,
                $event->provider,
                $event->metadata
            ));
        }

        // Send admin notification for high amounts
        if ($event->getAmount() > 1000) {
            $this->notifyAdmins('payment.high_amount', [
                'transaction_id' => $event->getTransactionId(),
                'amount' => $event->getAmount(),
                'provider' => $event->provider,
            ]);
        }
    }

    /**
     * Handle payment failed events
     */
    public function handlePaymentFailed(PaymentFailed $event): void
    {
        $user = $this->getUserFromTransaction($event->transaction);
        
        if ($user) {
            $user->notify(new PaymentFailedNotification(
                $event->transaction,
                $event->provider,
                $event->errorMessage,
                $event->metadata
            ));
        }

        // Send admin notification for critical failures
        if (!$event->isRetryable()) {
            $this->notifyAdmins('payment.critical_failure', [
                'error_details' => $event->getErrorDetails(),
                'requires_investigation' => true,
            ]);
        }
    }

    /**
     * Handle token created events
     */
    public function handleTokenCreated(TokenCreated $event): void
    {
        $event->user->notify(new TokenCreatedNotification(
            $event->token,
            $event->provider,
            $event->isFirstToken(),
            $event->metadata
        ));
    }

    /**
     * Get user from transaction data
     */
    private function getUserFromTransaction($transaction): ?User
    {
        // Try to get user from transaction metadata
        if (isset($transaction->metadata['user_id'])) {
            return User::find($transaction->metadata['user_id']);
        }

        // Try to get user from order if available
        if (isset($transaction->metadata['order_id'])) {
            $order = \App\Models\Order::find($transaction->metadata['order_id']);
            return $order?->user;
        }

        // Try to get user from email
        if (isset($transaction->customerEmail)) {
            return User::where('email', $transaction->customerEmail)->first();
        }

        return null;
    }

    /**
     * Notify administrators
     */
    private function notifyAdmins(string $type, array $data): void
    {
        $adminUsers = User::where('is_admin', true)->get();
        
        foreach ($adminUsers as $admin) {
            $admin->notify(new \App\Notifications\AdminPaymentNotification($type, $data));
        }
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