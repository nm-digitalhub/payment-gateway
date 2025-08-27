{{-- דף Checkout עם תמיכה ב-Slug --}}
<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" dir="{{ app()->isLocale('he') ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ config('app.name', 'Payment Gateway') }} - תשלום עבור {{ $package['name'] ?? 'חבילה' }}</title>
    
    {{-- CSS --}}
    <link href="{{ asset('vendor/payment-gateway/css/payment-gateway.css') }}" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    
    {{-- RTL Support --}}
    @if(app()->isLocale('he'))
    <style>
        body { direction: rtl; }
        .container { text-align: right; }
    </style>
    @endif
</head>
<body class="bg-gray-50">
    <div class="payment-gateway rtl">
        {{-- Header --}}
        <header class="bg-white shadow-sm border-b">
            <div class="container mx-auto px-4 py-4">
                <div class="flex justify-between items-center">
                    <a href="/packages" class="flex items-center text-blue-600 hover:text-blue-800">
                        <svg class="w-5 h-5 ml-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                        </svg>
                        חזור לקטלוג
                    </a>
                    
                    <h1 class="text-2xl font-bold text-gray-900">
                        💳 אזור תשלום מאובטח
                    </h1>
                    
                    <div class="w-20"></div> {{-- Spacer --}}
                </div>
            </div>
        </header>

        {{-- Progress Bar --}}
        <div class="bg-white border-b">
            <div class="container mx-auto px-4 py-4">
                <div class="payment-progress">
                    <div class="payment-step active">
                        <div class="payment-step-circle">1</div>
                        <div class="payment-step-label">בחירת חבילה</div>
                    </div>
                    <div class="payment-step active" id="step-details">
                        <div class="payment-step-circle">2</div>
                        <div class="payment-step-label">פרטי הזמנה</div>
                    </div>
                    <div class="payment-step" id="step-payment">
                        <div class="payment-step-circle">3</div>
                        <div class="payment-step-label">תשלום</div>
                    </div>
                    <div class="payment-step" id="step-confirmation">
                        <div class="payment-step-circle">4</div>
                        <div class="payment-step-label">אישור</div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Main Content --}}
        <main class="container mx-auto px-4 py-8">
            <div class="max-w-6xl mx-auto">
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
                    {{-- Order Summary --}}
                    <div class="lg:col-span-1 order-2 lg:order-1">
                        <div class="bg-white rounded-lg shadow-sm border p-6 sticky top-8">
                            <h2 class="text-xl font-semibold text-gray-900 mb-4">
                                📋 סיכום הזמנה
                            </h2>

                            {{-- Package Details --}}
                            <div id="package-summary">
                                <div class="flex items-start space-x-3 mb-4">
                                    <div class="package-icon text-2xl">
                                        {{ $package['icon'] ?? '📦' }}
                                    </div>
                                    <div class="flex-1">
                                        <h3 class="font-medium text-gray-900" id="package-name">
                                            {{ $package['name'] ?? 'טוען...' }}
                                        </h3>
                                        <p class="text-sm text-gray-600" id="package-provider">
                                            {{ $package['provider_name'] ?? '' }}
                                        </p>
                                        <p class="text-xs text-gray-500" id="package-description">
                                            {{ Str::limit($package['description'] ?? '', 100) }}
                                        </p>
                                    </div>
                                </div>

                                {{-- Package Features --}}
                                <div class="mb-4" id="package-features">
                                    @if(isset($package['features']) && is_array($package['features']))
                                        <ul class="text-sm text-gray-600 space-y-1">
                                            @foreach(array_slice($package['features'], 0, 3) as $feature)
                                            <li class="flex items-center">
                                                <span class="text-green-500 ml-2">✓</span>
                                                {{ $feature }}
                                            </li>
                                            @endforeach
                                        </ul>
                                    @endif
                                </div>

                                {{-- Pricing --}}
                                <div class="border-t pt-4">
                                    <div class="flex justify-between text-sm mb-2">
                                        <span>מחיר בסיס:</span>
                                        <span id="base-price">
                                            {{ number_format($package['price'] ?? 0, 2) }} {{ $package['currency'] ?? 'ILS' }}
                                        </span>
                                    </div>
                                    
                                    <div class="flex justify-between text-sm mb-2" id="setup-fee-row" style="display: none;">
                                        <span>עלות הקמה:</span>
                                        <span id="setup-fee">0.00 ILS</span>
                                    </div>
                                    
                                    <div class="flex justify-between text-sm mb-2" id="discount-row" style="display: none;">
                                        <span class="text-green-600">הנחה:</span>
                                        <span class="text-green-600" id="discount-amount">-0.00 ILS</span>
                                    </div>
                                    
                                    <div class="flex justify-between text-sm mb-2">
                                        <span>מע"מ (17%):</span>
                                        <span id="tax-amount">0.00 ILS</span>
                                    </div>
                                    
                                    <hr class="my-3">
                                    
                                    <div class="flex justify-between font-semibold text-lg">
                                        <span>סה"כ לתשלום:</span>
                                        <span id="total-amount" class="text-blue-600">
                                            {{ number_format(($package['price'] ?? 0) * 1.17, 2) }} ILS
                                        </span>
                                    </div>
                                </div>
                            </div>

                            {{-- Security Badges --}}
                            <div class="mt-6 pt-4 border-t">
                                <div class="flex items-center justify-center space-x-4 text-xs text-gray-500">
                                    <div class="flex items-center">
                                        <svg class="w-4 h-4 text-green-500 ml-1" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M5 9V7a5 5 0 0110 0v2a2 2 0 012 2v5a2 2 0 01-2 2H5a2 2 0 01-2-2v-5a2 2 0 012-2zm8-2v2H7V7a3 3 0 016 0z" clip-rule="evenodd"/>
                                        </svg>
                                        SSL מאובטח
                                    </div>
                                    <div class="flex items-center">
                                        <svg class="w-4 h-4 text-blue-500 ml-1" fill="currentColor" viewBox="0 0 20 20">
                                            <path d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                        </svg>
                                        PCI DSS
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- Checkout Form --}}
                    <div class="lg:col-span-2 order-1 lg:order-2">
                        <form id="checkout-form" class="payment-gateway-form bg-white rounded-lg shadow-sm border p-8" data-form-type="checkout">
                            {{-- Customer Information --}}
                            <section class="mb-8">
                                <h2 class="text-xl font-semibold text-gray-900 mb-6 flex items-center">
                                    <svg class="w-6 h-6 text-blue-600 ml-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                                    </svg>
                                    פרטים אישיים
                                </h2>

                                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                    <div class="payment-field">
                                        <label for="customer_name" class="form-label required">שם מלא *</label>
                                        <input type="text" id="customer_name" name="customer_name" class="form-input" required>
                                    </div>

                                    <div class="payment-field">
                                        <label for="customer_email" class="form-label required">דואר אלקטרוני *</label>
                                        <input type="email" id="customer_email" name="customer_email" class="form-input" required>
                                    </div>

                                    <div class="payment-field">
                                        <label for="customer_phone" class="form-label required">טלפון *</label>
                                        <input type="tel" id="customer_phone" name="customer_phone" class="form-input" required>
                                    </div>

                                    <div class="payment-field">
                                        <label for="customer_id" class="form-label">תעודת זהות</label>
                                        <input type="text" id="customer_id" name="customer_id" class="form-input" pattern="[0-9]{9}">
                                    </div>
                                </div>

                                <div class="payment-field mt-6">
                                    <label for="billing_address" class="form-label">כתובת לחיוב</label>
                                    <textarea id="billing_address" name="billing_address" rows="2" class="form-input" placeholder="כתובת מלאה כולל עיר ומיקוד"></textarea>
                                </div>
                            </section>

                            {{-- Service Configuration (if needed) --}}
                            <section id="service-configuration" class="mb-8" style="display: none;">
                                <h2 class="text-xl font-semibold text-gray-900 mb-6 flex items-center">
                                    <svg class="w-6 h-6 text-purple-600 ml-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/>
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                    </svg>
                                    הגדרות השירות
                                </h2>

                                <div id="service-fields">
                                    {{-- Dynamic fields will be populated here based on package type --}}
                                </div>
                            </section>

                            {{-- Payment Method --}}
                            <section class="mb-8">
                                <h2 class="text-xl font-semibold text-gray-900 mb-6 flex items-center">
                                    <svg class="w-6 h-6 text-green-600 ml-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"/>
                                    </svg>
                                    אמצעי תשלום
                                </h2>

                                <div class="payment-methods">
                                    <label class="payment-method selected">
                                        <input type="radio" name="payment_method" value="cardcom" checked>
                                        <div class="method-icon">💳</div>
                                        <div class="method-name">CardCom</div>
                                    </label>
                                </div>

                                {{-- CardCom Payment Fields --}}
                                <div id="cardcom-fields" class="card-form mt-6">
                                    <div class="mb-4">
                                        <p class="text-sm text-gray-600 mb-4">
                                            🔒 פרטי האשראי מוצפנים ומאובטחים על ידי CardCom
                                        </p>
                                    </div>

                                    {{-- CardCom iframe will be loaded here --}}
                                    <div id="cardcom-container">
                                        <div class="cardcom-loading">
                                            <div class="animate-spin inline-block w-6 h-6 border-4 border-blue-500 border-t-transparent rounded-full ml-2"></div>
                                            טוען אמצעי תשלום מאובטח...
                                        </div>
                                    </div>
                                </div>
                            </section>

                            {{-- Terms and Conditions --}}
                            <section class="mb-8">
                                <div class="bg-gray-50 rounded-lg p-4">
                                    <label class="flex items-start">
                                        <input type="checkbox" id="terms_accepted" name="terms_accepted" class="mt-1 rounded border-gray-300 text-blue-600" required>
                                        <span class="text-sm text-gray-700 mr-3">
                                            אני מאשר/ת שקראתי והבנתי את 
                                            <a href="/terms" target="_blank" class="text-blue-600 hover:underline">התנאים והתקנון</a>
                                            ואת 
                                            <a href="/privacy" target="_blank" class="text-blue-600 hover:underline">מדיניות הפרטיות</a>
                                            של האתר *
                                        </span>
                                    </label>
                                </div>
                            </section>

                            {{-- Submit Button --}}
                            <div class="text-center">
                                <button type="submit" class="payment-button primary" id="submit-btn">
                                    <svg class="w-5 h-5 inline-block ml-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2-2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                                    </svg>
                                    השלם תשלום מאובטח
                                </button>
                                
                                <p class="text-xs text-gray-500 mt-3">
                                    🔒 כל המידע מוצפן ומאובטח. לא נשמר מידע כרטיס אשראי באתר.
                                </p>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </main>
    </div>

    {{-- Hidden Package Data --}}
    <script id="package-data" type="application/json">
        @json($package ?? [])
    </script>

    {{-- JavaScript --}}
    <script src="{{ asset('vendor/payment-gateway/js/payment-gateway-core.js') }}"></script>
    <script src="{{ asset('vendor/payment-gateway/js/modules/form-handler.js') }}"></script>
    <script src="{{ asset('vendor/payment-gateway/js/modules/connection-tester.js') }}"></script>
    <script src="{{ asset('vendor/payment-gateway/js/modules/package-manager.js') }}"></script>

    <script>
        // Checkout specific functionality
        class CheckoutManager {
            constructor() {
                this.packageData = null;
                this.paymentMethod = 'cardcom';
                this.init();
            }

            init() {
                this.loadPackageData();
                this.bindEvents();
                this.calculateTotal();
                this.loadPaymentFields();
            }

            loadPackageData() {
                try {
                    const packageScript = document.getElementById('package-data');
                    this.packageData = JSON.parse(packageScript.textContent);
                    
                    if (this.packageData) {
                        this.updatePackageDisplay();
                        this.setupServiceFields();
                    }
                } catch (error) {
                    console.error('Failed to load package data:', error);
                    PaymentGateway.utils.showMessage('שגיאה בטעינת נתוני החבילה', 'error');
                }
            }

            updatePackageDisplay() {
                if (!this.packageData) return;

                // Update elements if they exist
                const elements = {
                    'package-name': this.packageData.name || 'ללא שם',
                    'package-provider': this.getProviderName(this.packageData.provider),
                    'package-description': this.packageData.description || ''
                };

                Object.entries(elements).forEach(([id, value]) => {
                    const element = document.getElementById(id);
                    if (element) element.textContent = value;
                });

                // Update features
                if (this.packageData.features) {
                    const featuresContainer = document.getElementById('package-features');
                    if (featuresContainer) {
                        featuresContainer.innerHTML = this.packageData.features
                            .slice(0, 3)
                            .map(feature => `
                                <li class="flex items-center text-sm text-gray-600">
                                    <span class="text-green-500 ml-2">✓</span>
                                    ${feature}
                                </li>
                            `).join('');
                    }
                }
            }

            setupServiceFields() {
                if (!this.packageData) return;

                const category = this.packageData.category;
                const serviceSection = document.getElementById('service-configuration');
                const serviceFields = document.getElementById('service-fields');

                if (!serviceFields) return;

                let fieldsHtml = '';

                switch (category) {
                    case 'domain':
                        fieldsHtml = this.createDomainFields();
                        break;
                    case 'hosting':
                        fieldsHtml = this.createHostingFields();
                        break;
                    case 'esim':
                        fieldsHtml = this.createEsimFields();
                        break;
                }

                if (fieldsHtml) {
                    serviceFields.innerHTML = fieldsHtml;
                    serviceSection.style.display = 'block';
                }
            }

            createDomainFields() {
                return `
                    <div class="payment-field">
                        <label for="domain_name" class="form-label required">שם הדומיין *</label>
                        <div class="flex">
                            <input type="text" id="domain_name" name="domain_name" class="form-input rounded-r-none" placeholder="example" required>
                            <select name="domain_extension" class="form-select w-32 rounded-l-none border-r-0">
                                <option value=".com">.com</option>
                                <option value=".co.il">.co.il</option>
                                <option value=".org.il">.org.il</option>
                                <option value=".net">.net</option>
                                <option value=".org">.org</option>
                            </select>
                        </div>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div class="payment-field">
                            <label class="flex items-center">
                                <input type="checkbox" name="privacy_protection" class="rounded border-gray-300 text-blue-600">
                                <span class="text-sm text-gray-700 mr-2">הגנת פרטיות (+₪20)</span>
                            </label>
                        </div>
                        <div class="payment-field">
                            <label for="nameservers" class="form-label">שרתי DNS</label>
                            <input type="text" id="nameservers" name="nameservers" class="form-input" placeholder="ns1.example.com, ns2.example.com">
                        </div>
                    </div>
                `;
            }

            createHostingFields() {
                return `
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div class="payment-field">
                            <label for="site_name" class="form-label required">שם האתר *</label>
                            <input type="text" id="site_name" name="site_name" class="form-input" required>
                        </div>
                        <div class="payment-field">
                            <label for="admin_email" class="form-label required">דואר מנהל *</label>
                            <input type="email" id="admin_email" name="admin_email" class="form-input" required>
                        </div>
                    </div>
                `;
            }

            createEsimFields() {
                return `
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div class="payment-field">
                            <label for="destination_country" class="form-label required">מדינת יעד *</label>
                            <select id="destination_country" name="destination_country" class="form-input" required>
                                <option value="">בחר מדינה</option>
                                <option value="US">ארצות הברית</option>
                                <option value="GB">בריטניה</option>
                                <option value="FR">צרפת</option>
                                <option value="DE">גרמניה</option>
                                <option value="ES">ספרד</option>
                                <option value="IT">איטליה</option>
                                <option value="JP">יפן</option>
                                <option value="AU">אוסטרליה</option>
                            </select>
                        </div>
                        <div class="payment-field">
                            <label for="travel_dates" class="form-label">תאריכי נסיעה</label>
                            <input type="text" id="travel_dates" name="travel_dates" class="form-input" placeholder="dd/mm/yyyy - dd/mm/yyyy">
                        </div>
                    </div>
                `;
            }

            calculateTotal() {
                if (!this.packageData) return;

                const basePrice = parseFloat(this.packageData.price || 0);
                const setupFee = parseFloat(this.packageData.setup_fee || 0);
                let discount = 0;

                // Calculate discount if exists
                if (this.packageData.discount_price && this.packageData.discount_price < basePrice) {
                    discount = basePrice - this.packageData.discount_price;
                }

                const subtotal = (basePrice - discount + setupFee);
                const tax = subtotal * 0.17; // 17% VAT
                const total = subtotal + tax;

                // Update display
                this.updatePriceDisplay('base-price', basePrice, this.packageData.currency);
                this.updatePriceDisplay('tax-amount', tax, 'ILS');
                this.updatePriceDisplay('total-amount', total, 'ILS');

                if (setupFee > 0) {
                    this.updatePriceDisplay('setup-fee', setupFee, this.packageData.currency);
                    document.getElementById('setup-fee-row').style.display = 'flex';
                }

                if (discount > 0) {
                    this.updatePriceDisplay('discount-amount', -discount, this.packageData.currency);
                    document.getElementById('discount-row').style.display = 'flex';
                }
            }

            updatePriceDisplay(elementId, amount, currency = 'ILS') {
                const element = document.getElementById(elementId);
                if (element) {
                    const formatted = PaymentGateway.utils.formatPrice ? 
                        PaymentGateway.utils.formatPrice(amount, currency) :
                        `${amount.toFixed(2)} ${currency}`;
                    element.textContent = formatted;
                }
            }

            loadPaymentFields() {
                if (this.paymentMethod === 'cardcom') {
                    this.loadCardComFields();
                }
            }

            loadCardComFields() {
                // Initialize CardCom payment fields
                const container = document.getElementById('cardcom-container');
                if (container) {
                    // This would typically load CardCom's iframe
                    setTimeout(() => {
                        container.innerHTML = `
                            <div class="space-y-4">
                                <div class="payment-field">
                                    <label for="card_number" class="form-label required">מספר כרטיס אשראי *</label>
                                    <input type="text" id="card_number" name="card_number" class="form-input" placeholder="1234 5678 9012 3456" required>
                                </div>
                                <div class="grid grid-cols-2 gap-4">
                                    <div class="payment-field">
                                        <label for="card_expiry" class="form-label required">תוקף *</label>
                                        <input type="text" id="card_expiry" name="card_expiry" class="form-input" placeholder="MM/YY" required>
                                    </div>
                                    <div class="payment-field">
                                        <label for="card_cvv" class="form-label required">CVV *</label>
                                        <input type="text" id="card_cvv" name="card_cvv" class="form-input" placeholder="123" required>
                                    </div>
                                </div>
                                <div class="payment-field">
                                    <label for="card_holder" class="form-label required">שם בעל הכרטיס *</label>
                                    <input type="text" id="card_holder" name="card_holder" class="form-input" placeholder="כפי שמופיע על הכרטיס" required>
                                </div>
                            </div>
                        `;
                    }, 1000);
                }
            }

            bindEvents() {
                // Form submission
                const form = document.getElementById('checkout-form');
                if (form) {
                    form.addEventListener('submit', (e) => {
                        e.preventDefault();
                        this.processCheckout();
                    });
                }

                // Payment method change
                const paymentMethods = document.querySelectorAll('input[name="payment_method"]');
                paymentMethods.forEach(method => {
                    method.addEventListener('change', (e) => {
                        this.paymentMethod = e.target.value;
                        this.loadPaymentFields();
                    });
                });

                // Form validation
                this.addFormValidation();
            }

            addFormValidation() {
                // Israeli ID validation
                const idField = document.getElementById('customer_id');
                if (idField) {
                    idField.addEventListener('blur', (e) => {
                        if (e.target.value && !PaymentGateway.utils.validateIsraeliID(e.target.value)) {
                            PaymentGateway.utils.showMessage('מספר תעודת זהות אינו תקין', 'error');
                        }
                    });
                }

                // Phone validation
                const phoneField = document.getElementById('customer_phone');
                if (phoneField) {
                    phoneField.addEventListener('blur', (e) => {
                        if (e.target.value && !PaymentGateway.utils.validateIsraeliPhone(e.target.value)) {
                            PaymentGateway.utils.showMessage('מספר טלפון אינו תקין', 'error');
                        }
                    });
                }
            }

            processCheckout() {
                const form = document.getElementById('checkout-form');
                const submitBtn = document.getElementById('submit-btn');

                if (!form || !submitBtn) return;

                // Show loading state
                submitBtn.disabled = true;
                submitBtn.innerHTML = `
                    <svg class="animate-spin w-5 h-5 inline-block ml-2" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    מעבד תשלום...
                `;

                // Collect form data
                const formData = new FormData(form);
                const checkoutData = {
                    package_slug: '{{ $package["slug"] ?? "" }}',
                    package_id: '{{ $package["id"] ?? "" }}',
                    payment_method: this.paymentMethod,
                    ...Object.fromEntries(formData)
                };

                // Process payment
                PaymentGateway.apiRequest('process-checkout', {
                    method: 'POST',
                    body: JSON.stringify(checkoutData)
                })
                .then(response => {
                    if (response.success) {
                        if (response.redirect_url) {
                            window.location.href = response.redirect_url;
                        } else {
                            PaymentGateway.utils.showMessage('תשלום הושלם בהצלחה!', 'success');
                            // Redirect to success page
                            setTimeout(() => {
                                window.location.href = '/checkout/success';
                            }, 2000);
                        }
                    } else {
                        PaymentGateway.utils.showMessage(response.message || 'שגיאה בעיבוד התשלום', 'error');
                    }
                })
                .catch(error => {
                    PaymentGateway.utils.showMessage('שגיאה בתקשורת עם השרת', 'error');
                    console.error('Checkout error:', error);
                })
                .finally(() => {
                    // Reset button
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = `
                        <svg class="w-5 h-5 inline-block ml-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2-2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                        </svg>
                        השלם תשלום מאובטח
                    `;
                });
            }

            getProviderName(provider) {
                const names = {
                    'cardcom': 'CardCom',
                    'maya-mobile': 'Maya Mobile',
                    'resellerclub': 'ResellerClub'
                };
                return names[provider] || provider;
            }
        }

        // Initialize when DOM is ready
        document.addEventListener('DOMContentLoaded', function() {
            // Auto-initialize PaymentGateway
            PaymentGateway.init({
                debug: {{ config('app.debug', false) ? 'true' : 'false' }},
                locale: '{{ app()->getLocale() }}',
                currency: 'ILS'
            });

            // Initialize checkout manager
            new CheckoutManager();
        });
    </script>
</body>
</html>