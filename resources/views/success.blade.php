@extends('layouts.app', ['title' => 'תשלום הושלם בהצלחה'])

@section('content')
<div class="min-h-screen bg-gray-50 py-8" dir="rtl">
    <div class="max-w-2xl mx-auto px-4 sm:px-6 lg:px-8">
        
        {{-- Success Message --}}
        <div class="bg-white rounded-lg shadow-lg p-8 text-center">
            
            {{-- Success Icon --}}
            <div class="mx-auto flex items-center justify-center h-16 w-16 rounded-full bg-green-100 mb-6">
                <svg class="h-8 w-8 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                </svg>
            </div>

            {{-- Title --}}
            <h1 class="text-2xl font-bold text-gray-900 mb-4">
                🎉 התשלום הושלם בהצלחה!
            </h1>
            
            {{-- Description --}}
            <p class="text-lg text-gray-600 mb-6">
                תודה לכם על הרכישה. פרטי התשלום נשלחו אליכם במייל.
            </p>

            {{-- Transaction Details --}}
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
                        <span class="text-gray-600 font-semibold">
                            {{ number_format($transaction->amount, 2) }} {{ $transaction->currency }}
                        </span>
                    </div>
                    
                    @if($transaction->description)
                    <div class="flex justify-between">
                        <span class="font-medium">תיאור:</span>
                        <span class="text-gray-600">{{ $transaction->description }}</span>
                    </div>
                    @endif
                    
                    <div class="flex justify-between">
                        <span class="font-medium">תאריך ושעה:</span>
                        <span class="text-gray-600">
                            {{ $transaction->processed_at?->format('d/m/Y H:i') ?? 'עכשיו' }}
                        </span>
                    </div>
                    
                    <div class="flex justify-between">
                        <span class="font-medium">סטטוס:</span>
                        <span class="text-green-600 font-semibold">✅ אושר</span>
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

            {{-- Next Steps --}}
            <div class="text-right mb-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-3">השלבים הבאים:</h3>
                <ul class="text-gray-600 space-y-2">
                    <li class="flex items-start">
                        <svg class="w-5 h-5 text-green-500 mt-0.5 ml-2 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                        </svg>
                        אישור התשלום יישלח אליכם במייל תוך 5 דקות
                    </li>
                    <li class="flex items-start">
                        <svg class="w-5 h-5 text-green-500 mt-0.5 ml-2 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                        </svg>
                        הקבלה תתקבל על פי החוק
                    </li>
                    <li class="flex items-start">
                        <svg class="w-5 h-5 text-green-500 mt-0.5 ml-2 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                        </svg>
                        במקרה של שאלות, צרו איתנו קשר
                    </li>
                </ul>
            </div>

            {{-- Action Buttons --}}
            <div class="space-y-3">
                {{-- Print Receipt Button --}}
                <button onclick="window.print()" 
                        class="w-full bg-gray-100 hover:bg-gray-200 text-gray-700 font-medium py-3 px-6 rounded-lg transition-colors">
                    🖨️ הדפס קבלה
                </button>
                
                {{-- Back to Site Button --}}
                <a href="{{ url('/') }}" 
                   class="block w-full bg-blue-600 hover:bg-blue-700 text-white font-semibold py-3 px-6 rounded-lg transition-colors">
                    חזרה לאתר הראשי
                </a>
                
                {{-- Contact Support --}}
                <a href="{{ url('/contact') }}" 
                   class="block w-full text-blue-600 hover:text-blue-800 font-medium py-2 transition-colors">
                    צרו קשר עם התמיכה
                </a>
            </div>

            {{-- Security Notice --}}
            <div class="mt-8 pt-6 border-t border-gray-200 text-center">
                <p class="text-sm text-gray-500 mb-2">
                    התשלום עובד באמצעות CardCom בתקני אבטחה הגבוהים ביותר
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

{{-- Print Styles --}}
@push('styles')
<style>
@media print {
    body * {
        visibility: hidden;
    }
    .print-area, .print-area * {
        visibility: visible;
    }
    .print-area {
        position: absolute;
        left: 0;
        top: 0;
        width: 100%;
    }
    .no-print {
        display: none !important;
    }
}
</style>
@endpush

{{-- Auto-redirect after success (optional) --}}
@if(request()->get('auto_redirect'))
<script>
setTimeout(() => {
    window.location.href = '{{ url("/") }}';
}, 5000);
</script>
@endif
@endsection