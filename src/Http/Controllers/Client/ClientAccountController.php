<?php

namespace NMDigitalHub\PaymentGateway\Http\Controllers\Client;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\View\View;
use NMDigitalHub\PaymentGateway\Models\PaymentPage;
use NMDigitalHub\PaymentGateway\Models\PaymentTransaction;
use Illuminate\Support\Facades\Auth;

/**
 * קונטרולר חשבון לקוח - פאנל לקוח
 */
class ClientAccountController
{
    public function dashboard(): View
    {
        $user = Auth::user();
        
        $stats = [
            'total_payments' => PaymentTransaction::where('customer_email', $user->email)->count(),
            'successful_payments' => PaymentTransaction::where('customer_email', $user->email)
                ->where('status', 'success')->count(),
            'total_spent' => PaymentTransaction::where('customer_email', $user->email)
                ->where('status', 'success')->sum('amount'),
            'recent_transactions' => PaymentTransaction::where('customer_email', $user->email)
                ->latest()->take(5)->get()
        ];
        
        return view('payment-gateway::client.dashboard', compact('stats'));
    }
    
    public function profile(): View
    {
        return view('payment-gateway::client.profile', [
            'user' => Auth::user()
        ]);
    }
    
    public function updateProfile(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email,' . Auth::id(),
            'phone' => 'nullable|string|max:20'
        ]);
        
        Auth::user()->update($request->only(['name', 'email', 'phone']));
        
        return back()->with('success', 'הפרופיל עודכן בהצלחה');
    }
    
    public function showPublicPage(string $slug): View
    {
        $page = PaymentPage::where('slug', $slug)
            ->published()
            ->public()
            ->firstOrFail();
            
        return view('payment-gateway::pages.show', compact('page'));
    }
    
    public function previewPage(string $slug): View
    {
        $page = PaymentPage::where('slug', $slug)->firstOrFail();
        
        return view('payment-gateway::pages.preview', compact('page'));
    }
    
    public function getStats(): JsonResponse
    {
        $user = Auth::user();
        
        return response()->json([
            'payments_count' => PaymentTransaction::where('customer_email', $user->email)->count(),
            'successful_payments' => PaymentTransaction::where('customer_email', $user->email)
                ->where('status', 'success')->count(),
            'total_amount' => PaymentTransaction::where('customer_email', $user->email)
                ->where('status', 'success')->sum('amount'),
            'last_payment' => PaymentTransaction::where('customer_email', $user->email)
                ->latest()->first()?->created_at
        ]);
    }
    
    public function updateProfileField(Request $request, string $field): JsonResponse
    {
        $allowedFields = ['name', 'phone', 'timezone', 'language'];
        
        if (!in_array($field, $allowedFields)) {
            return response()->json(['error' => 'Invalid field'], 400);
        }
        
        $request->validate([
            'value' => 'required|string|max:255'
        ]);
        
        Auth::user()->update([$field => $request->value]);
        
        return response()->json(['success' => true]);
    }
}
