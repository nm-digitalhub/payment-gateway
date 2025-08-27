/**
 * Payment Gateway Package JavaScript
 * 
 * Handles form interactions, payment processing, and UI updates
 */

(function(window, document) {
    'use strict';

    // Main PaymentGateway object
    window.PaymentGateway = window.PaymentGateway || {};

    /**
     * Form Handler Class
     */
    class PaymentForm {
        constructor(formElement, options = {}) {
            this.form = formElement;
            this.options = {
                validateOnInput: true,
                showProgress: true,
                rtlSupport: true,
                ...options
            };
            this.isProcessing = false;
            this.init();
        }

        init() {
            this.setupEventListeners();
            this.setupValidation();
            if (this.options.showProgress) {
                this.initProgressIndicator();
            }
        }

        setupEventListeners() {
            // Form submission
            this.form.addEventListener('submit', (e) => {
                e.preventDefault();
                this.handleSubmit();
            });

            // Payment method selection
            const paymentMethods = this.form.querySelectorAll('input[name="payment_method"]');
            paymentMethods.forEach(method => {
                method.addEventListener('change', (e) => {
                    this.handlePaymentMethodChange(e.target.value);
                });
            });

            // Real-time validation
            if (this.options.validateOnInput) {
                const inputs = this.form.querySelectorAll('input, select, textarea');
                inputs.forEach(input => {
                    input.addEventListener('blur', () => {
                        this.validateField(input);
                    });
                    input.addEventListener('input', () => {
                        this.clearFieldError(input);
                    });
                });
            }

            // Amount formatting
            const amountInputs = this.form.querySelectorAll('input[name*="amount"]');
            amountInputs.forEach(input => {
                input.addEventListener('input', (e) => {
                    this.formatAmount(e.target);
                });
            });

            // Phone number formatting
            const phoneInputs = this.form.querySelectorAll('input[name*="phone"]');
            phoneInputs.forEach(input => {
                input.addEventListener('input', (e) => {
                    this.formatPhone(e.target);
                });
            });
        }

        setupValidation() {
            this.validators = {
                required: (value) => value && value.toString().trim() !== '',
                email: (value) => /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value),
                phone: (value) => /^[+]?[0-9\s\-\(\)]{7,15}$/.test(value),
                amount: (value) => !isNaN(value) && parseFloat(value) > 0,
                israeliId: (value) => {
                    // Israeli ID validation
                    if (!/^\d{9}$/.test(value)) return false;
                    const digits = value.split('').map(Number);
                    const checksum = digits.reduce((sum, digit, index) => {
                        const weight = (index % 2) + 1;
                        const product = digit * weight;
                        return sum + (product > 9 ? product - 9 : product);
                    }, 0);
                    return checksum % 10 === 0;
                }
            };
        }

        validateField(field) {
            const value = field.value;
            const rules = field.dataset.validate ? field.dataset.validate.split('|') : [];
            
            for (const rule of rules) {
                if (this.validators[rule] && !this.validators[rule](value)) {
                    this.showFieldError(field, this.getErrorMessage(rule));
                    return false;
                }
            }

            this.clearFieldError(field);
            return true;
        }

        validateForm() {
            const fields = this.form.querySelectorAll('[data-validate]');
            let isValid = true;

            fields.forEach(field => {
                if (!this.validateField(field)) {
                    isValid = false;
                }
            });

            return isValid;
        }

        showFieldError(field, message) {
            const fieldContainer = field.closest('.payment-field');
            if (!fieldContainer) return;

            fieldContainer.classList.add('error');
            
            let errorElement = fieldContainer.querySelector('.error-message');
            if (!errorElement) {
                errorElement = document.createElement('div');
                errorElement.className = 'error-message';
                fieldContainer.appendChild(errorElement);
            }
            
            errorElement.textContent = message;
        }

        clearFieldError(field) {
            const fieldContainer = field.closest('.payment-field');
            if (!fieldContainer) return;

            fieldContainer.classList.remove('error');
            const errorElement = fieldContainer.querySelector('.error-message');
            if (errorElement) {
                errorElement.remove();
            }
        }

        getErrorMessage(rule) {
            const messages = {
                required: 'שדה זה הוא חובה',
                email: 'נא להזין כתובת אימייל תקנית',
                phone: 'נא להזין מספר טלפון תקין',
                amount: 'נא להזין סכום תקין',
                israeliId: 'נא להזין מספר זהות ישראלי תקין'
            };
            return messages[rule] || 'הערך שהוזן אינו תקין';
        }

        handlePaymentMethodChange(method) {
            // Hide all payment method specific forms
            const methodForms = this.form.querySelectorAll('[data-payment-method]');
            methodForms.forEach(form => {
                form.style.display = 'none';
            });

            // Show the selected method form
            const selectedForm = this.form.querySelector(`[data-payment-method="${method}"]`);
            if (selectedForm) {
                selectedForm.style.display = 'block';
                selectedForm.classList.add('fade-in');
            }

            // Update submit button text
            const submitButton = this.form.querySelector('[type="submit"]');
            if (submitButton) {
                const buttonTexts = {
                    cardcom: 'תשלום בכרטיס אשראי',
                    bank_transfer: 'המשך להעברה בנקאית',
                    cash: 'המשך לתשלום במזומן'
                };
                submitButton.textContent = buttonTexts[method] || 'המשך';
            }
        }

        formatAmount(input) {
            let value = input.value.replace(/[^\d.]/g, '');
            if (value) {
                const parts = value.split('.');
                if (parts[1] && parts[1].length > 2) {
                    value = parts[0] + '.' + parts[1].substring(0, 2);
                }
                input.value = value;
            }
        }

        formatPhone(input) {
            let value = input.value.replace(/[^\d+\-\s\(\)]/g, '');
            input.value = value;
        }

        initProgressIndicator() {
            const steps = this.form.querySelectorAll('.payment-step');
            if (steps.length > 0) {
                this.currentStep = 0;
                this.totalSteps = steps.length;
                this.updateProgress();
            }
        }

        updateProgress() {
            const steps = this.form.querySelectorAll('.payment-step');
            steps.forEach((step, index) => {
                step.classList.remove('active', 'completed');
                if (index < this.currentStep) {
                    step.classList.add('completed');
                } else if (index === this.currentStep) {
                    step.classList.add('active');
                }
            });
        }

        nextStep() {
            if (this.currentStep < this.totalSteps - 1) {
                this.currentStep++;
                this.updateProgress();
            }
        }

        prevStep() {
            if (this.currentStep > 0) {
                this.currentStep--;
                this.updateProgress();
            }
        }

        async handleSubmit() {
            if (this.isProcessing) return;

            // Validate form
            if (!this.validateForm()) {
                this.showStatus('אנא תקנו את השגיאות בטופס', 'error');
                return;
            }

            this.isProcessing = true;
            this.showLoading(true);

            try {
                const formData = new FormData(this.form);
                const response = await this.submitForm(formData);
                
                if (response.success) {
                    this.handleSuccess(response);
                } else {
                    this.handleError(response.error || 'אירעה שגיאה בעיבוד התשלום');
                }
            } catch (error) {
                this.handleError('שגיאת חיבור לשרת');
            } finally {
                this.isProcessing = false;
                this.showLoading(false);
            }
        }

        async submitForm(formData) {
            const response = await fetch(this.form.action, {
                method: 'POST',
                body: formData,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content
                }
            });

            return await response.json();
        }

        handleSuccess(response) {
            this.showStatus('התשלום עובד בהצלחה!', 'success');
            
            if (response.redirect_url) {
                setTimeout(() => {
                    window.location.href = response.redirect_url;
                }, 2000);
            } else if (response.checkout_url) {
                window.location.href = response.checkout_url;
            }
        }

        handleError(message) {
            this.showStatus(message, 'error');
        }

        showStatus(message, type) {
            let statusElement = this.form.querySelector('.payment-status');
            if (!statusElement) {
                statusElement = document.createElement('div');
                statusElement.className = 'payment-status';
                this.form.insertBefore(statusElement, this.form.firstChild);
            }

            statusElement.className = `payment-status ${type}`;
            statusElement.textContent = message;
            statusElement.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        }

        showLoading(show) {
            const submitButton = this.form.querySelector('[type="submit"]');
            if (submitButton) {
                submitButton.disabled = show;
                submitButton.classList.toggle('loading', show);
            }
        }
    }

    /**
     * CardCom Integration Handler
     */
    class CardComHandler {
        constructor(options = {}) {
            this.options = {
                containerId: 'cardcom-container',
                language: 'he',
                theme: 'default',
                ...options
            };
        }

        async initializePayment(paymentData) {
            try {
                const response = await fetch('/api/cardcom/create-low-profile', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content
                    },
                    body: JSON.stringify(paymentData)
                });

                const result = await response.json();
                
                if (result.success && result.checkout_url) {
                    this.redirectToPayment(result.checkout_url);
                } else {
                    throw new Error(result.error || 'Failed to create payment session');
                }
            } catch (error) {
                console.error('CardCom initialization error:', error);
                throw error;
            }
        }

        redirectToPayment(checkoutUrl) {
            window.location.href = checkoutUrl;
        }
    }

    /**
     * Utility Functions
     */
    const Utils = {
        formatCurrency(amount, currency = 'ILS') {
            return new Intl.NumberFormat('he-IL', {
                style: 'currency',
                currency: currency,
                minimumFractionDigits: 2
            }).format(amount);
        },

        formatNumber(number) {
            return new Intl.NumberFormat('he-IL').format(number);
        },

        debounce(func, wait) {
            let timeout;
            return function executedFunction(...args) {
                const later = () => {
                    clearTimeout(timeout);
                    func(...args);
                };
                clearTimeout(timeout);
                timeout = setTimeout(later, wait);
            };
        },

        isRTL() {
            return document.dir === 'rtl' || document.documentElement.dir === 'rtl';
        },

        getCSRFToken() {
            return document.querySelector('meta[name="csrf-token"]')?.content;
        }
    };

    // Expose classes and utilities
    PaymentGateway.PaymentForm = PaymentForm;
    PaymentGateway.CardComHandler = CardComHandler;
    PaymentGateway.Utils = Utils;

    /**
     * Auto-initialize forms when DOM is ready
     */
    document.addEventListener('DOMContentLoaded', function() {
        // Auto-initialize payment forms
        const paymentForms = document.querySelectorAll('.payment-gateway-form form');
        paymentForms.forEach(form => {
            new PaymentForm(form);
        });

        // Set RTL direction if needed
        if (document.documentElement.lang === 'he' || document.documentElement.lang === 'ar') {
            document.body.dir = 'rtl';
        }
    });

    // Handle browser back button for payment flows
    window.addEventListener('popstate', function(event) {
        if (event.state && event.state.paymentStep) {
            // Handle payment flow navigation
            console.log('Payment navigation:', event.state);
        }
    });

})(window, document);