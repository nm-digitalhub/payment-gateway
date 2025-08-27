{{-- דף קטלוג החבילות עם Slugs --}}
<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" dir="{{ app()->isLocale('he') ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ config('app.name', 'Payment Gateway') }} - קטלוג חבילות</title>
    
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
            <div class="container mx-auto px-4 py-6">
                <div class="flex justify-between items-center">
                    <h1 class="text-3xl font-bold text-gray-900">
                        🌐 קטלוג חבילות ושירותים
                    </h1>
                    
                    <div class="flex items-center space-x-4">
                        <div class="provider-status" id="sync-status">
                            <span class="status-dot bg-yellow-400"></span>
                            מעדכן נתונים...
                        </div>
                        
                        <button 
                            onclick="PaymentGateway.PackageManager.syncAll()" 
                            class="payment-button success"
                            id="sync-all-btn">
                            🔄 סנכרן הכל
                        </button>
                    </div>
                </div>
            </div>
        </header>

        {{-- Filters --}}
        <section class="bg-white border-b">
            <div class="container mx-auto px-4 py-4">
                <div class="flex flex-wrap gap-4 items-center">
                    <div class="flex-1 min-w-64">
                        <input 
                            type="text" 
                            id="search-packages" 
                            placeholder="🔍 חפש חבילות..."
                            class="form-input w-full"
                            onkeyup="PaymentGateway.PackageManager.filterPackages()">
                    </div>
                    
                    <select id="provider-filter" class="form-select" onchange="PaymentGateway.PackageManager.filterPackages()">
                        <option value="">כל הספקים</option>
                        <option value="cardcom">💳 CardCom</option>
                        <option value="maya-mobile">📱 Maya Mobile</option>
                        <option value="resellerclub">🌐 ResellerClub</option>
                    </select>
                    
                    <select id="category-filter" class="form-select" onchange="PaymentGateway.PackageManager.filterPackages()">
                        <option value="">כל הקטגוריות</option>
                        <option value="domain">🌐 דומיינים</option>
                        <option value="hosting">🖥️ אירוח</option>
                        <option value="esim">📱 eSIM</option>
                        <option value="ssl">🔒 SSL</option>
                        <option value="email">📧 דואר</option>
                    </select>
                    
                    <select id="sort-filter" class="form-select" onchange="PaymentGateway.PackageManager.sortPackages()">
                        <option value="name">מיון לפי שם</option>
                        <option value="price-low">מחיר: נמוך לגבוה</option>
                        <option value="price-high">מחיר: גבוה לנמוך</option>
                        <option value="provider">לפי ספק</option>
                        <option value="updated">עודכן לאחרונה</option>
                    </select>
                </div>
            </div>
        </section>

        {{-- Stats Bar --}}
        <section class="bg-blue-50 border-b">
            <div class="container mx-auto px-4 py-3">
                <div class="flex justify-between items-center text-sm text-blue-800">
                    <div id="packages-stats">
                        📊 נטען...
                    </div>
                    
                    <div class="flex space-x-4">
                        <span>עדכון אחרון: <span id="last-sync">לא ידוע</span></span>
                        <span>|</span>
                        <span>חיבור: <span id="connection-status" class="font-semibold">בודק...</span></span>
                    </div>
                </div>
            </div>
        </section>

        {{-- Main Content --}}
        <main class="container mx-auto px-4 py-8">
            {{-- Loading State --}}
            <div id="loading-state" class="text-center py-12">
                <div class="animate-spin inline-block w-8 h-8 border-4 border-blue-500 border-t-transparent rounded-full mb-4"></div>
                <p class="text-gray-600">טוען חבילות...</p>
            </div>

            {{-- Error State --}}
            <div id="error-state" class="hidden bg-red-50 border border-red-200 rounded-lg p-6 text-center">
                <div class="text-red-600 text-lg mb-2">❌</div>
                <h3 class="text-red-800 font-semibold mb-2">שגיאה בטעינת החבילות</h3>
                <p class="text-red-700 mb-4" id="error-message"></p>
                <button onclick="PaymentGateway.PackageManager.loadPackages()" class="payment-button error">
                    נסה שוב
                </button>
            </div>

            {{-- No Results State --}}
            <div id="no-results-state" class="hidden text-center py-12">
                <div class="text-6xl mb-4">🔍</div>
                <h3 class="text-xl font-semibold text-gray-700 mb-2">לא נמצאו חבילות</h3>
                <p class="text-gray-600 mb-4">נסה לשנות את הפילטרים או המילים לחיפוש</p>
                <button onclick="PaymentGateway.PackageManager.clearFilters()" class="payment-button secondary">
                    נקה פילטרים
                </button>
            </div>

            {{-- Packages Grid --}}
            <div id="packages-container" class="hidden">
                <div id="packages-grid" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">
                    {{-- Packages will be loaded here via JavaScript --}}
                </div>

                {{-- Load More Button --}}
                <div class="text-center mt-8">
                    <button id="load-more-btn" class="payment-button secondary" onclick="PaymentGateway.PackageManager.loadMore()">
                        טען עוד חבילות
                    </button>
                </div>
            </div>
        </main>

        {{-- Package Template --}}
        <template id="package-card-template">
            <div class="package-card bg-white rounded-lg shadow-sm border hover:shadow-md transition-shadow duration-200">
                <div class="p-6">
                    {{-- Provider Badge --}}
                    <div class="flex justify-between items-start mb-3">
                        <span class="provider-badge inline-flex items-center px-2 py-1 rounded-full text-xs font-medium">
                            <span class="provider-icon mr-1"></span>
                            <span class="provider-name"></span>
                        </span>
                        <span class="package-category text-xs text-gray-500"></span>
                    </div>

                    {{-- Package Info --}}
                    <h3 class="package-name text-lg font-semibold text-gray-900 mb-2"></h3>
                    <p class="package-description text-sm text-gray-600 mb-4 line-clamp-2"></p>

                    {{-- Features --}}
                    <div class="package-features space-y-1 mb-4">
                        {{-- Features will be populated here --}}
                    </div>

                    {{-- Pricing --}}
                    <div class="package-pricing mb-4">
                        <div class="flex items-baseline justify-between">
                            <div>
                                <span class="package-price text-2xl font-bold text-gray-900"></span>
                                <span class="package-currency text-sm text-gray-500"></span>
                                <span class="package-period text-sm text-gray-500"></span>
                            </div>
                            <div class="package-discount hidden">
                                <span class="original-price line-through text-sm text-gray-400"></span>
                                <span class="discount-badge bg-green-100 text-green-800 text-xs px-2 py-1 rounded-full"></span>
                            </div>
                        </div>
                    </div>

                    {{-- Actions --}}
                    <div class="flex space-x-2">
                        <button class="package-select-btn payment-button primary flex-1" onclick="selectPackage(this)">
                            בחר חבילה
                        </button>
                        <button class="package-details-btn payment-button secondary" onclick="viewPackageDetails(this)">
                            פרטים
                        </button>
                    </div>
                </div>

                {{-- Package Metadata (hidden) --}}
                <div class="package-metadata hidden">
                    <span class="package-id"></span>
                    <span class="package-slug"></span>
                    <span class="package-provider"></span>
                    <span class="package-raw-data"></span>
                </div>
            </div>
        </template>

        {{-- Package Details Modal --}}
        <div id="package-modal" class="fixed inset-0 bg-black bg-opacity-50 hidden z-50">
            <div class="flex items-center justify-center min-h-screen p-4">
                <div class="bg-white rounded-lg max-w-2xl w-full max-h-90vh overflow-y-auto">
                    <div class="p-6">
                        <div class="flex justify-between items-start mb-4">
                            <h2 id="modal-title" class="text-2xl font-bold text-gray-900"></h2>
                            <button onclick="closePackageModal()" class="text-gray-500 hover:text-gray-700">
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                                </svg>
                            </button>
                        </div>
                        
                        <div id="modal-content">
                            {{-- Content will be populated via JavaScript --}}
                        </div>
                        
                        <div class="flex space-x-2 mt-6">
                            <button id="modal-select-btn" class="payment-button primary flex-1" onclick="selectPackageFromModal()">
                                בחר חבילה זו
                            </button>
                            <button onclick="closePackageModal()" class="payment-button secondary">
                                סגור
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- JavaScript --}}
    <script src="{{ asset('vendor/payment-gateway/js/payment-gateway-core.js') }}"></script>
    <script src="{{ asset('vendor/payment-gateway/js/modules/form-handler.js') }}"></script>
    <script src="{{ asset('vendor/payment-gateway/js/modules/connection-tester.js') }}"></script>
    <script src="{{ asset('vendor/payment-gateway/js/modules/package-manager.js') }}"></script>

    <script>
        // Package Management Functions
        function selectPackage(button) {
            const card = button.closest('.package-card');
            const packageId = card.querySelector('.package-id').textContent;
            const packageSlug = card.querySelector('.package-slug').textContent;
            
            if (packageSlug) {
                // Navigate to checkout with slug
                window.location.href = `/checkout/${packageSlug}`;
            } else {
                // Fallback to ID
                window.location.href = `/checkout?package=${packageId}`;
            }
        }

        function viewPackageDetails(button) {
            const card = button.closest('.package-card');
            const packageId = card.querySelector('.package-id').textContent;
            const rawData = JSON.parse(card.querySelector('.package-raw-data').textContent || '{}');
            
            PaymentGateway.PackageManager.showPackageDetails(packageId, rawData);
        }

        function selectPackageFromModal() {
            const packageId = document.getElementById('modal-select-btn').dataset.packageId;
            const packageSlug = document.getElementById('modal-select-btn').dataset.packageSlug;
            
            if (packageSlug) {
                window.location.href = `/checkout/${packageSlug}`;
            } else {
                window.location.href = `/checkout?package=${packageId}`;
            }
        }

        function closePackageModal() {
            document.getElementById('package-modal').classList.add('hidden');
        }

        // Initialize when DOM is ready
        document.addEventListener('DOMContentLoaded', function() {
            // Auto-initialize PaymentGateway
            PaymentGateway.init({
                debug: {{ config('app.debug', false) ? 'true' : 'false' }},
                locale: '{{ app()->getLocale() }}',
                currency: 'ILS'
            });

            // Load packages
            PaymentGateway.PackageManager.loadPackages();
            
            // Setup periodic sync (every 5 minutes)
            setInterval(() => {
                PaymentGateway.PackageManager.syncAll();
            }, 5 * 60 * 1000);
        });
    </script>
</body>
</html>