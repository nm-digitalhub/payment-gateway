<?php

namespace NMDigitalHub\PaymentGateway\Http\Controllers;

use NMDigitalHub\PaymentGateway\Models\PaymentPage;
use NMDigitalHub\PaymentGateway\Services\CardComLowProfileService;
use NMDigitalHub\PaymentGateway\Facades\Payment;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use App\Models\Order;
use App\Models\Payment as PaymentModel;

class CheckoutController
{
    protected CardComLowProfileService $cardcomService;

    public function __construct(CardComLowProfileService $cardcomService)
    {
        $this->cardcomService = $cardcomService;
    }

    /**
     * צפייה בעמוד תשלום ציבורי לפי slug
     */
    public function showPaymentPage(string $slug, Request $request): View
    {
        $page = PaymentPage::published()
            ->where('slug', $slug)
            ->firstOrFail();

        // בדיקת גישה
        if (!$page->is_public || ($page->require_auth && !auth()->check())) {
            abort(403, 'אין גישה לעמוד זה');
        }

        // פרמטרים מ-URL
        $prefilledData = [
            'amount' => $request->get('amount'),
            'currency' => $request->get('currency', 'ILS'),
            'description' => $request->get('description'),
            'product_id' => $request->get('product_id'),
            'package_slug' => $request->get('package_slug'),
            'customer_email' => $request->get('email'),
            'customer_name' => $request->get('name'),
            'success_url' => $request->get('success_url'),
            'failed_url' => $request->get('failed_url'),
        ];

        return view('payment-gateway::checkout.page', [
            'page' => $page,
            'prefilledData' => array_filter($prefilledData),
            'paymentProviders' => Payment::getAvailablePaymentProviders(),
            'savedTokens' => $this->getSavedTokensForUser()
        ]);
    }

