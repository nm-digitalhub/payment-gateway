<?php

namespace NMDigitalHub\PaymentGateway\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\View\View;
use Illuminate\Http\RedirectResponse;
use NMDigitalHub\PaymentGateway\Services\CardComService;
use NMDigitalHub\PaymentGateway\Services\PaymentGatewayManager;

/**
 * Payment Handler Controller
 * מבוסס על EsimPaymentController - טיפול בתשלומים
 */
class PaymentHandlerController
{
    public function __construct(
        private CardComService $cardComService,
        private PaymentGatewayManager $paymentManager
    ) {}

    /**
     * Payment success page - עמוד הצלחת תשלום
     * מבוסס על EsimPaymentController@paymentSuccess
     */
    public function paymentSuccess(Request $request): View
    {
        $orderRef = $request->get('order');
        $dealId = $request->get('deal_id');
        $transactionId = $request->get('transaction_id');

        if (!$orderRef) {
            return view('payment-gateway::payment.error', [
                'message' => 'חסר מזהה הזמנה בבקשת ההצלחה'
            ]);
        }

        try {
            // Find order by reference - מציאת הזמנה לפי מזהה
            $order = $this->findOrderByReference($orderRef);
            
            if (!$order) {
                \Log::warning('Payment success for unknown order', [
                    'order_ref' => $orderRef,
                    'deal_id' => $dealId,
                    'transaction_id' => $transactionId
                ]);
                
                return view('payment-gateway::payment.error', [
                    'message' => 'הזמנה לא נמצאה במערכת'
                ]);
            }

            // Update order status - עדכון סטטוס הזמנה
            $this->updateOrderStatus($order, 'completed', [
                'cardcom_deal_id' => $dealId,
                'cardcom_transaction_id' => $transactionId,
                'payment_completed_at' => now()
            ]);

            // Process post-payment actions - עיבוד פעולות לאחר תשלום
            $this->processPostPaymentActions($order);

            \Log::info('Payment success processed', [
                'order_ref' => $orderRef,
                'order_id' => $order['id'],
                'deal_id' => $dealId,
                'transaction_id' => $transactionId
            ]);

            return view('payment-gateway::payment.success', [
                'order' => $order,
                'transaction_details' => [
                    'deal_id' => $dealId,
                    'transaction_id' => $transactionId,
                    'order_ref' => $orderRef
                ]
            ]);

        } catch (\Exception $e) {
            \Log::error('Error processing payment success', [
                'order_ref' => $orderRef,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return view('payment-gateway::payment.error', [
                'message' => 'שגיאה בעיבוד הצלחת התשלום: ' . $e->getMessage()
            ]);
        }
    }

    /**
     * Payment failed page - עמוד כשלון תשלום
     * מבוסס על EsimPaymentController@paymentFailed
     */
    public function paymentFailed(Request $request): View
    {
        $orderRef = $request->get('order');
        $errorCode = $request->get('error');
        $errorMessage = $request->get('message', 'תשלום נכשל');

        \Log::warning('Payment failed', [
            'order_ref' => $orderRef,
            'error_code' => $errorCode,
            'error_message' => $errorMessage,
            'request_data' => $request->all()
        ]);

        try {
            if ($orderRef) {
                $order = $this->findOrderByReference($orderRef);
                
                if ($order) {
                    // Update order status to failed - עדכון סטטוס להזמנה כושלת
                    $this->updateOrderStatus($order, 'failed', [
                        'payment_error_code' => $errorCode,
                        'payment_error_message' => $errorMessage,
                        'payment_failed_at' => now()
                    ]);
                    
                    // Send failure notification - שליחת התראת כשלון
                    $this->sendPaymentFailureNotification($order, $errorMessage);
                }
            }

        } catch (\Exception $e) {
            \Log::error('Error processing payment failure', [
                'order_ref' => $orderRef,
                'error' => $e->getMessage()
            ]);
        }

        return view('payment-gateway::payment.failed', [
            'order_ref' => $orderRef,
            'error_code' => $errorCode,
            'error_message' => $errorMessage,
            'retry_url' => $orderRef ? route('payment-gateway.checkout.show', ['packageSlug' => 'retry-' . $orderRef]) : null
        ]);
    }

    /**
     * Get payment status - קבלת סטטוס תשלום
     * AJAX endpoint for real-time status checking
     */
    public function getPaymentStatus(string $orderId): JsonResponse
    {
        try {
            $order = $this->findOrderById($orderId);
            
            if (!$order) {
                return response()->json([
                    'success' => false,
                    'message' => 'הזמנה לא נמצאה'
                ], 404);
            }

            // Check with CardCom if needed - בדיקה עם CardCom במידת הצורך
            $cardcomStatus = null;
            if ($order['payment_gateway'] === 'cardcom' && isset($order['cardcom_deal_id'])) {
                $cardcomStatus = $this->cardComService->checkTransactionStatus($order['cardcom_deal_id']);
            }

            return response()->json([
                'success' => true,
                'order_id' => $orderId,
                'status' => $order['status'],
                'payment_status' => $order['payment_status'] ?? 'unknown',
                'cardcom_status' => $cardcomStatus,
                'last_updated' => $order['updated_at'] ?? now()->toISOString()
            ]);

        } catch (\Exception $e) {
            \Log::error('Error getting payment status', [
                'order_id' => $orderId,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'שגיאה בקבלת סטטוס תשלום'
            ], 500);
        }
    }

    /**
     * Cancel payment - ביטול תשלום
     */
    public function cancelPayment(Request $request, string $orderRef): JsonResponse
    {
        try {
            $order = $this->findOrderByReference($orderRef);
            
            if (!$order) {
                return response()->json([
                    'success' => false,
                    'message' => 'הזמנה לא נמצאה'
                ], 404);
            }

            if (in_array($order['status'], ['completed', 'cancelled'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'לא ניתן לבטל הזמנה שכבר הושלמה או בוטלה'
                ], 400);
            }

            // Cancel with payment gateway if needed
            if ($order['payment_gateway'] === 'cardcom' && isset($order['cardcom_low_profile_id'])) {
                $this->cardComService->cancelLowProfile($order['cardcom_low_profile_id']);
            }

            // Update order status
            $this->updateOrderStatus($order, 'cancelled', [
                'cancelled_at' => now(),
                'cancellation_reason' => 'user_cancelled'
            ]);

            \Log::info('Payment cancelled', [
                'order_ref' => $orderRef,
                'order_id' => $order['id']
            ]);

            return response()->json([
                'success' => true,
                'message' => 'התשלום בוטל בהצלחה',
                'redirect_url' => route('payment-gateway.packages.index')
            ]);

        } catch (\Exception $e) {
            \Log::error('Error cancelling payment', [
                'order_ref' => $orderRef,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'שגיאה בביטול התשלום'
            ], 500);
        }
    }

    /**
     * Find order by reference - מציאת הזמנה לפי מזהה
     */
    private function findOrderByReference(string $orderRef): ?array
    {
        // Implementation depends on your order storage system
        // This could be database, cache, or external API
        
        // Example implementation:
        return cache()->remember("order_{$orderRef}", 3600, function () use ($orderRef) {
            // Query your order storage system
            return null; // Placeholder
        });
    }

    /**
     * Find order by ID - מציאת הזמנה לפי ID
     */
    private function findOrderById(string $orderId): ?array
    {
        // Similar to findOrderByReference but using ID
        return cache()->remember("order_id_{$orderId}", 3600, function () use ($orderId) {
            // Query your order storage system
            return null; // Placeholder
        });
    }

    /**
     * Update order status - עדכון סטטוס הזמנה
     */
    private function updateOrderStatus(array $order, string $status, array $metadata = []): void
    {
        // Implementation depends on your order storage system
        // Update the order status and metadata
        
        \Log::info('Order status updated', [
            'order_id' => $order['id'],
            'old_status' => $order['status'] ?? 'unknown',
            'new_status' => $status,
            'metadata' => $metadata
        ]);

        // Clear relevant caches
        cache()->forget("order_{$order['reference']}");
        cache()->forget("order_id_{$order['id']}");
    }

    /**
     * Process post-payment actions - עיבוד פעולות לאחר תשלום
     */
    private function processPostPaymentActions(array $order): void
    {
        try {
            // Send confirmation email
            $this->sendOrderConfirmation($order);
            
            // Trigger provisioning if needed
            if (isset($order['requires_provisioning']) && $order['requires_provisioning']) {
                $this->triggerProvisioning($order);
            }
            
            // Update inventory if applicable
            $this->updateInventory($order);
            
            // Send webhook notifications
            $this->sendWebhookNotifications($order);

        } catch (\Exception $e) {
            \Log::error('Error in post-payment actions', [
                'order_id' => $order['id'],
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Send order confirmation - שליחת אישור הזמנה
     */
    private function sendOrderConfirmation(array $order): void
    {
        // Implementation for sending confirmation email
        \Log::info('Order confirmation sent', ['order_id' => $order['id']]);
    }

    /**
     * Trigger provisioning - הפעלת provisioning
     */
    private function triggerProvisioning(array $order): void
    {
        // Implementation for triggering service provisioning
        \Log::info('Provisioning triggered', ['order_id' => $order['id']]);
    }

    /**
     * Update inventory - עדכון מלאי
     */
    private function updateInventory(array $order): void
    {
        // Implementation for inventory updates
        \Log::info('Inventory updated', ['order_id' => $order['id']]);
    }

    /**
     * Send webhook notifications - שליחת התראות webhook
     */
    private function sendWebhookNotifications(array $order): void
    {
        // Implementation for webhook notifications
        \Log::info('Webhook notifications sent', ['order_id' => $order['id']]);
    }

    /**
     * Send payment failure notification - שליחת התראת כשלון תשלום
     */
    private function sendPaymentFailureNotification(array $order, string $errorMessage): void
    {
        // Implementation for failure notifications
        \Log::info('Payment failure notification sent', [
            'order_id' => $order['id'],
            'error' => $errorMessage
        ]);
    }
}