/**
 * Connection Tester Module for Payment Gateway
 * מודול לבדיקת חיבורים לספקי שירות
 */

(function(window) {
    'use strict';

    const ConnectionTester = {
        gateway: null,
        testResults: new Map(),
        testInProgress: new Set(),

        /**
         * אתחול המודול
         */
        init: function(gateway) {
            this.gateway = gateway;
            this.bindEvents();
        },

        /**
         * בדיקת חיבור לספק ספציפי
         */
        test: function(provider) {
            if (this.testInProgress.has(provider)) {
                this.gateway.utils.showMessage(`בדיקה עבור ${provider} כבר בתהליך`, 'warning');
                return;
            }

            this.testInProgress.add(provider);
            this.updateTestUI(provider, 'testing');

            const testData = this.gatherTestData(provider);
            const endpoint = `test-${provider}-connection`;

            return this.gateway.apiRequest(endpoint, {
                method: 'POST',
                body: JSON.stringify(testData)
            })
            .then(response => {
                this.testInProgress.delete(provider);
                this.testResults.set(provider, {
                    success: response.success,
                    timestamp: Date.now(),
                    details: response
                });

                this.updateTestUI(provider, response.success ? 'success' : 'error');
                this.showTestResult(provider, response);

                return response;
            })
            .catch(error => {
                this.testInProgress.delete(provider);
                this.testResults.set(provider, {
                    success: false,
                    timestamp: Date.now(),
                    error: error.message
                });

                this.updateTestUI(provider, 'error');
                this.gateway.handleError(`Connection test for ${provider}`, error);
                throw error;
            });
        },

        /**
         * בדיקת כל החיבורים
         */
        testAll: function() {
            const providers = ['cardcom', 'maya-mobile', 'resellerclub'];
            const promises = providers.map(provider => {
                return this.test(provider).catch(error => {
                    return { provider, success: false, error: error.message };
                });
            });

            return Promise.allSettled(promises)
                .then(results => {
                    const summary = this.generateTestSummary(results);
                    this.showTestSummary(summary);
                    return summary;
                });
        },

        /**
         * איסוף נתונים לבדיקה
         */
        gatherTestData: function(provider) {
            const data = {};
            const formSelectors = {
                'cardcom': '#cardcom-settings-form',
                'maya-mobile': '#maya-mobile-settings-form',
                'resellerclub': '#resellerclub-settings-form'
            };

            const form = document.querySelector(formSelectors[provider]);
            if (form) {
                const formData = new FormData(form);
                for (let [key, value] of formData.entries()) {
                    data[key] = value;
                }
            }

            return data;
        },

        /**
         * עדכון ממשק המשתמש של הבדיקה
         */
        updateTestUI: function(provider, status) {
            const button = document.querySelector(`.test-connection-btn[data-provider="${provider}"]`);
            if (!button) return;

            // איפוס מחלקות קיימות
            button.classList.remove('testing', 'success', 'error');
            
            switch (status) {
                case 'testing':
                    button.classList.add('testing');
                    button.disabled = true;
                    button.innerHTML = `
                        <svg class="animate-spin h-4 w-4 inline-block ml-2" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        בודק...
                    `;
                    break;

                case 'success':
                    button.classList.add('success');
                    button.disabled = false;
                    button.innerHTML = `
                        <svg class="h-4 w-4 inline-block ml-2" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                        </svg>
                        מחובר
                    `;
                    break;

                case 'error':
                    button.classList.add('error');
                    button.disabled = false;
                    button.innerHTML = `
                        <svg class="h-4 w-4 inline-block ml-2" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"/>
                        </svg>
                        שגיאה
                    `;
                    break;
            }

            // עדכון timestamp
            const timestamp = button.parentNode.querySelector('.test-timestamp');
            if (timestamp && status !== 'testing') {
                timestamp.textContent = `נבדק: ${new Date().toLocaleTimeString('he-IL')}`;
            }
        },

        /**
         * הצגת תוצאת בדיקה
         */
        showTestResult: function(provider, response) {
            const providerNames = {
                'cardcom': 'CardCom',
                'maya-mobile': 'Maya Mobile',
                'resellerclub': 'ResellerClub'
            };

            const providerName = providerNames[provider] || provider;

            if (response.success) {
                let message = `✅ החיבור ל${providerName} בוצע בהצלחה!`;
                
                if (response.response_time) {
                    message += `\nזמן תגובה: ${response.response_time}ms`;
                }

                if (response.details) {
                    message += `\nפרטים נוספים זמינים בקונסול`;
                    console.log(`${providerName} connection details:`, response.details);
                }

                this.gateway.utils.showMessage(message, 'success');
            } else {
                const errorMessage = response.error || response.message || 'שגיאה לא מוגדרת';
                const message = `❌ שגיאה בחיבור ל${providerName}:\n${errorMessage}`;
                
                this.gateway.utils.showMessage(message, 'error');
            }
        },

        /**
         * יצירת סיכום בדיקות
         */
        generateTestSummary: function(results) {
            const summary = {
                total: results.length,
                successful: 0,
                failed: 0,
                details: []
            };

            results.forEach(result => {
                if (result.status === 'fulfilled' && result.value.success) {
                    summary.successful++;
                } else {
                    summary.failed++;
                }
                summary.details.push(result.value || result.reason);
            });

            return summary;
        },

        /**
         * הצגת סיכום בדיקות
         */
        showTestSummary: function(summary) {
            const message = `
📊 סיכום בדיקות חיבור:
• סה"כ בדיקות: ${summary.total}
• בדיקות מוצלחות: ${summary.successful}
• בדיקות נכשלות: ${summary.failed}

${summary.details.map(detail => 
    `${detail.provider || 'Unknown'}: ${detail.success ? '✅' : '❌'}`
).join('\n')}
            `.trim();

            this.gateway.utils.showMessage(message, summary.failed === 0 ? 'success' : 'warning');
        },

        /**
         * קבלת היסטוריית בדיקות
         */
        getTestHistory: function(provider = null) {
            if (provider) {
                return this.testResults.get(provider);
            }
            return Array.from(this.testResults.entries()).map(([provider, result]) => ({
                provider,
                ...result
            }));
        },

        /**
         * ניקוי היסטוריית בדיקות
         */
        clearTestHistory: function() {
            this.testResults.clear();
            this.gateway.utils.showMessage('היסטוריית בדיקות נוקתה', 'info');
        },

        /**
         * בדיקה מתוזמנת
         */
        schedulePeriodicTest: function(provider, intervalMinutes = 5) {
            const interval = intervalMinutes * 60 * 1000;
            
            const periodicTest = () => {
                if (!this.testInProgress.has(provider)) {
                    this.test(provider).catch(error => {
                        console.warn(`Periodic test failed for ${provider}:`, error);
                    });
                }
            };

            // בדיקה ראשונית
            periodicTest();
            
            // הגדרת בדיקות תקופתיות
            const intervalId = setInterval(periodicTest, interval);
            
            // החזרת פונקציה לביטול
            return () => clearInterval(intervalId);
        },

        /**
         * קישור אירועים
         */
        bindEvents: function() {
            // כפתור בדיקת כל החיבורים
            document.addEventListener('click', (e) => {
                if (e.target.classList.contains('test-all-connections-btn')) {
                    e.preventDefault();
                    this.testAll();
                }
            });

            // כפתור ניקוי היסטוריה
            document.addEventListener('click', (e) => {
                if (e.target.classList.contains('clear-test-history-btn')) {
                    e.preventDefault();
                    if (confirm('לנקות את היסטוריית הבדיקות?')) {
                        this.clearTestHistory();
                    }
                }
            });

            // טיפול בשינויים בטפסים
            document.addEventListener('input', (e) => {
                if (e.target.closest('.payment-gateway-form')) {
                    const form = e.target.closest('.payment-gateway-form');
                    const formType = form.dataset.formType;
                    
                    if (formType) {
                        const provider = formType.replace('-settings', '');
                        this.resetTestUI(provider);
                    }
                }
            });
        },

        /**
         * איפוס ממשק הבדיקה
         */
        resetTestUI: function(provider) {
            const button = document.querySelector(`.test-connection-btn[data-provider="${provider}"]`);
            if (button) {
                button.classList.remove('success', 'error');
                button.disabled = false;
                button.innerHTML = 'בדוק חיבור';
            }
        }
    };

    // חשיפת המודול
    if (window.PaymentGateway) {
        window.PaymentGateway.ConnectionTester = ConnectionTester;
    }

})(window);