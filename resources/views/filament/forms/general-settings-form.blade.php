{{-- טופס הגדרות כלליות של Payment Gateway --}}
<div class="space-y-6" dir="rtl">
    {{-- הגדרות בסיסיות --}}
    <div class="bg-white rounded-lg shadow p-6">
        <div class="flex items-center space-x-3 mb-4">
            <svg class="w-8 h-8 text-blue-600" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M11.49 3.17c-.38-1.56-2.6-1.56-2.98 0a1.532 1.532 0 01-2.286.948c-1.372-.836-2.942.734-2.106 2.106.54.886.061 2.042-.947 2.287-1.561.379-1.561 2.6 0 2.978a1.532 1.532 0 01.947 2.287c-.836 1.372.734 2.942 2.106 2.106a1.532 1.532 0 012.287.947c.379 1.561 2.6 1.561 2.978 0a1.533 1.533 0 012.287-.947c1.372.836 2.942-.734 2.106-2.106a1.533 1.533 0 01.947-2.287c1.561-.379 1.561-2.6 0-2.978a1.532 1.532 0 01-.947-2.287c.836-1.372-.734-2.942-2.106-2.106a1.532 1.532 0 01-2.287-.947zM10 13a3 3 0 100-6 3 3 0 000 6z" clip-rule="evenodd"/>
            </svg>
            <h2 class="text-xl font-bold text-gray-900">הגדרות כלליות</h2>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            {{-- תמיכה בRTL --}}
            <div class="space-y-2">
                <label class="flex items-center">
                    <input type="checkbox" 
                           id="rtl_support" 
                           name="rtl_support" 
                           checked
                           class="rounded border-gray-300 text-blue-600 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50">
                    <span class="mr-2 text-sm font-medium text-gray-700">תמיכה בעברית (RTL)</span>
                </label>
                <p class="text-xs text-gray-500">הפעל תמיכה בכיווניות ימין לשמאל</p>
            </div>

            {{-- ספק ברירת מחדל --}}
            <div class="space-y-2">
                <label for="default_provider" class="block text-sm font-medium text-gray-700">
                    ספק תשלום ברירת מחדל
                </label>
                <select id="default_provider" 
                        name="default_provider" 
                        class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500">
                    <option value="cardcom">CardCom</option>
                    <option value="maya_mobile">Maya Mobile</option>
                    <option value="resellerclub">ResellerClub</option>
                </select>
            </div>

            {{-- שפה ברירת מחדל --}}
            <div class="space-y-2">
                <label for="default_locale" class="block text-sm font-medium text-gray-700">
                    שפה ברירת מחדל
                </label>
                <select id="default_locale" 
                        name="default_locale" 
                        class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500">
                    <option value="he">עברית</option>
                    <option value="en">אנגלית</option>
                    <option value="fr">צרפתית</option>
                </select>
            </div>

            {{-- מטבע ברירת מחדל --}}
            <div class="space-y-2">
                <label for="default_currency" class="block text-sm font-medium text-gray-700">
                    מטבע ברירת מחדל
                </label>
                <select id="default_currency" 
                        name="default_currency" 
                        class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500">
                    <option value="ILS">שקל ישראלי (₪)</option>
                    <option value="USD">דולר אמריקני ($)</option>
                    <option value="EUR">יורו (€)</option>
                </select>
            </div>
        </div>
    </div>

    {{-- הגדרות אבטחה --}}
    <div class="bg-white rounded-lg shadow p-6">
        <div class="flex items-center space-x-3 mb-4">
            <svg class="w-8 h-8 text-red-600" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M5 9V7a5 5 0 0110 0v2a2 2 0 012 2v5a2 2 0 01-2 2H5a2 2 0 01-2-2v-5a2 2 0 012-2zm8-2v2H7V7a3 3 0 016 0z" clip-rule="evenodd"/>
            </svg>
            <h2 class="text-xl font-bold text-gray-900">הגדרות אבטחה</h2>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            {{-- אימות חתימת webhook --}}
            <div class="space-y-2">
                <label class="flex items-center">
                    <input type="checkbox" 
                           id="verify_webhook_signature" 
                           name="verify_signature" 
                           checked
                           class="rounded border-gray-300 text-red-600 shadow-sm focus:border-red-300 focus:ring focus:ring-red-200 focus:ring-opacity-50">
                    <span class="mr-2 text-sm font-medium text-gray-700">אמת חתימת Webhook</span>
                </label>
                <p class="text-xs text-gray-500">וודא שכל webhook מגיע מספק אמין</p>
            </div>

            {{-- הגבלת קצב --}}
            <div class="space-y-2">
                <label for="webhook_rate_limit" class="block text-sm font-medium text-gray-700">
                    הגבלת קצב Webhook
                </label>
                <input type="text" 
                       id="webhook_rate_limit" 
                       name="rate_limit" 
                       class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-red-500 focus:border-red-500"
                       value="60,1"
                       placeholder="60,1">
                <p class="text-xs text-gray-500">פורמט: מספר בקשות,דקות (60,1 = 60 בדקה)</p>
            </div>

            {{-- זמן קצוב לsession --}}
            <div class="space-y-2">
                <label for="session_timeout" class="block text-sm font-medium text-gray-700">
                    זמן קצוב לsession (דקות)
                </label>
                <input type="number" 
                       id="session_timeout" 
                       name="session_timeout" 
                       class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-red-500 focus:border-red-500"
                       value="30"
                       min="5"
                       max="120">
                <p class="text-xs text-gray-500">כמה זמן להשאיר session פעיל</p>
            </div>

            {{-- מספר ניסיונות מקסימלי --}}
            <div class="space-y-2">
                <label for="max_attempts" class="block text-sm font-medium text-gray-700">
                    ניסיונות מקסימליים
                </label>
                <input type="number" 
                       id="max_attempts" 
                       name="max_attempts" 
                       class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-red-500 focus:border-red-500"
                       value="3"
                       min="1"
                       max="10">
                <p class="text-xs text-gray-500">כמה ניסיונות תשלום לאפשר</p>
            </div>
        </div>
    </div>

    {{-- הגדרות ביצועים --}}
    <div class="bg-white rounded-lg shadow p-6">
        <div class="flex items-center space-x-3 mb-4">
            <svg class="w-8 h-8 text-green-600" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M3 4a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zm0 4a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zm0 4a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zm0 4a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1z" clip-rule="evenodd"/>
            </svg>
            <h2 class="text-xl font-bold text-gray-900">הגדרות ביצועים</h2>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            {{-- Cache מופעל --}}
            <div class="space-y-2">
                <label class="flex items-center">
                    <input type="checkbox" 
                           id="cache_enabled" 
                           name="cache_enabled" 
                           checked
                           class="rounded border-gray-300 text-green-600 shadow-sm focus:border-green-300 focus:ring focus:ring-green-200 focus:ring-opacity-50">
                    <span class="mr-2 text-sm font-medium text-gray-700">הפעל Cache</span>
                </label>
                <p class="text-xs text-gray-500">שימור נתונים למהירות גישה</p>
            </div>

            {{-- זמן TTL של Cache --}}
            <div class="space-y-2">
                <label for="cache_ttl" class="block text-sm font-medium text-gray-700">
                    Cache TTL (שעות)
                </label>
                <input type="number" 
                       id="cache_ttl" 
                       name="cache_ttl" 
                       class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-green-500 focus:border-green-500"
                       value="1"
                       min="0.1"
                       step="0.1">
            </div>

            {{-- סינכרון אוטומטי --}}
            <div class="space-y-2">
                <label class="flex items-center">
                    <input type="checkbox" 
                           id="auto_sync" 
                           name="auto_sync" 
                           class="rounded border-gray-300 text-green-600 shadow-sm focus:border-green-300 focus:ring focus:ring-green-200 focus:ring-opacity-50">
                    <span class="mr-2 text-sm font-medium text-gray-700">סינכרון אוטומטי</span>
                </label>
                <p class="text-xs text-gray-500">סנכרן חבילות ומחירים יומית</p>
            </div>
        </div>
    </div>

    {{-- הגדרות רישום --}}
    <div class="bg-white rounded-lg shadow p-6">
        <div class="flex items-center space-x-3 mb-4">
            <svg class="w-8 h-8 text-yellow-600" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M3 5a2 2 0 012-2h10a2 2 0 012 2v8a2 2 0 01-2 2h-2.22l.123.489.804.804A1 1 0 0113 18H7a1 1 0 01-.707-1.707l.804-.804L7.22 15H5a2 2 0 01-2-2V5zm5.771 7H5V5h10v7H8.771z" clip-rule="evenodd"/>
            </svg>
            <h2 class="text-xl font-bold text-gray-900">הגדרות רישום</h2>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            {{-- רישום מופעל --}}
            <div class="space-y-2">
                <label class="flex items-center">
                    <input type="checkbox" 
                           id="logging_enabled" 
                           name="logging_enabled" 
                           checked
                           class="rounded border-gray-300 text-yellow-600 shadow-sm focus:border-yellow-300 focus:ring focus:ring-yellow-200 focus:ring-opacity-50">
                    <span class="mr-2 text-sm font-medium text-gray-700">הפעל רישום</span>
                </label>
                <p class="text-xs text-gray-500">שמור לוגים של פעולות</p>
            </div>

            {{-- רמת רישום --}}
            <div class="space-y-2">
                <label for="log_level" class="block text-sm font-medium text-gray-700">
                    רמת רישום
                </label>
                <select id="log_level" 
                        name="log_level" 
                        class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-yellow-500 focus:border-yellow-500">
                    <option value="debug">Debug</option>
                    <option value="info" selected>Info</option>
                    <option value="warning">Warning</option>
                    <option value="error">Error</option>
                </select>
            </div>

            {{-- שמירת לוגים (ימים) --}}
            <div class="space-y-2">
                <label for="log_retention" class="block text-sm font-medium text-gray-700">
                    שמירת לוגים (ימים)
                </label>
                <input type="number" 
                       id="log_retention" 
                       name="retention_days" 
                       class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-yellow-500 focus:border-yellow-500"
                       value="30"
                       min="1"
                       max="365">
            </div>
        </div>
    </div>

    {{-- כפתורי פעולה --}}
    <div class="bg-white rounded-lg shadow p-6">
        <div class="flex justify-between items-center">
            <div class="space-x-3">
                <button type="button" 
                        onclick="testAllConnections()" 
                        class="bg-green-600 hover:bg-green-700 text-white font-medium py-2 px-4 rounded-md transition duration-200">
                    בדוק כל החיבורים
                </button>
                <button type="button" 
                        onclick="clearAllCache()" 
                        class="bg-yellow-600 hover:bg-yellow-700 text-white font-medium py-2 px-4 rounded-md transition duration-200">
                    נקה Cache
                </button>
            </div>
            
            <div class="space-x-3">
                <button type="button" 
                        onclick="resetAllSettings()" 
                        class="bg-red-600 hover:bg-red-700 text-white font-medium py-2 px-4 rounded-md transition duration-200">
                    איפוס כללי
                </button>
                <button type="submit" 
                        class="bg-blue-600 hover:bg-blue-700 text-white font-medium py-2 px-4 rounded-md transition duration-200">
                    שמור הגדרות
                </button>
            </div>
        </div>
    </div>