    /**
     * עיבוד בקשת תשלום מעמוד ציבורי - שילוב עם המערכת המאוחדת
     */
    public function processPayment(Request $request, string $slug): JsonResponse
    {
        $page = PaymentPage::published()
            ->where('slug', $slug)
            ->firstOrFail();

        // ואלידציה
        $validator = Validator::make($request->all(), [
            'amount' => 'required|numeric|min:0.01',
            'currency' => 'required|string|in:ILS,USD,EUR',
            'customer_name' => 'required|string|max:255',
            'customer_email' => 'required|email|max:255',
            'customer_phone' => 'nullable|string|max:20',
            'description' => 'nullable|string|max:255',
            'payment_method_type' => 'required|string|in:new_card,saved_token',
            'saved_token_id' => 'required_if:payment_method_type,saved_token|exists:payment_tokens,id',
            'cvv' => 'required_if:payment_method_type,saved_token|string|size:3',
            'save_payment_method' => 'boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $data = $validator->validated();
            
            // בדיקה אם מדובר בתשלום עם טוקן שמור
            if ($data['payment_method_type'] === 'saved_token') {
                return $this->processSavedTokenPayment($data, $page);
            }
            
            // תשלום עם כרטיס חדש - יצירת LowProfile
            return $this->processNewCardPayment($data, $page);
            
        } catch (\Exception $e) {
            Log::error('Payment processing failed', [
                'page_slug' => $slug,
                'error' => $e->getMessage(),
                'data' => $request->except(['cvv'])
            ]);

            return response()->json([
                'success' => false,
                'message' => 'שגיאה בעיבוד התשלום: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * עיבוד תשלום עם כרטיס חדש - CardCom LowProfile כמו במערכת eSIM
     */
    protected function processNewCardPayment(array $data, PaymentPage $page): JsonResponse
    {
        // הכנת פרמטרים ל-CardCom
        $cardcomParams = [
            'amount' => $data['amount'],
            'currency' => $data['currency'],
            'customer_name' => $data['customer_name'],
            'customer_email' => $data['customer_email'],
            'customer_phone' => $data['customer_phone'] ?? null,
            'description' => $data['description'] ?? $page->title,
            'product_name' => $page->title,
            'save_payment_method' => $data['save_payment_method'] ?? false,
            'should_create_token' => $data['save_payment_method'] ?? false,
            'success_url' => url("/payment/success?page={$page->slug}"),
            'failed_url' => url("/payment/failed?page={$page->slug}"),
            'webhook_url' => url('/webhooks/cardcom')
        ];

        $session = $this->cardcomService->createLowProfilePayment($cardcomParams);
        
        // שמירת הזמנה אם נדרש
        if (isset($data['create_order']) && $data['create_order']) {
            $this->createPendingOrder($data, $session->sessionReference);
        }

        return response()->json([
            'success' => true,
            'checkout_url' => $session->checkoutUrl,
            'session_reference' => $session->sessionReference,
            'low_profile_id' => $session->lowProfileId,
            'message' => 'מעבר לדף התשלום...'
        ]);
    }

    /**
     * עיבוד תשלום עם טוקן שמור - Do3DSAndSubmit
     */
    protected function processSavedTokenPayment(array $data, PaymentPage $page): JsonResponse
    {
        $tokenParams = [
            'saved_token_id' => $data['saved_token_id'],
            'cvv' => $data['cvv'],
            'amount' => $data['amount'],
            'currency' => $data['currency'],
            'customer_name' => $data['customer_name'],
            'customer_email' => $data['customer_email'],
            'customer_phone' => $data['customer_phone'] ?? null,
            'description' => $data['description'] ?? $page->title,
            'product_name' => $page->title,
            'success_url' => url("/payment/success?page={$page->slug}"),
            'failed_url' => url("/payment/failed?page={$page->slug}"),
            'webhook_url' => url('/webhooks/cardcom')
        ];

        $transaction = $this->cardcomService->processTokenPayment($tokenParams);
        
        // בדיקה אם נדרש 3D Secure
        if ($transaction->status === '3ds_required') {
            return response()->json([
                'success' => true,
                'requires_3ds' => true,
                'three_ds_url' => $transaction->metadata['three_ds_url'],
                'transaction_id' => $transaction->transactionId,
                'message' => 'מעבר לאימות 3D Secure...'
            ]);
        }
        
        // תשלום ישיר בלי 3DS
        if ($transaction->status === 'success') {
            // שמירת הזמנה אם נדרש
            if (isset($data['create_order']) && $data['create_order']) {
                $order = $this->createSuccessfulOrder($data, $transaction->reference, $transaction);
            }
            
            return response()->json([
                'success' => true,
                'payment_completed' => true,
                'transaction_id' => $transaction->transactionId,
                'order_ref' => $transaction->reference,
                'redirect_url' => url("/payment/success?page={$page->slug}&order={$transaction->reference}"),
                'message' => 'התשלום בוצע בהצלחה!'
            ]);
        }
        
        // תשלום נכשל
        return response()->json([
            'success' => false,
            'message' => $transaction->failureReason ?? 'התשלום נכשל',
            'transaction_id' => $transaction->transactionId
        ], 422);
    }

    /**
     * עמוד הצלחה
     */
    public function paymentSuccess(Request $request): View
    {
        $pageSlug = $request->get('page');
        $orderRef = $request->get('order');
        $dealId = $request->get('deal_id');
        
        $page = null;
        if ($pageSlug) {
            $page = PaymentPage::where('slug', $pageSlug)->first();
        }
        
        // חיפוש עסקה במאגר המידע
        $transaction = null;
        if ($orderRef || $dealId) {
            $transaction = Payment::getTransaction($orderRef ?: $dealId);
        }
        
        return view('payment-gateway::checkout.success', [
            'page' => $page,
            'transaction' => $transaction,
            'orderRef' => $orderRef,
            'dealId' => $dealId
        ]);
    }

    /**
     * עמוד כישלון
     */
    public function paymentFailed(Request $request): View
    {
        $pageSlug = $request->get('page');
        $orderRef = $request->get('order');
        $dealId = $request->get('deal_id');
        
        $page = null;
        if ($pageSlug) {
            $page = PaymentPage::where('slug', $pageSlug)->first();
        }
        
        $transaction = null;
        if ($orderRef || $dealId) {
            $transaction = Payment::getTransaction($orderRef ?: $dealId);
        }
        
        return view('payment-gateway::checkout.failed', [
            'page' => $page,
            'transaction' => $transaction,
            'orderRef' => $orderRef,
            'dealId' => $dealId,
            'errorMessage' => $request->get('error')
        ]);
    }

    /**
     * קבלת טוקנים שמורים למשתמש נוכחי
     */
    protected function getSavedTokensForUser(): array
    {
        if (!auth()->check()) {
            return [];
        }

        return \App\Models\PaymentToken::where('user_id', auth()->id())
            ->where('is_active', true)
            ->where('expires_at', '>', now())
            ->orderBy('is_default', 'desc')
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($token) {
                return [
                    'id' => $token->id,
                    'card_brand' => $token->card_brand,
                    'card_last_four' => $token->card_last_four,
                    'expires_at' => $token->expires_at->format('m/y'),
                    'is_default' => $token->is_default
                ];
            })
            ->toArray();
    }

    /**
     * יצירת הזמנה ממתינה
     */
    protected function createPendingOrder(array $data, string $orderRef): void
    {
        // אם קיים מודל Order במערכת
        if (class_exists(\App\Models\Order::class)) {
            \App\Models\Order::create([
                'reference' => $orderRef,
                'user_id' => auth()->id(),
                'amount' => $data['amount'],
                'currency' => $data['currency'],
                'status' => 'pending',
                'customer_email' => $data['customer_email'],
                'customer_name' => $data['customer_name'],
                'description' => $data['description'] ?? 'Payment via gateway',
                'metadata' => $data
            ]);
        }
    }

    /**
     * יצירת הזמנה מוצלחת
     */
    protected function createSuccessfulOrder(array $data, string $orderRef, $transaction)
    {
        if (!class_exists(\App\Models\Order::class)) {
            return null;
        }
        
        return \App\Models\Order::create([
            'reference' => $orderRef,
            'user_id' => auth()->id(),
            'amount' => $data['amount'],
            'currency' => $data['currency'],
            'status' => 'completed',
            'customer_email' => $data['customer_email'],
            'customer_name' => $data['customer_name'],
            'description' => $data['description'] ?? 'Payment via gateway',
            'transaction_id' => $transaction->transactionId,
            'completed_at' => now(),
            'metadata' => array_merge($data, [
                'transaction_data' => $transaction->toArray()
            ])
        ]);
    }
}
