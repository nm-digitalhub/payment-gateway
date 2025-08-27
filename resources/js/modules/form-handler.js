/**
 * Form Handler Module for Payment Gateway
 * מודול לטיפול בטפסים של Payment Gateway
 */

(function(window) {
    'use strict';

    const FormHandler = {
        gateway: null,
        forms: new Map(),

        /**
         * אתחול המודול
         */
        init: function(gateway) {
            this.gateway = gateway;
            this.bindEvents();
            this.initializeForms();
        },

        /**
         * אתחול כל הטפסים בעמוד
         */
        initializeForms: function() {
            const forms = document.querySelectorAll('.payment-gateway-form');
            forms.forEach(form => this.initializeForm(form));
        },

        /**
         * אתחול טופס ספציפי
         */
        initializeForm: function(form) {
            const formId = form.id || `form-${Date.now()}`;
            const formType = form.dataset.formType;
            
            const formConfig = {
                element: form,
                type: formType,
                validators: this.getValidators(formType),
                fields: this.mapFormFields(form)
            };

            this.forms.set(formId, formConfig);
            
            // הוספת validation בזמן אמת
            this.addRealTimeValidation(form, formConfig);
        },

        /**
         * מיפוי שדות הטופס
         */
        mapFormFields: function(form) {
            const fields = {};
            const inputs = form.querySelectorAll('input, select, textarea');
            
            inputs.forEach(input => {
                fields[input.name] = {
                    element: input,
                    type: input.type,
                    required: input.required,
                    value: input.value
                };
            });

            return fields;
        },

        /**
         * קבלת validators לפי סוג טופס
         */
        getValidators: function(formType) {
            const validators = {
                'cardcom-settings': {
                    terminal: {
                        required: true,
                        pattern: /^\d+$/,
                        message: 'מספר טרמינל חייב להיות מספר'
                    },
                    username: {
                        required: true,
                        minLength: 3,
                        message: 'שם משתמש חייב להכיל לפחות 3 תווים'
                    },
                    password: {
                        required: true,
                        minLength: 6,
                        message: 'סיסמה חייבת להכיל לפחות 6 תווים'
                    }
                },

                'maya-mobile-settings': {
                    api_key: {
                        required: true,
                        minLength: 10,
                        message: 'מפתח API חייב להכיל לפחות 10 תווים'
                    },
                    api_secret: {
                        required: true,
                        minLength: 10,
                        message: 'סוד API חייב להכיל לפחות 10 תווים'
                    },
                    api_url: {
                        required: true,
                        pattern: /^https?:\/\/.+/,
                        message: 'כתובת API חייבת להיות תקינה'
                    }
                },

                'resellerclub-settings': {
                    reseller_id: {
                        required: true,
                        pattern: /^\d+$/,
                        message: 'מזהה משווק חייב להיות מספר'
                    },
                    api_key: {
                        required: true,
                        minLength: 10,
                        message: 'מפתח API חייב להכיל לפחות 10 תווים'
                    }
                }
            };

            return validators[formType] || {};
        },

        /**
         * הוספת validation בזמן אמת
         */
        addRealTimeValidation: function(form, formConfig) {
            Object.keys(formConfig.fields).forEach(fieldName => {
                const field = formConfig.fields[fieldName];
                const validator = formConfig.validators[fieldName];

                if (validator) {
                    field.element.addEventListener('blur', () => {
                        this.validateField(field, validator);
                    });

                    field.element.addEventListener('input', () => {
                        // נקה שגיאות קיימות כשמתחילים להקליד
                        this.clearFieldError(field.element);
                    });
                }
            });
        },

        /**
         * בדיקת תקינות שדה
         */
        validateField: function(field, validator) {
            const value = field.element.value.trim();
            let isValid = true;
            let errorMessage = '';

            // בדיקת חובה
            if (validator.required && !value) {
                isValid = false;
                errorMessage = `${this.getFieldLabel(field.element)} הוא שדה חובה`;
            }
            
            // בדיקת אורך מינימלי
            else if (validator.minLength && value.length < validator.minLength) {
                isValid = false;
                errorMessage = validator.message || `שדה חייב להכיל לפחות ${validator.minLength} תווים`;
            }
            
            // בדיקת pattern
            else if (validator.pattern && !validator.pattern.test(value)) {
                isValid = false;
                errorMessage = validator.message || 'פורמט השדה אינו תקין';
            }

            // הצגת שגיאה או ניקוי
            if (!isValid) {
                this.showFieldError(field.element, errorMessage);
            } else {
                this.clearFieldError(field.element);
            }

            return isValid;
        },

        /**
         * הצגת שגיאה בשדה
         */
        showFieldError: function(element, message) {
            this.clearFieldError(element);
            
            element.classList.add('error');
            
            const errorElement = document.createElement('div');
            errorElement.className = 'field-error';
            errorElement.textContent = message;
            errorElement.style.cssText = `
                color: #dc2626;
                font-size: 0.875rem;
                margin-top: 0.25rem;
                direction: rtl;
            `;

            element.parentNode.appendChild(errorElement);
        },

        /**
         * ניקוי שגיאה מהשדה
         */
        clearFieldError: function(element) {
            element.classList.remove('error');
            
            const errorElement = element.parentNode.querySelector('.field-error');
            if (errorElement) {
                errorElement.remove();
            }
        },

        /**
         * קבלת תווית השדה
         */
        getFieldLabel: function(element) {
            const label = document.querySelector(`label[for="${element.id}"]`);
            if (label) {
                return label.textContent.replace('*', '').trim();
            }
            return element.name || 'השדה';
        },

        /**
         * טיפול בשליחת טופס
         */
        handleSubmit: function(form) {
            const formType = form.dataset.formType;
            const formConfig = this.forms.get(form.id);

            if (!formConfig) {
                this.gateway.utils.showMessage('שגיאה בטופס: הגדרות לא נמצאו', 'error');
                return;
            }

            // בדיקת תקינות כל השדות
            const isFormValid = this.validateForm(formConfig);
            
            if (!isFormValid) {
                this.gateway.utils.showMessage('יש לתקן את השגיאות בטופס', 'error');
                return;
            }

            // איסוף נתוני הטופס
            const formData = this.collectFormData(form);
            
            // הצגת אנימציית טעינה
            this.showLoadingState(form);

            // שליחת הטופס
            this.submitForm(formType, formData, form);
        },

        /**
         * בדיקת תקינות כל הטופס
         */
        validateForm: function(formConfig) {
            let isValid = true;

            Object.keys(formConfig.validators).forEach(fieldName => {
                const field = formConfig.fields[fieldName];
                const validator = formConfig.validators[fieldName];

                if (field && validator) {
                    const fieldValid = this.validateField(field, validator);
                    if (!fieldValid) {
                        isValid = false;
                    }
                }
            });

            return isValid;
        },

        /**
         * איסוף נתוני הטופס
         */
        collectFormData: function(form) {
            const formData = new FormData(form);
            const data = {};

            for (let [key, value] of formData.entries()) {
                // טיפול בcheckboxes
                if (form.querySelector(`input[name="${key}"][type="checkbox"]`)) {
                    data[key] = form.querySelector(`input[name="${key}"]:checked`) ? true : false;
                } else {
                    data[key] = value;
                }
            }

            return data;
        },

        /**
         * הצגת מצב טעינה
         */
        showLoadingState: function(form) {
            const submitButton = form.querySelector('button[type="submit"]');
            if (submitButton) {
                submitButton.disabled = true;
                submitButton.dataset.originalText = submitButton.textContent;
                submitButton.innerHTML = `
                    <svg class="animate-spin -ml-1 mr-3 h-5 w-5 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    שומר...
                `;
            }
        },

        /**
         * ביטול מצב טעינה
         */
        hideLoadingState: function(form) {
            const submitButton = form.querySelector('button[type="submit"]');
            if (submitButton && submitButton.dataset.originalText) {
                submitButton.disabled = false;
                submitButton.textContent = submitButton.dataset.originalText;
                delete submitButton.dataset.originalText;
            }
        },

        /**
         * שליחת הטופס לשרת
         */
        submitForm: function(formType, data, form) {
            const endpoint = this.getEndpointForFormType(formType);
            
            this.gateway.apiRequest(endpoint, {
                method: 'POST',
                body: JSON.stringify(data)
            })
            .then(response => {
                this.hideLoadingState(form);
                
                if (response.success) {
                    this.gateway.utils.showMessage('ההגדרות נשמרו בהצלחה!', 'success');
                    this.gateway.trigger('settingsSaved', { formType, data, response });
                } else {
                    this.gateway.utils.showMessage(response.message || 'שגיאה בשמירת ההגדרות', 'error');
                }
            })
            .catch(error => {
                this.hideLoadingState(form);
                this.gateway.utils.showMessage('שגיאה בתקשורת עם השרת', 'error');
                this.gateway.handleError('Form Submission', error);
            });
        },

        /**
         * קבלת endpoint לפי סוג הטופס
         */
        getEndpointForFormType: function(formType) {
            const endpoints = {
                'cardcom-settings': 'save-cardcom-settings',
                'maya-mobile-settings': 'save-maya-settings',
                'resellerclub-settings': 'save-resellerclub-settings',
                'general-settings': 'save-general-settings'
            };

            return endpoints[formType] || 'save-settings';
        },

        /**
         * קישור אירועים
         */
        bindEvents: function() {
            // טיפול בכפתורי איפוס
            document.addEventListener('click', (e) => {
                if (e.target.classList.contains('reset-form-btn')) {
                    e.preventDefault();
                    const form = e.target.closest('form');
                    if (form && confirm('האם לאפס את הטופס?')) {
                        this.resetForm(form);
                    }
                }
            });
        },

        /**
         * איפוס טופס
         */
        resetForm: function(form) {
            form.reset();
            
            // ניקוי שגיאות
            const errorElements = form.querySelectorAll('.field-error');
            errorElements.forEach(error => error.remove());
            
            const errorFields = form.querySelectorAll('.error');
            errorFields.forEach(field => field.classList.remove('error'));

            this.gateway.utils.showMessage('הטופס אופס', 'info');
        }
    };

    // חשיפת המודול
    if (window.PaymentGateway) {
        window.PaymentGateway.FormHandler = FormHandler;
    }

})(window);