</div>

<script>
function testAllConnections() {
    if (confirm('לבדוק את החיבור לכל הספקים?')) {
        fetch('/api/payment-gateway/test-all-connections', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
            }
        })
        .then(response => response.json())
        .then(data => {
            let message = 'תוצאות בדיקת חיבורים:\n\n';
            data.results.forEach(result => {
                message += `${result.provider}: ${result.success ? '✅ מחובר' : '❌ נכשל - ' + result.error}\n`;
            });
            alert(message);
        })
        .catch(error => {
            alert('❌ שגיאה בבדיקת החיבורים: ' + error.message);
        });
    }
}

function clearAllCache() {
    if (confirm('לנקות את כל ה-Cache?')) {
        fetch('/api/payment-gateway/clear-cache', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('✅ Cache נוקה בהצלחה!');
            } else {
                alert('❌ שגיאה בניקוי Cache: ' + data.error);
            }
        })
        .catch(error => {
            alert('❌ שגיאה בניקוי Cache: ' + error.message);
        });
    }
}

function resetAllSettings() {
    if (confirm('⚠️ פעולה זו תאפס את כל ההגדרות! האם אתה בטוח?')) {
        if (confirm('⚠️ אזהרה אחרונה: כל הנתונים יאפסו ולא ניתן יהיה לשחזר אותם!')) {
            // Reset all form fields to defaults
            document.getElementById('rtl_support').checked = true;
            document.getElementById('default_provider').value = 'cardcom';
            document.getElementById('default_locale').value = 'he';
            document.getElementById('default_currency').value = 'ILS';
            document.getElementById('verify_webhook_signature').checked = true;
            document.getElementById('webhook_rate_limit').value = '60,1';
            document.getElementById('session_timeout').value = '30';
            document.getElementById('max_attempts').value = '3';
            document.getElementById('cache_enabled').checked = true;
            document.getElementById('cache_ttl').value = '1';
            document.getElementById('auto_sync').checked = false;
            document.getElementById('logging_enabled').checked = true;
            document.getElementById('log_level').value = 'info';
            document.getElementById('log_retention').value = '30';
        }
    }
}
</script>