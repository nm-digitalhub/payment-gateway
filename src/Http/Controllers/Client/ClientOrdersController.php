<?php

namespace NMDigitalHub\PaymentGateway\Http\Controllers\Client;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\View\View;
use NMDigitalHub\PaymentGateway\Models\Order;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * קונטרולר הזמנות לקוח
 */
class ClientOrdersController
{
    public function index(Request $request): View
    {
        $query = Order::where('user_id', Auth::id())->latest();
        
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        
        if ($request->filled('service_type')) {
            $query->where('service_type', $request->service_type);
        }
        
        if ($request->filled('from_date')) {
            $query->whereDate('created_at', '>=', $request->from_date);
        }
        
        if ($request->filled('to_date')) {
            $query->whereDate('created_at', '<=', $request->to_date);
        }
        
        $orders = $query->paginate(15);
        
        $stats = [
            'total_orders' => Order::where('user_id', Auth::id())->count(),
            'completed_orders' => Order::where('user_id', Auth::id())
                ->where('status', 'completed')->count(),
            'pending_orders' => Order::where('user_id', Auth::id())
                ->where('status', 'pending')->count(),
            'cancelled_orders' => Order::where('user_id', Auth::id())
                ->where('status', 'cancelled')->count(),
            'total_amount' => Order::where('user_id', Auth::id())
                ->where('status', 'completed')->sum('amount')
        ];
        
        return view('payment-gateway::client.orders.index', compact('orders', 'stats'));
    }
    
    public function show(Order $order): View
    {
        // ודא שההזמנה שייכת למשתמש הנוכחי
        if ($order->user_id !== Auth::id()) {
            abort(403);
        }
        
        return view('payment-gateway::client.orders.show', compact('order'));
    }
    
    public function downloadInvoice(Order $order): StreamedResponse
    {
        if ($order->user_id !== Auth::id()) {
            abort(403);
        }
        
        if ($order->status !== 'completed') {
            abort(404, 'חשבונית לא זמינה עבור הזמנה שלא הושלמה');
        }
        
        return $this->generateInvoicePdf($order);
    }
    
    public function cancelOrder(Request $request, Order $order): JsonResponse
    {
        if ($order->user_id !== Auth::id()) {
            abort(403);
        }
        
        if (!in_array($order->status, ['pending', 'processing'])) {
            return response()->json([
                'success' => false,
                'message' => 'לא ניתן לבטל הזמנה זו'
            ], 400);
        }
        
        try {
            $order->update([
                'status' => 'cancelled',
                'cancellation_reason' => $request->input('reason', 'בוטל על ידי הלקוח'),
                'cancelled_at' => now()
            ]);
            
            return response()->json([
                'success' => true,
                'message' => 'ההזמנה בוטלה בהצלחה'
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'שגיאה בביטול ההזמנה'
            ], 500);
        }
    }
    
    public function getOrderStatus(Order $order): JsonResponse
    {
        if ($order->user_id !== Auth::id()) {
            abort(403);
        }
        
        return response()->json([
            'order_id' => $order->id,
            'status' => $order->status,
            'status_display' => $this->getStatusDisplay($order->status),
            'progress' => $this->getStatusProgress($order->status),
            'last_updated' => $order->updated_at->toISOString(),
            'estimated_completion' => $this->getEstimatedCompletion($order)
        ]);
    }
    
    protected function getStatusDisplay(string $status): string
    {
        $statusMap = [
            'pending' => 'ממתין לעיבוד',
            'processing' => 'בעיבוד',
            'completed' => 'הושלם',
            'cancelled' => 'בוטל',
            'failed' => 'נכשל'
        ];
        
        return $statusMap[$status] ?? $status;
    }
    
    protected function getStatusProgress(string $status): int
    {
        $progressMap = [
            'pending' => 20,
            'processing' => 60,
            'completed' => 100,
            'cancelled' => 0,
            'failed' => 0
        ];
        
        return $progressMap[$status] ?? 0;
    }
    
    protected function getEstimatedCompletion(Order $order): ?string
    {
        if (in_array($order->status, ['completed', 'cancelled', 'failed'])) {
            return null;
        }
        
        // זמן השלמה משוער לפי סוג השירות
        $estimatedMinutes = match($order->service_type ?? 'default') {
            'domain' => 5,
            'hosting' => 15,
            'ssl' => 10,
            'esim' => 2,
            default => 10
        };
        
        return now()->addMinutes($estimatedMinutes)->toISOString();
    }
    
    protected function generateInvoicePdf(Order $order): StreamedResponse
    {
        $fileName = "invoice-{$order->id}.pdf";
        
        return response()->streamDownload(function () use ($order) {
            // כאן אפשר להשתמש ב-DomPDF או כלי אחר
            $html = view('payment-gateway::invoices.pdf', compact('order'))->render();
            
            // פשוט - החזרת HTML כ-PDF
            echo $html;
        }, $fileName, [
            'Content-Type' => 'application/pdf'
        ]);
    }
}