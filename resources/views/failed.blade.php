@extends('layouts.app', ['title' => 'תשלום נכשל'])

@section('content')
<div class="min-h-screen bg-gray-50 py-8" dir="rtl">
    <div class="max-w-2xl mx-auto px-4 sm:px-6 lg:px-8">
        
        {{-- Error Message --}}
        <div class="bg-white rounded-lg shadow-lg p-8 text-center">
            
            {{-- Error Icon --}}
            <div class="mx-auto flex items-center justify-center h-16 w-16 rounded-full bg-red-100 mb-6">
                <svg class="h-8 w-8 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                </svg>
            </div>

            {{-- Title --}}
            <h1 class="text-2xl font-bold text-gray-900 mb-4">
                ⚠️ התשלום לא הושלם
            </h1>
            
            {{-- Error Message --}}
            <div class="bg-red-50 border border-red-200 rounded-lg p-4 mb-6">
                @if($errorMessage ?? false)
                    <p class="text-red-800 font-medium">{{ $errorMessage }}</p>
                @else
                    <p class="text-red-800 font-medium">התשלום נכשל או בוטל על ידי המשתמש</p>
                @endif
            </div>

            {{-- Transaction Details (if available) --}}
            @if($transaction)
            <div class="bg-gray-50 rounded-lg p-6 mb-6 text-right">
                <h3 class="text-lg font-semibold text-gray-900 mb-4">פרטי העסקה</h3>
                
                <div class="space-y-2">
                    <div class="flex justify-between">
                        <span class="font-medium">מספר עסקה:</span>
                        <span class="text-gray-600">{{ $transaction->reference }}</span>
                    </div>
                    
                    @if($transaction->gateway_transaction_id)
                    <div class="flex justify-between">
                        <span class="font-medium">מספר CardCom:</span>
                        <span class="text-gray-600">{{ $transaction->gateway_transaction_id }}</span>
                    </div>
                    @endif
                    
                    <div class="flex justify-between">
                        <span class="font-medium">סכום:</span>
                        <span class="text-gray-600">
                            {{ number_format($transaction->amount, 2) }} {{ $transaction->currency }}
                        </span>
                    </div>
                    
                    @if($transaction->failure_reason)
                    <div class="flex justify-between">
                        <span class="font-medium">סיבת הכישלון:</span>
                        <span class="text-red-600">{{ $transaction->failure_reason }}</span>
                    </div>
                    @endif
                    
                    <div class="flex justify-between">
                        <span class="font-medium">תאריך ושעה:</span>
                        <span class="text-gray-600">
                            {{ now()->format('d/m/Y H:i') }}
                        </span>
                    </div>
                    
                    <div class="flex justify-between">
                        <span class="font-medium">סטטוס:</span>
                        <span class="text-red-600 font-semibold">❌ נכשל</span>
                    </div>
                </div>
            </div>
            @endif

            {{-- Order Reference (Fallback) --}}
            @if(!$transaction && ($orderRef || $dealId))
            <div class="bg-gray-50 rounded-lg p-6 mb-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-2">פרטי התשלום</h3>
                
                @if($orderRef)
                <p class="text-gray-600">
                    <strong>מספר הזמנה:</strong> {{ $orderRef }}
                </p>
                @endif
                
                @if($dealId)
                <p class="text-gray-600">
                    <strong>מספר עסקה CardCom:</strong> {{ $dealId }}
                </p>
                @endif
            </div>
            @endif

            {{-- Common Failure Reasons --}}
            <div class="text-right mb-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-3">סיבות אפשריות לכישלון:</h3>
                <ul class="text-gray-600 space-y-2">
                    <li class="flex items-start">
                        <svg class="w-5 h-5 text-yellow-500 mt-0.5 ml-2 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.962-.833-2.732 0L3.268 16.5c-.77.833.192 2.5 1.732 2.5z"></path>
                        </svg>
                        יתרה לא מספקת בכרטיס
                    </li>
                    <li class="flex items-start">
                        <svg class="w-5 h-5 text-yellow-500 mt-0.5 ml-2 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.962-.833-2.732 0L3.268 16.5c-.77.833.192 2.5 1.732 2.5z"></path>
                        </svg>
                        פרטי כרטיס לא תקינים
                    </li>
                    <li class="flex items-start">
                        <svg class="w-5 h-5 text-yellow-500 mt-0.5 ml-2 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.962-.833-2.732 0L3.268 16.5c-.77.833.192 2.5 1.732 2.5z"></path>
                        </svg>
                        כרטיס חסום או פג תוקף
                    </li>
                    <li class="flex items-start">
                        <svg class="w-5 h-5 text-yellow-500 mt-0.5 ml-2 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.962-.833-2.732 0L3.268 16.5c-.77.833.192 2.5 1.732 2.5z"></path>
                        </svg>
                        התשלום בוטל על ידי המשתמש
                    </li>
                    <li class="flex items-start">
                        <svg class="w-5 h-5 text-yellow-500 mt-0.5 ml-2 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.962-.833-2.732 0L3.268 16.5c-.77.833.192 2.5 1.732 2.5z"></path>
                        </svg>
                        כישלון ב-3D Secure
                    </li>
                </ul>
            </div>

            {{-- Action Buttons --}}
            <div class="space-y-3">
                {{-- Try Again Button --}}
                <button onclick="history.back()" 
                        class="w-full bg-blue-600 hover:bg-blue-700 text-white font-semibold py-3 px-6 rounded-lg transition-colors">
                    🔄 נסה שוב
                </button>
                
                {{-- Different Payment Method --}}
                <a href="{{ url()->previous() }}" 
                   class="block w-full bg-gray-100 hover:bg-gray-200 text-gray-700 font-medium py-3 px-6 rounded-lg transition-colors">
                    💳 נסה אמצעי תשלום אחר
                </a>
                
                {{-- Back to Site --}}
                <a href="{{ url('/') }}" 
                   class="block w-full text-blue-600 hover:text-blue-800 font-medium py-2 transition-colors">
                    חזרה לאתר הראשי
                </a>
            </div>

            {{-- Help Section --}}
            <div class="mt-8 pt-6 border-t border-gray-200">
                <h3 class="text-lg font-semibold text-gray-900 mb-3">זקוקים לעזרה?</h3>
                
                <div class="grid md:grid-cols-2 gap-4 text-sm">
                    <div class="flex items-center justify-center p-3 bg-blue-50 rounded-lg">
                        <svg class="w-5 h-5 text-blue-600 ml-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"></path>
                        </svg>
                        <div class="text-center">
                            <p class="font-medium text-blue-900">טלפון</p>
                            <p class="text-blue-700">03-1234567</p>
                        </div>
                    </div>
                    
                    <div class="flex items-center justify-center p-3 bg-green-50 rounded-lg">
                        <svg class="w-5 h-5 text-green-600 ml-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 4.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path>
                        </svg>
                        <div class="text-center">
                            <p class="font-medium text-green-900">אימייל</p>
                            <p class="text-green-700">support@example.com</p>
                        </div>
                    </div>
                </div>
                
                <p class="text-gray-500 mt-4 text-sm">
                    שירות הלקוחות זמין בימים א'-ה' בין 9:00-18:00
                </p>
            </div>

            {{-- Security Notice --}}
            <div class="mt-6 pt-4 border-t border-gray-200 text-center">
                <p class="text-sm text-gray-500 mb-2">
                    פרטי הכרטיס שלכם מוגנים ולא נשמרים אצלנו
                </p>
                <div class="flex items-center justify-center space-x-4">
                    <img src="{{ asset('images/security/cardcom.png') }}" alt="CardCom" class="h-8">
                    <img src="{{ asset('images/security/ssl.png') }}" alt="SSL" class="h-8">
                    <img src="{{ asset('images/security/pci.png') }}" alt="PCI DSS" class="h-8">
                </div>
            </div>
        </div>
    </div>
</div>

{{-- Auto-retry script (optional) --}}
@if(request()->get('auto_retry'))
<script>
let retryCount = 0;
const maxRetries = 3;

function autoRetry() {
    if (retryCount < maxRetries) {
        retryCount++;
        setTimeout(() => {
            history.back();
        }, 5000);
    }
}

// autoRetry(); // Uncomment to enable auto-retry
</script>
@endif
@endsection