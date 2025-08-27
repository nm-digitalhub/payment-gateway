<?php

namespace NMDigitalHub\PaymentGateway\Http\Controllers\Client;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\View\View;
use NMDigitalHub\PaymentGateway\Models\PaymentTransaction;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * קונטרולר תשלומים לקוח
 */
class ClientPaymentsController
{
    public function index(Request $request): View
    {
        $query = PaymentTransaction::where('customer_email', Auth::user()->email)
            ->latest();
        
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        
        if ($request->filled('provider')) {
            $query->where('provider', $request->provider);
        }
        
        if ($request->filled('from_date')) {
            $query->whereDate('created_at', '>=', $request->from_date);
        }
        
        if ($request->filled('to_date')) {
            $query->whereDate('created_at', '<=', $request->to_date);
        }
        
        $payments = $query->paginate(15);
        
        $stats = [
            'total_payments' => PaymentTransaction::where('customer_email', Auth::user()->email)->count(),
            'successful_payments' => PaymentTransaction::where('customer_email', Auth::user()->email)
                ->where('status', 'success')->count(),
            'failed_payments' => PaymentTransaction::where('customer_email', Auth::user()->email)
                ->where('status', 'failed')->count(),
            'total_amount' => PaymentTransaction::where('customer_email', Auth::user()->email)
                ->where('status', 'success')->sum('amount')
        ];
        
        return view('payment-gateway::client.payments.index', compact('payments', 'stats'));
    }
    
    public function show(PaymentTransaction $payment): View
    {
        // וידוא שהתשלום שייך למשתמש הנוכחי
        if ($payment->customer_email !== Auth::user()->email) {
            abort(403);
        }
        
        return view('payment-gateway::client.payments.show', compact('payment'));
    }
    
    public function downloadReceipt(PaymentTransaction $payment): StreamedResponse
    {
        if ($payment->customer_email !== Auth::user()->email) {
            abort(403);
        }
        
        if ($payment->status !== 'success') {
            abort(404, 'קבלה לא זמינה עבור תשלום שלא הושלם');
        }
        
        return $this->generateReceiptPdf($payment);
    }
    
    public function paymentMethods(): View
    {
        // אם יש מודל PaymentToken
        if (class_exists('\\App\\Models\\PaymentToken')) {
            $tokens = \App\Models\PaymentToken::where('user_id', Auth::id())
                ->where('is_active', true)
                ->get();
        } else {
            $tokens = collect();
        }
        
        return view('payment-gateway::client.payments.methods', compact('tokens'));
    }
    
    public function deletePaymentMethod($tokenId)
    {
        if (class_exists('\\App\\Models\\PaymentToken')) {
            $token = \App\Models\PaymentToken::where('id', $tokenId)
                ->where('user_id', Auth::id())
                ->firstOrFail();
                
            $token->update(['is_active' => false]);
        }
        
        return back()->with('success', 'אמצעי התשלום נמחק בהצלחה');
    }
    
    public function setDefaultPaymentMethod($tokenId)
    {
        if (class_exists('\\App\\Models\\PaymentToken')) {
            // ביטול ברירת מחדל קודמת
            \App\Models\PaymentToken::where('user_id', Auth::id())
                ->update(['is_default' => false]);
            
            // הגדרת אמצעי חדש כברירת מחדל
            $token = \App\Models\PaymentToken::where('id', $tokenId)
                ->where('user_id', Auth::id())
                ->firstOrFail();
                
            $token->update(['is_default' => true]);
        }
        
        return back()->with('success', 'אמצעי התשלום הוגדר כברירת מחדל');
    }
    
    public function getPaymentHistory(): JsonResponse
    {
        $payments = PaymentTransaction::where('customer_email', Auth::user()->email)
            ->latest()
            ->take(20)
            ->get(['id', 'amount', 'currency', 'status', 'provider', 'created_at']);
            
        return response()->json($payments);
    }
    
    protected function generateReceiptPdf(PaymentTransaction $payment): StreamedResponse
    {
        $fileName = "receipt-{$payment->transaction_id}.pdf";
        
        return response()->streamDownload(function () use ($payment) {
            // כאן אפשר להשתמש ב-DomPDF או כלי אחר
            $html = view('payment-gateway::receipts.pdf', compact('payment'))->render();
            
            // פשוט - החזרת HTML כ-PDF
            echo $html;
        }, $fileName, [
            'Content-Type' => 'application/pdf'
        ]);
    }
}
