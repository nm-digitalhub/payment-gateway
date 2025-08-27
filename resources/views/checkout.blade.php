@extends('layouts.app', ['title' => 'תשלום מאובטח - ' . $package['name']])

@section('content')
<div class="min-h-screen bg-gray-50 py-8" dir="rtl">
    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
        
        {{-- Header --}}
        <div class="text-center mb-8">
            <h1 class="text-3xl font-bold text-gray-900 mb-2">{{ $package['name'] }}</h1>
            <p class="text-lg text-gray-600">{{ $package['description'] }}</p>
        </div>

        <div class="grid lg:grid-cols-3 gap-8">
            
            {{-- Payment Form --}}
            <div class="lg:col-span-2">
                <div class="bg-white rounded-lg shadow-lg p-6">
                    
                    {{-- Progress Steps --}}
                    <div class="mb-8">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center text-blue-600">
                                <div class="w-8 h-8 bg-blue-600 text-white rounded-full flex items-center justify-center text-sm font-medium">1</div>
                                <span class="mr-2 text-sm font-medium">פרטי לקוח</span>
                            </div>
                            <div class="flex-1 h-0.5 bg-gray-200 mx-4"></div>
                            <div class="flex items-center text-gray-400">
                                <div class="w-8 h-8 bg-gray-200 text-gray-500 rounded-full flex items-center justify-center text-sm font-medium">2</div>
                                <span class="mr-2 text-sm font-medium">תשלום</span>
                            </div>
                            <div class="flex-1 h-0.5 bg-gray-200 mx-4"></div>
                            <div class="flex items-center text-gray-400">
                                <div class="w-8 h-8 bg-gray-200 text-gray-500 rounded-full flex items-center justify-center text-sm font-medium">3</div>
                                <span class="mr-2 text-sm font-medium">אישור</span>
                            </div>
                        </div>
                    </div>

                    {{-- Status Messages --}}
                    <div id="status-messages" class="mb-6"></div>

                    {{-- Main Form --}}
                    <form id="payment-form" class="space-y-6">
                        @csrf
                        
                        {{-- Customer Information --}}
                        <div class="bg-gray-50 p-4 rounded-lg">
                            <h3 class="text-lg font-semibold text-gray-900 mb-4">פרטי הלקוח</h3>
                            
                            <div class="grid md:grid-cols-2 gap-4">
                                <div>
                                    <label for="customer_name" class="block text-sm font-medium text-gray-700 mb-1">שם מלא *</label>
                                    <input type="text" id="customer_name" name="customer_name" 
                                           class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500"
                                           placeholder="הזינו שם מלא" required>
                                </div>
                                
                                <div>
                                    <label for="customer_email" class="block text-sm font-medium text-gray-700 mb-1">כתובת אימייל *</label>
                                    <input type="email" id="customer_email" name="customer_email" 
                                           class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500"
                                           placeholder="example@domain.com" required>
                                </div>
                                
                                <div>
                                    <label for="customer_phone" class="block text-sm font-medium text-gray-700 mb-1">מספר טלפון</label>
                                    <input type="tel" id="customer_phone" name="customer_phone" 
                                           class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500"
                                           placeholder="050-1234567">
                                </div>
                                
                                <div>
                                    <label for="amount" class="block text-sm font-medium text-gray-700 mb-1">סכום לתשלום *</label>
                                    <div class="relative">
                                        <input type="number" id="amount" name="amount" step="0.01" 
                                               min="{{ $package['min_amount'] ?? 1 }}" 
                                               max="{{ $package['max_amount'] ?? 10000 }}"
                                               class="w-full px-3 py-2 pl-12 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500"
                                               placeholder="0.00" required>
                                        <span class="absolute left-3 top-2 text-gray-500">₪</span>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="mt-4">
                                <label for="description" class="block text-sm font-medium text-gray-700 mb-1">הערות</label>
                                <textarea id="description" name="description" rows="2"
                                          class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500"
                                          placeholder="הערות נוספות (אופציונלי)"></textarea>
                            </div>
                        </div>

                        {{-- Payment Method Selection --}}
                        <div class="bg-gray-50 p-4 rounded-lg">
                            <h3 class="text-lg font-semibold text-gray-900 mb-4">אמצעי תשלום</h3>
                            
                            {{-- New Card Option --}}
                            <div class="mb-4">
                                <label class="flex items-center p-3 border border-gray-200 rounded-lg hover:bg-white cursor-pointer transition-colors">
                                    <input type="radio" name="payment_method_type" value="new_card" class="text-blue-600 focus:ring-blue-500" checked>
                                    <div class="mr-3 flex-1">
                                        <div class="flex items-center">
                                            <span class="font-medium text-gray-900">כרטיס אשראי חדש</span>
                                            <div class="mr-2 flex items-center space-x-1">
                                                <img src="{{ asset('images/cards/visa.png') }}" alt="Visa" class="h-6">
                                                <img src="{{ asset('images/cards/mastercard.png') }}" alt="MasterCard" class="h-6">
                                                <img src="{{ asset('images/cards/amex.png') }}" alt="American Express" class="h-6">
                                            </div>
                                        </div>
                                        <p class="text-sm text-gray-500">תשלום מאובטח עם CardCom</p>
                                    </div>
                                </label>
                            </div>
                            
                            {{-- Save Card Option --}}
                            @auth
                            <div class="mb-4 mr-8">
                                <label class="flex items-center">
                                    <input type="checkbox" name="save_payment_method" value="1" class="text-blue-600 focus:ring-blue-500 rounded">
                                    <span class="mr-2 text-sm text-gray-700">שמור כרטיס זה לתשלומים עתידיים</span>
                                </label>
                            </div>
                            @endauth

                            {{-- Saved Tokens --}}
                            @if(!empty($savedTokens))
                            <div class="space-y-2">
                                @foreach($savedTokens as $token)
                                <label class="flex items-center p-3 border border-gray-200 rounded-lg hover:bg-white cursor-pointer transition-colors">
                                    <input type="radio" name="payment_method_type" value="saved_token" 
                                           data-token-id="{{ $token['id'] }}" class="text-blue-600 focus:ring-blue-500">
                                    <div class="mr-3 flex-1">
                                        <div class="flex items-center justify-between">
                                            <span class="font-medium text-gray-900">{{ $token['display_name'] }}</span>
                                            @if($token['is_default'])
                                            <span class="text-xs bg-green-100 text-green-800 px-2 py-1 rounded">ברירת מחדל</span>
                                            @endif
                                        </div>
                                        <p class="text-sm text-gray-500">פג תוקף: {{ $token['expires_at'] }}</p>
                                    </div>
                                </label>
                                @endforeach
                            </div>
                            
                            {{-- CVV for Saved Token --}}
                            <div id="saved-token-cvv" class="mt-4 p-3 bg-yellow-50 border border-yellow-200 rounded-lg" style="display: none;">
                                <label for="cvv" class="block text-sm font-medium text-gray-700 mb-2">קוד CVV *</label>
                                <input type="text" id="cvv" name="cvv" maxlength="4" 
                                       class="w-24 px-3 py-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500"
                                       placeholder="123">
                                <p class="text-xs text-gray-500 mt-1">נדרש לאימות 3D Secure</p>
                            </div>
                            @endif
                        </div>

                        {{-- Submit Button --}}
                        <div class="text-center">
                            <button type="submit" id="submit-btn" 
                                    class="w-full bg-blue-600 hover:bg-blue-700 text-white font-semibold py-3 px-6 rounded-lg transition-colors disabled:opacity-50 disabled:cursor-not-allowed">
                                <span class="submit-text">המשך לתשלום מאובטח</span>
                                <span class="loading-text hidden">מעבד תשלום...</span>
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            {{-- Order Summary --}}
            <div class="lg:col-span-1">
                <div class="bg-white rounded-lg shadow-lg p-6 sticky top-8">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">סיכום הזמנה</h3>
                    
                    <div class="space-y-3">
                        <div class="flex justify-between">
                            <span class="text-gray-600">חבילה:</span>
                            <span class="font-medium">{{ $package['name'] }}</span>
                        </div>
                        
                        <div class="flex justify-between">
                            <span class="text-gray-600">מחיר:</span>
                            <span class="font-medium" id="price-display">₪ 0.00</span>
                        </div>
                        
                        @if($package['supports_3ds'] ?? false)
                        <div class="flex items-center text-green-600 text-sm">
                            <svg class="w-4 h-4 ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                            3D Secure מאובטח
                        </div>
                        @endif
                        
                        @if($package['supports_tokens'] ?? false)
                        <div class="flex items-center text-blue-600 text-sm">
                            <svg class="w-4 h-4 ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path>
                            </svg>
                            אפשרות לשמור כרטיס
                        </div>
                        @endif
                    </div>
                    
                    <div class="border-t border-gray-200 mt-4 pt-4">
                        <div class="flex justify-between text-lg font-semibold">
                            <span>סה"כ לתשלום:</span>
                            <span id="total-display">₪ 0.00</span>
                        </div>
                    </div>
                    
                    {{-- Security Badges --}}
                    <div class="mt-6 pt-4 border-t border-gray-200 text-center">
                        <p class="text-xs text-gray-500 mb-2">מאובטח על ידי</p>
                        <div class="flex items-center justify-center space-x-2">
                            <img src="{{ asset('images/security/cardcom.png') }}" alt="CardCom" class="h-6">
                            <img src="{{ asset('images/security/ssl.png') }}" alt="SSL" class="h-6">
                            <img src="{{ asset('images/security/pci.png') }}" alt="PCI DSS" class="h-6">
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- JavaScript --}}
@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('payment-form');
    const submitBtn = document.getElementById('submit-btn');
    const amountInput = document.getElementById('amount');
    const priceDisplay = document.getElementById('price-display');
    const totalDisplay = document.getElementById('total-display');
    const statusMessages = document.getElementById('status-messages');
    const paymentMethodInputs = document.querySelectorAll('input[name="payment_method_type"]');
    const savedTokenCvv = document.getElementById('saved-token-cvv');

    // Update price display
    function updatePriceDisplay() {
        const amount = parseFloat(amountInput.value) || 0;
        const formatted = new Intl.NumberFormat('he-IL', { 
            style: 'currency', 
            currency: 'ILS' 
        }).format(amount);
        
        priceDisplay.textContent = formatted;
        totalDisplay.textContent = formatted;
    }
    
    // Show status message
    function showStatus(message, type = 'info') {
        const alertClass = {
            'success': 'bg-green-50 text-green-800 border-green-200',
            'error': 'bg-red-50 text-red-800 border-red-200',
            'warning': 'bg-yellow-50 text-yellow-800 border-yellow-200',
            'info': 'bg-blue-50 text-blue-800 border-blue-200'
        }[type] || 'bg-gray-50 text-gray-800 border-gray-200';
        
        statusMessages.innerHTML = `
            <div class="${alertClass} border px-4 py-3 rounded-lg">
                ${message}
            </div>
        `;
        
        statusMessages.scrollIntoView({ behavior: 'smooth' });
    }
    
    // Toggle CVV field for saved tokens
    paymentMethodInputs.forEach(input => {
        input.addEventListener('change', function() {
            if (this.value === 'saved_token') {
                savedTokenCvv.style.display = 'block';
            } else {
                savedTokenCvv.style.display = 'none';
            }
        });
    });
    
    // Update price on amount change
    amountInput.addEventListener('input', updatePriceDisplay);
    updatePriceDisplay();
    
    // Form submission
    form.addEventListener('submit', async function(e) {
        e.preventDefault();
        
        if (submitBtn.disabled) return;
        
        // Show loading state
        submitBtn.disabled = true;
        submitBtn.querySelector('.submit-text').classList.add('hidden');
        submitBtn.querySelector('.loading-text').classList.remove('hidden');
        
        try {
            const formData = new FormData(form);
            const data = Object.fromEntries(formData);
            
            // Add selected token ID if saved token is selected
            const selectedToken = document.querySelector('input[name="payment_method_type"]:checked');
            if (selectedToken && selectedToken.value === 'saved_token') {
                data.saved_token_id = selectedToken.dataset.tokenId;
            }
            
            const response = await fetch(`{{ route('payment-gateway.process', $package['slug']) }}`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                },
                body: JSON.stringify(data)
            });
            
            const result = await response.json();
            
            if (result.success) {
                if (result.requires_3ds && result.three_ds_url) {
                    showStatus('מעבר לאימות 3D Secure...', 'info');
                    setTimeout(() => {
                        window.location.href = result.three_ds_url;
                    }, 1000);
                } else if (result.checkout_url) {
                    showStatus('מעבר לעמוד התשלום המאובטח...', 'info');
                    setTimeout(() => {
                        window.location.href = result.checkout_url;
                    }, 1000);
                } else if (result.payment_completed) {
                    showStatus('התשלום הושלם בהצלחה!', 'success');
                    setTimeout(() => {
                        window.location.href = result.redirect_url || '{{ route("payment-gateway.success") }}';
                    }, 2000);
                } else {
                    showStatus(result.message || 'התשלום עובד', 'success');
                }
            } else {
                showStatus(result.message || result.error || 'שגיאה בעיבוד התשלום', 'error');
            }
            
        } catch (error) {
            console.error('Payment error:', error);
            showStatus('שגיאת חיבור לשרת. אנא נסו שוב.', 'error');
        } finally {
            // Reset loading state
            submitBtn.disabled = false;
            submitBtn.querySelector('.submit-text').classList.remove('hidden');
            submitBtn.querySelector('.loading-text').classList.add('hidden');
        }
    });
});
</script>
@endpush
@endsection