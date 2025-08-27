/**
 * Package Manager Module for Payment Gateway
 * מודול לניהול חבילות וסינכרון קטלוג - מבוסס על מערכת eSIM
 * 
 * תכונות חדשות:
 * - Slug-based navigation (כמו /esim-packages/{slug})
 * - זרימה מלאה מקטלוג לרכישה
 * - סנכרון אוטומטי עם Maya Mobile, ResellerClub, CardCom
 * - תמיכה בקופונים ומבצעים
 * - ניתוב דינמי לדפי checkout עם slug
 */

(function(window) {
    'use strict';

    const PackageManager = {
        gateway: null,
        packages: [],
        filteredPackages: [],
        currentPage: 1,
        packagesPerPage: 12,
        isLoading: false,
        lastSync: null,

        /**
         * אתחול המודול
         */
        init: function(gateway) {
            this.gateway = gateway;
            this.bindEvents();
            this.loadFromCache();
        },

        /**
         * טעינת חבילות מהשרת
         */
        loadPackages: function(showLoading = true) {
            if (this.isLoading) return;

            this.isLoading = true;
            
            if (showLoading) {
                this.showLoadingState();
            }

            return this.gateway.apiRequest('packages', { method: 'GET' })
                .then(response => {
                    this.isLoading = false;
                    
                    if (response.success) {
                        this.packages = response.packages || [];
                        this.filteredPackages = [...this.packages];
                        this.lastSync = new Date();
                        
                        this.saveToCache();
                        this.renderPackages();
                        this.updateStats();
                        this.updateSyncStatus('success');
                        
                        this.gateway.trigger('packagesLoaded', {
                            packages: this.packages,
                            count: this.packages.length
                        });
                    } else {
                        this.showErrorState(response.message || 'שגיאה בטעינת החבילות');
                    }
                })
                .catch(error => {
                    this.isLoading = false;
                    this.showErrorState(error.message);
                    this.gateway.handleError('Load Packages', error);
                });
        },

        /**
         * סינכרון חבילות מכל הספקים
         */
        syncAll: function() {
            if (this.isLoading) {
                this.gateway.utils.showMessage('סינכרון כבר מתבצע', 'warning');
                return;
            }

            this.updateSyncStatus('syncing');
            
            return this.gateway.apiRequest('sync-all-packages', {
                method: 'POST'
            })
            .then(response => {
                if (response.success) {
                    this.gateway.utils.showMessage(
                        `✅ סינכרון הושלם! עודכנו ${response.synced || 0} חבילות`, 
                        'success'
                    );
                    
                    // טען את החבילות המעודכנות
                    this.loadPackages(false);
                } else {
                    this.gateway.utils.showMessage(
                        response.message || 'שגיאה בסינכרון', 
                        'error'
                    );
                    this.updateSyncStatus('error');
                }
            })
            .catch(error => {
                this.gateway.utils.showMessage('שגיאה בסינכרון חבילות', 'error');
                this.updateSyncStatus('error');
                this.gateway.handleError('Sync All Packages', error);
            });
        },

        /**
         * סינכרון ספק ספציפי
         */
        syncProvider: function(provider) {
            return this.gateway.apiRequest(`sync-${provider}-packages`, {
                method: 'POST'
            })
            .then(response => {
                if (response.success) {
                    this.gateway.utils.showMessage(
                        `✅ ${provider} סונכרן! עודכנו ${response.synced || 0} חבילות`,
                        'success'
                    );
                    this.loadPackages(false);
                } else {
                    this.gateway.utils.showMessage(
                        `שגיאה בסינכרון ${provider}: ${response.message}`,
                        'error'
                    );
                }
                return response;
            })
            .catch(error => {
                this.gateway.utils.showMessage(`שגיאה בסינכרון ${provider}`, 'error');
                throw error;
            });
        },

        /**
         * פילטור חבילות
         */
        filterPackages: function() {
            const searchTerm = document.getElementById('search-packages')?.value.toLowerCase() || '';
            const providerFilter = document.getElementById('provider-filter')?.value || '';
            const categoryFilter = document.getElementById('category-filter')?.value || '';

            this.filteredPackages = this.packages.filter(package => {
                // חיפוש טקסט
                const matchesSearch = !searchTerm || 
                    package.name.toLowerCase().includes(searchTerm) ||
                    package.description.toLowerCase().includes(searchTerm) ||
                    package.features?.some(feature => feature.toLowerCase().includes(searchTerm));

                // פילטר ספק
                const matchesProvider = !providerFilter || package.provider === providerFilter;

                // פילטר קטגוריה
                const matchesCategory = !categoryFilter || package.category === categoryFilter;

                return matchesSearch && matchesProvider && matchesCategory;
            });

            this.currentPage = 1; // איפוס עמוד
            this.renderPackages();
            this.updateStats();
        },

        /**
         * מיון חבילות
         */
        sortPackages: function() {
            const sortBy = document.getElementById('sort-filter')?.value || 'name';

            this.filteredPackages.sort((a, b) => {
                switch (sortBy) {
                    case 'name':
                        return a.name.localeCompare(b.name, 'he');
                    
                    case 'price-low':
                        return (a.price || 0) - (b.price || 0);
                    
                    case 'price-high':
                        return (b.price || 0) - (a.price || 0);
                    
                    case 'provider':
                        return a.provider.localeCompare(b.provider);
                    
                    case 'updated':
                        const dateA = new Date(a.updated_at || a.created_at);
                        const dateB = new Date(b.updated_at || b.created_at);
                        return dateB - dateA;
                    
                    default:
                        return 0;
                }
            });

            this.renderPackages();
        },

        /**
         * ניקוי פילטרים
         */
        clearFilters: function() {
            document.getElementById('search-packages').value = '';
            document.getElementById('provider-filter').value = '';
            document.getElementById('category-filter').value = '';
            document.getElementById('sort-filter').value = 'name';

            this.filterPackages();
        },

        /**
         * רינדור החבילות
         */
        renderPackages: function() {
            const container = document.getElementById('packages-grid');
            const template = document.getElementById('package-card-template');
            
            if (!container || !template) return;

            // הסתר מצבי loading ו-error
            this.hideLoadingState();
            this.hideErrorState();

            // הצג או הסתר no-results
            if (this.filteredPackages.length === 0) {
                this.showNoResultsState();
                return;
            } else {
                this.hideNoResultsState();
            }

            // הצג container
            document.getElementById('packages-container')?.classList.remove('hidden');

            // נקה container
            container.innerHTML = '';

            // רינדור חבילות
            const startIndex = (this.currentPage - 1) * this.packagesPerPage;
            const endIndex = Math.min(startIndex + this.packagesPerPage, this.filteredPackages.length);
            const packagesToShow = this.filteredPackages.slice(0, endIndex);

            packagesToShow.forEach(package => {
                const packageCard = this.createPackageCard(package, template);
                container.appendChild(packageCard);
            });

            // עדכן כפתור "טען עוד"
            this.updateLoadMoreButton();
        },

        /**
         * יצירת קרד חבילה
         */
        createPackageCard: function(package, template) {
            const card = template.content.cloneNode(true);
            
            // מילוי נתונים בסיסיים
            card.querySelector('.package-name').textContent = package.name || 'ללא שם';
            card.querySelector('.package-description').textContent = package.description || 'אין תיאור';
            card.querySelector('.package-category').textContent = this.getCategoryName(package.category);
            
            // ספק
            const providerBadge = card.querySelector('.provider-badge');
            const providerName = card.querySelector('.provider-name');
            const providerIcon = card.querySelector('.provider-icon');
            
            providerName.textContent = this.getProviderName(package.provider);
            providerIcon.textContent = this.getProviderIcon(package.provider);
            providerBadge.className = `provider-badge inline-flex items-center px-2 py-1 rounded-full text-xs font-medium ${this.getProviderColor(package.provider)}`;

            // מחיר
            card.querySelector('.package-price').textContent = this.formatPrice(package.price || 0);
            card.querySelector('.package-currency').textContent = package.currency || 'ILS';
            card.querySelector('.package-period').textContent = this.getPeriodText(package.billing_cycle);

            // הנחה
            if (package.discount_price && package.discount_price < package.price) {
                const discountSection = card.querySelector('.package-discount');
                discountSection.classList.remove('hidden');
                
                card.querySelector('.original-price').textContent = this.formatPrice(package.price);
                card.querySelector('.discount-badge').textContent = `חסכון ${Math.round(((package.price - package.discount_price) / package.price) * 100)}%`;
                card.querySelector('.package-price').textContent = this.formatPrice(package.discount_price);
            }

            // פיצ'רים
            const featuresContainer = card.querySelector('.package-features');
            if (package.features && Array.isArray(package.features)) {
                package.features.slice(0, 3).forEach(feature => {
                    const featureElement = document.createElement('div');
                    featureElement.className = 'flex items-center text-sm text-gray-600';
                    featureElement.innerHTML = `<span class="text-green-500 mr-2">✓</span>${feature}`;
                    featuresContainer.appendChild(featureElement);
                });
            }

            // מטא-דטה נסתרת
            card.querySelector('.package-id').textContent = package.id || '';
            card.querySelector('.package-slug').textContent = package.slug || '';
            card.querySelector('.package-provider').textContent = package.provider || '';
            card.querySelector('.package-raw-data').textContent = JSON.stringify(package);

            return card;
        },

        /**
         * הצגת פרטי חבילה - מעודכן עם slug navigation
         */
        showPackageDetails: function(packageId, rawData) {
            const package = this.packages.find(p => p.id == packageId) || rawData;
            if (!package) return;

            const modal = document.getElementById('package-modal');
            const title = document.getElementById('modal-title');
            const content = document.getElementById('modal-content');
            const selectBtn = document.getElementById('modal-select-btn');

            title.textContent = package.name || 'פרטי חבילה';
            selectBtn.dataset.packageId = package.id;
            selectBtn.dataset.packageSlug = package.slug || '';
            
            // עדכון כפתור לזרימת checkout עם slug
            selectBtn.onclick = () => this.navigateToCheckout(package.slug || package.id);

            // יצירת תוכן מפורט
            content.innerHTML = `
                <div class="space-y-6">
                    <div>
                        <h3 class="font-semibold mb-2">תיאור</h3>
                        <p class="text-gray-700">${package.description || 'אין תיאור זמין'}</p>
                    </div>
                    
                    ${package.features ? `
                    <div>
                        <h3 class="font-semibold mb-2">מאפיינים</h3>
                        <ul class="space-y-1">
                            ${package.features.map(feature => `
                                <li class="flex items-center text-sm">
                                    <span class="text-green-500 mr-2">✓</span>
                                    ${feature}
                                </li>
                            `).join('')}
                        </ul>
                    </div>
                    ` : ''}
                    
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <h3 class="font-semibold mb-2">מחיר</h3>
                            <p class="text-2xl font-bold text-blue-600">
                                ${this.formatPrice(package.discount_price || package.price || 0)} 
                                <span class="text-sm text-gray-500">${package.currency || 'ILS'}</span>
                            </p>
                            ${package.discount_price ? `
                                <p class="text-sm text-gray-500 line-through">
                                    ${this.formatPrice(package.price)}
                                </p>
                            ` : ''}
                        </div>
                        
                        <div>
                            <h3 class="font-semibold mb-2">מידע כללי</h3>
                            <ul class="text-sm text-gray-600 space-y-1">
                                <li>ספק: ${this.getProviderName(package.provider)}</li>
                                <li>קטגוריה: ${this.getCategoryName(package.category)}</li>
                                ${package.billing_cycle ? `<li>מחזור: ${this.getPeriodText(package.billing_cycle)}</li>` : ''}
                                ${package.setup_fee ? `<li>עלות הקמה: ${this.formatPrice(package.setup_fee)} ${package.currency}</li>` : ''}
                            </ul>
                        </div>
                    </div>
                    
                    ${package.terms ? `
                    <div>
                        <h3 class="font-semibold mb-2">תנאים</h3>
                        <p class="text-sm text-gray-600">${package.terms}</p>
                    </div>
                    ` : ''}
                </div>
            `;

            modal.classList.remove('hidden');
        },

        /**
         * טעינת חבילות נוספות
         */
        loadMore: function() {
            this.currentPage++;
            this.renderPackages();
        },

        /**
         * עדכון כפתור "טען עוד"
         */
        updateLoadMoreButton: function() {
            const button = document.getElementById('load-more-btn');
            if (!button) return;

            const totalShown = this.currentPage * this.packagesPerPage;
            const hasMore = totalShown < this.filteredPackages.length;

            if (hasMore) {
                button.classList.remove('hidden');
                button.textContent = `טען עוד (${this.filteredPackages.length - totalShown} נוספות)`;
            } else {
                button.classList.add('hidden');
            }
        },

        /**
         * עדכון סטטיסטיקות
         */
        updateStats: function() {
            const statsElement = document.getElementById('packages-stats');
            if (!statsElement) return;

            const total = this.packages.length;
            const filtered = this.filteredPackages.length;
            const providers = [...new Set(this.packages.map(p => p.provider))];

            statsElement.innerHTML = `
                📊 סה"כ ${total} חבילות | מוצג: ${filtered} | ספקים: ${providers.length}
                ${filtered !== total ? `(מסונן מתוך ${total})` : ''}
            `;

            // עדכון סטטוס עדכון אחרון
            const lastSyncElement = document.getElementById('last-sync');
            if (lastSyncElement && this.lastSync) {
                lastSyncElement.textContent = this.gateway.utils.formatDate(this.lastSync);
            }
        },

        /**
         * עדכון סטטוס סינכרון
         */
        updateSyncStatus: function(status) {
            const statusElement = document.getElementById('sync-status');
            const connectionElement = document.getElementById('connection-status');
            
            if (statusElement) {
                const dot = statusElement.querySelector('.status-dot');
                
                switch (status) {
                    case 'syncing':
                        dot.className = 'status-dot bg-yellow-400 animate-pulse';
                        statusElement.innerHTML = '<span class="status-dot bg-yellow-400 animate-pulse"></span>מסנכרן...';
                        break;
                    case 'success':
                        dot.className = 'status-dot bg-green-400';
                        statusElement.innerHTML = '<span class="status-dot bg-green-400"></span>מעודכן';
                        break;
                    case 'error':
                        dot.className = 'status-dot bg-red-400';
                        statusElement.innerHTML = '<span class="status-dot bg-red-400"></span>שגיאה';
                        break;
                }
            }
            
            if (connectionElement) {
                connectionElement.textContent = status === 'success' ? 'מחובר' : 'לא מחובר';
                connectionElement.className = `font-semibold ${status === 'success' ? 'text-green-600' : 'text-red-600'}`;
            }
        },

        /**
         * הצגת מצב טעינה
         */
        showLoadingState: function() {
            document.getElementById('loading-state')?.classList.remove('hidden');
            document.getElementById('error-state')?.classList.add('hidden');
            document.getElementById('no-results-state')?.classList.add('hidden');
            document.getElementById('packages-container')?.classList.add('hidden');
        },

        /**
         * הסתרת מצב טעינה
         */
        hideLoadingState: function() {
            document.getElementById('loading-state')?.classList.add('hidden');
        },

        /**
         * הצגת מצב שגיאה
         */
        showErrorState: function(message) {
            const errorState = document.getElementById('error-state');
            const errorMessage = document.getElementById('error-message');
            
            if (errorState && errorMessage) {
                errorMessage.textContent = message;
                errorState.classList.remove('hidden');
            }
            
            this.hideLoadingState();
            document.getElementById('packages-container')?.classList.add('hidden');
            document.getElementById('no-results-state')?.classList.add('hidden');
        },

        /**
         * הסתרת מצב שגיאה
         */
        hideErrorState: function() {
            document.getElementById('error-state')?.classList.add('hidden');
        },

        /**
         * הצגת מצב "אין תוצאות"
         */
        showNoResultsState: function() {
            document.getElementById('no-results-state')?.classList.remove('hidden');
            document.getElementById('packages-container')?.classList.add('hidden');
        },

        /**
         * הסתרת מצב "אין תוצאות"
         */
        hideNoResultsState: function() {
            document.getElementById('no-results-state')?.classList.add('hidden');
        },

        /**
         * שמירה למטמון
         */
        saveToCache: function() {
            try {
                const cacheData = {
                    packages: this.packages,
                    lastSync: this.lastSync?.toISOString(),
                    timestamp: Date.now()
                };
                localStorage.setItem('paymentGatewayPackages', JSON.stringify(cacheData));
            } catch (error) {
                console.warn('Failed to save packages to cache:', error);
            }
        },

        /**
         * טעינה מהמטמון
         */
        loadFromCache: function() {
            try {
                const cached = localStorage.getItem('paymentGatewayPackages');
                if (cached) {
                    const cacheData = JSON.parse(cached);
                    
                    // בדיקה שהמטמון לא ישן מדי (24 שעות)
                    const age = Date.now() - cacheData.timestamp;
                    if (age < 24 * 60 * 60 * 1000) {
                        this.packages = cacheData.packages || [];
                        this.filteredPackages = [...this.packages];
                        this.lastSync = cacheData.lastSync ? new Date(cacheData.lastSync) : null;
                        
                        if (this.packages.length > 0) {
                            this.renderPackages();
                            this.updateStats();
                        }
                    }
                }
            } catch (error) {
                console.warn('Failed to load packages from cache:', error);
            }
        },

        /**
         * Helper Functions
         */
        formatPrice: function(price) {
            return this.gateway?.utils?.formatPrice ? 
                this.gateway.utils.formatPrice(price) : 
                `₪${price.toFixed(2)}`;
        },

        getProviderName: function(provider) {
            const names = {
                'cardcom': 'CardCom',
                'maya-mobile': 'Maya Mobile',
                'resellerclub': 'ResellerClub'
            };
            return names[provider] || provider;
        },

        getProviderIcon: function(provider) {
            const icons = {
                'cardcom': '💳',
                'maya-mobile': '📱',
                'resellerclub': '🌐'
            };
            return icons[provider] || '🔧';
        },

        getProviderColor: function(provider) {
            const colors = {
                'cardcom': 'bg-blue-100 text-blue-800',
                'maya-mobile': 'bg-purple-100 text-purple-800',
                'resellerclub': 'bg-orange-100 text-orange-800'
            };
            return colors[provider] || 'bg-gray-100 text-gray-800';
        },

        getCategoryName: function(category) {
            const names = {
                'domain': '🌐 דומיינים',
                'hosting': '🖥️ אירוח',
                'esim': '📱 eSIM',
                'ssl': '🔒 SSL',
                'email': '📧 דואר'
            };
            return names[category] || category;
        },

        getPeriodText: function(period) {
            const periods = {
                'monthly': '/חודש',
                'yearly': '/שנה',
                'one_time': 'חד פעמי',
                'daily': '/יום'
            };
            return periods[period] || '';
        },

        /**
         * ניתוב לדף checkout עם slug (כמו מערכת eSIM)
         */
        navigateToCheckout: function(packageSlug) {
            if (!packageSlug) {
                this.gateway.utils.showMessage('שגיאה: חסר מזהה חבילה', 'error');
                return;
            }
            
            // יצירת URL זהה למערכת eSIM: /payment-gateway/checkout/{packageSlug}
            const checkoutUrl = `/payment-gateway/checkout/${packageSlug}`;
            
            this.gateway.utils.showMessage('מעביר לדף רכישה...', 'info');
            
            // ניתוב לדף checkout
            window.location.href = checkoutUrl;
        },

        /**
         * ניתוב לדף פרטי חבילה
         */
        navigateToPackage: function(packageSlug) {
            if (!packageSlug) return;
            
            const packageUrl = `/payment-gateway/packages/${packageSlug}`;
            window.location.href = packageUrl;
        },

        /**
         * קישור אירועים - מעודכן עם slug support
         */
        bindEvents: function() {
            // Auto-refresh every 15 minutes
            setInterval(() => {
                if (!this.isLoading) {
                    this.loadPackages(false);
                }
            }, 15 * 60 * 1000);

            // קישור click events לכרטיסי חבילות (delegation)
            document.addEventListener('click', (e) => {
                // כפתור "רכוש עכשיו" - ניתוב לcheckout
                if (e.target.matches('.package-checkout-btn') || e.target.closest('.package-checkout-btn')) {
                    const btn = e.target.closest('.package-checkout-btn');
                    const packageSlug = btn?.dataset.packageSlug;
                    if (packageSlug) {
                        e.preventDefault();
                        this.navigateToCheckout(packageSlug);
                    }
                }

                // כפתור "פרטים נוספים" - הצגת modal
                if (e.target.matches('.package-details-btn') || e.target.closest('.package-details-btn')) {
                    const btn = e.target.closest('.package-details-btn');
                    const packageId = btn?.dataset.packageId;
                    const rawData = btn?.dataset.rawData ? JSON.parse(btn.dataset.rawData) : null;
                    if (packageId) {
                        e.preventDefault();
                        this.showPackageDetails(packageId, rawData);
                    }
                }

                // כרטיס חבילה - ניתוב לדף חבילה
                if (e.target.matches('.package-card') || e.target.closest('.package-card')) {
                    const card = e.target.closest('.package-card');
                    const packageSlug = card?.querySelector('.package-slug')?.textContent;
                    if (packageSlug && !e.target.matches('button') && !e.target.closest('button')) {
                        e.preventDefault();
                        this.navigateToPackage(packageSlug);
                    }
                }
            });

            // קישור events לחיפוש וסינון
            document.addEventListener('input', (e) => {
                if (e.target.matches('#search-packages')) {
                    // Debounce search
                    clearTimeout(this.searchTimeout);
                    this.searchTimeout = setTimeout(() => {
                        this.filterPackages();
                    }, 300);
                }
            });

            document.addEventListener('change', (e) => {
                if (e.target.matches('#provider-filter, #category-filter, #sort-filter')) {
                    this.filterPackages();
                    this.sortPackages();
                }
            });

            // URL state management - תמיכה בparameters מURL
            this.loadUrlState();
            window.addEventListener('popstate', () => {
                this.loadUrlState();
            });
        },

        /**
         * טעינת state מURL parameters
         */
        loadUrlState: function() {
            const urlParams = new URLSearchParams(window.location.search);
            
            // עדכון פילטרים לפי URL
            if (urlParams.has('search')) {
                const searchInput = document.getElementById('search-packages');
                if (searchInput) searchInput.value = urlParams.get('search');
            }
            
            if (urlParams.has('provider')) {
                const providerFilter = document.getElementById('provider-filter');
                if (providerFilter) providerFilter.value = urlParams.get('provider');
            }
            
            if (urlParams.has('category')) {
                const categoryFilter = document.getElementById('category-filter');
                if (categoryFilter) categoryFilter.value = urlParams.get('category');
            }
            
            // החל פילטרים
            if (this.packages.length > 0) {
                this.filterPackages();
            }
        },

        /**
         * עדכון URL עם state נוכחי
         */
        updateUrlState: function() {
            const searchTerm = document.getElementById('search-packages')?.value || '';
            const provider = document.getElementById('provider-filter')?.value || '';
            const category = document.getElementById('category-filter')?.value || '';
            
            const params = new URLSearchParams();
            if (searchTerm) params.set('search', searchTerm);
            if (provider) params.set('provider', provider);
            if (category) params.set('category', category);
            
            const newUrl = window.location.pathname + (params.toString() ? '?' + params.toString() : '');
            window.history.pushState({}, '', newUrl);
        }
    };

    // חשיפת המודול
    if (window.PaymentGateway) {
        window.PaymentGateway.PackageManager = PackageManager;
    }

})(window);