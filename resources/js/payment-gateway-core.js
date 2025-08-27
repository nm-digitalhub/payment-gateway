/**
 * Payment Gateway Core JavaScript Module
 * מודול JavaScript מרכזי לחבילת Payment Gateway
 */

(function(window, undefined) {
    'use strict';

    // יצירת namespace גלובלי
    const PaymentGateway = {
        version: '1.0.0',
        debug: false,
        apiUrl: '/api/payment-gateway',
        csrf_token: null,
        
        // הגדרות ברירת מחדל
        defaults: {
            timeout: 30000,
            retries: 3,
            locale: 'he',
            currency: 'ILS'
        },

        // מטמון תוצאות
        cache: new Map(),

        // Event system
        events: {},

        /**
         * אתחול המודול
         */
        init: function(options = {}) {
            this.settings = Object.assign({}, this.defaults, options);
            this.csrf_token = this.getCSRFToken();
            
            if (this.debug) {
                console.log('PaymentGateway initialized:', this.settings);
            }

            // אתחול מודולים
            this.initModules();
            this.bindEvents();

            return this;
        },

        /**
         * יצירת בקשת API
         */
        apiRequest: function(endpoint, options = {}) {
            const url = `${this.apiUrl}/${endpoint}`;
            const defaultOptions = {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': this.csrf_token,
                    'Accept': 'application/json'
                },
                timeout: this.settings.timeout
            };

            const requestOptions = Object.assign({}, defaultOptions, options);

            if (this.debug) {
                console.log('API Request:', url, requestOptions);
            }

            return fetch(url, requestOptions)
                .then(response => {
                    if (!response.ok) {
                        throw new Error(`HTTP error! status: ${response.status}`);
                    }
                    return response.json();
                })
                .then(data => {
                    if (this.debug) {
                        console.log('API Response:', data);
                    }
                    return data;
                })
                .catch(error => {
                    this.handleError('API Request Failed', error);
                    throw error;
                });
        },

        /**
         * טיפול בשגיאות
         */
        handleError: function(context, error) {
            const errorMsg = `[PaymentGateway] ${context}: ${error.message}`;
            
            if (this.debug) {
                console.error(errorMsg, error);
            }

            // שלח event שגיאה
            this.trigger('error', { context, error, message: errorMsg });
        },

        /**
         * קבלת CSRF Token
         */
        getCSRFToken: function() {
            const metaTag = document.querySelector('meta[name="csrf-token"]');
            return metaTag ? metaTag.getAttribute('content') : null;
        },

        /**
         * אתחול מודולים - מעודכן עם מודולים מבוססי eSIM
         */
        initModules: function() {
            // אתחול מודול FormHandler
            if (this.FormHandler) {
                this.FormHandler.init(this);
            }

            // אתחול מודול ConnectionTester
            if (this.ConnectionTester) {
                this.ConnectionTester.init(this);
            }

            // אתחול מודול ProviderManager
            if (this.ProviderManager) {
                this.ProviderManager.init(this);
            }

            // אתחול מודול PackageManager החדש (מבוסס eSIM)
            if (this.PackageManager) {
                this.PackageManager.init(this);
            }

            console.log('PaymentGateway: All modules initialized', {
                FormHandler: !!this.FormHandler,
                ConnectionTester: !!this.ConnectionTester,
                ProviderManager: !!this.ProviderManager,
                PackageManager: !!this.PackageManager
            });
        },

        /**
         * Event system - הרשמה לאירוע
         */
        on: function(event, callback) {
            if (!this.events[event]) {
                this.events[event] = [];
            }
            this.events[event].push(callback);
        },

        /**
         * Event system - הפעלת אירוע
         */
        trigger: function(event, data) {
            if (this.events[event]) {
                this.events[event].forEach(callback => callback(data));
            }
        },

        /**
         * קישור אירועים גלובליים - מעודכן עם תמיכה בslug וזרימה מלאה
         */
        bindEvents: function() {
            // טיפול בטפסים עם class payment-gateway-form
            document.addEventListener('submit', (e) => {
                if (e.target.classList.contains('payment-gateway-form')) {
                    e.preventDefault();
                    this.FormHandler.handleSubmit(e.target);
                }
            });

            // טיפול בכפתורי בדיקת חיבור
            document.addEventListener('click', (e) => {
                if (e.target.classList.contains('test-connection-btn')) {
                    e.preventDefault();
                    const provider = e.target.dataset.provider;
                    this.ConnectionTester.test(provider);
                }

                // כפתורי רכישה עם slug
                if (e.target.classList.contains('purchase-btn') || e.target.closest('.purchase-btn')) {
                    e.preventDefault();
                    const btn = e.target.closest('.purchase-btn') || e.target;
                    const packageSlug = btn.dataset.packageSlug || btn.dataset.slug;
                    
                    if (packageSlug) {
                        this.navigateToCheckout(packageSlug);
                    } else {
                        this.utils.showMessage('שגיאה: חסר מזהה חבילה', 'error');
                    }
                }

                // כפתורי סנכרון
                if (e.target.classList.contains('sync-packages-btn')) {
                    e.preventDefault();
                    if (this.PackageManager) {
                        this.PackageManager.syncAll();
                    }
                }
            });

            // טיפול בשינויי URL (back/forward navigation)
            window.addEventListener('popstate', (e) => {
                if (this.PackageManager && this.PackageManager.loadUrlState) {
                    this.PackageManager.loadUrlState();
                }
            });

            console.log('PaymentGateway: Global events bound successfully');
        },

        /**
         * ניתוב לדף checkout עם slug (כמו מערכת eSIM)
         */
        navigateToCheckout: function(packageSlug) {
            if (!packageSlug) {
                this.utils.showMessage('שגיאה: חסר מזהה חבילה', 'error');
                return;
            }
            
            // הצגת הודעת טעינה
            this.utils.showMessage('מעביר לדף רכישה...', 'info');
            
            // יצירת URL מבוסס slug
            const checkoutUrl = `/payment-gateway/checkout/${packageSlug}`;
            
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
         * עזרים
         */
        utils: {
            /**
             * תרגום כיווניות לעברית
             */
            setRTL: function() {
                document.documentElement.dir = 'rtl';
                document.documentElement.lang = 'he';
            },

            /**
             * פורמט מחיר
             */
            formatPrice: function(amount, currency = 'ILS') {
                const formatter = new Intl.NumberFormat('he-IL', {
                    style: 'currency',
                    currency: currency,
                    minimumFractionDigits: 2
                });
                return formatter.format(amount);
            },

            /**
             * פורמט תאריך
             */
            formatDate: function(date, locale = 'he-IL') {
                const formatter = new Intl.DateTimeFormat(locale, {
                    year: 'numeric',
                    month: 'long',
                    day: 'numeric',
                    hour: '2-digit',
                    minute: '2-digit'
                });
                return formatter.format(new Date(date));
            },

            /**
             * בדיקת תקינות מספר טלפון ישראלי
             */
            validateIsraeliPhone: function(phone) {
                const phoneRegex = /^(\+972|972|0)([23489]|5[0248]|77)[0-9]{7}$/;
                return phoneRegex.test(phone.replace(/[-\s]/g, ''));
            },

            /**
             * בדיקת תקינות מספר זהות ישראלי
             */
            validateIsraeliID: function(id) {
                if (!/^\d{9}$/.test(id)) return false;
                
                const digits = id.split('').map(Number);
                let sum = 0;
                
                for (let i = 0; i < 8; i++) {
                    let digit = digits[i];
                    if (i % 2 === 1) digit *= 2;
                    if (digit > 9) digit -= 9;
                    sum += digit;
                }
                
                return (10 - (sum % 10)) % 10 === digits[8];
            },

            /**
             * הצגת הודעה
             */
            showMessage: function(message, type = 'info') {
                // יצירת toast notification
                const toast = document.createElement('div');
                toast.className = `payment-gateway-toast toast-${type}`;
                toast.innerHTML = `
                    <div class="toast-content">
                        <span class="toast-icon">${this.getToastIcon(type)}</span>
                        <span class="toast-message">${message}</span>
                        <button class="toast-close" onclick="this.parentElement.parentElement.remove()">&times;</button>
                    </div>
                `;

                // הוספה לDOM
                let container = document.querySelector('.payment-gateway-toasts');
                if (!container) {
                    container = document.createElement('div');
                    container.className = 'payment-gateway-toasts';
                    container.style.cssText = `
                        position: fixed;
                        top: 20px;
                        right: 20px;
                        z-index: 10000;
                        direction: rtl;
                    `;
                    document.body.appendChild(container);
                }

                container.appendChild(toast);

                // הסרה אוטומטית אחרי 5 שניות
                setTimeout(() => {
                    if (toast.parentNode) {
                        toast.parentNode.removeChild(toast);
                    }
                }, 5000);
            },

            /**
             * קבלת אייקון לtoast
             */
            getToastIcon: function(type) {
                const icons = {
                    success: '✅',
                    error: '❌',
                    warning: '⚠️',
                    info: 'ℹ️'
                };
                return icons[type] || icons.info;
            }
        }
    };

    // חשיפת המודול גלובלית
    window.PaymentGateway = PaymentGateway;

    // אתחול אוטומטי אם DOM מוכן
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function() {
            if (window.paymentGatewayAutoInit !== false) {
                PaymentGateway.init(window.paymentGatewayConfig || {});
            }
        });
    } else {
        if (window.paymentGatewayAutoInit !== false) {
            PaymentGateway.init(window.paymentGatewayConfig || {});
        }
    }

})(window);