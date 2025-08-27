{{-- טופס הגדרת Maya Mobile --}}
<div class="space-y-6" dir="rtl">
    <div class="bg-white rounded-lg shadow p-6">
        <div class="flex items-center space-x-3 mb-4">
            <svg class="w-8 h-8 text-purple-600" fill="currentColor" viewBox="0 0 20 20">
                <path d="M2 3a1 1 0 011-1h2.153a1 1 0 01.986.836l.74 4.435a1 1 0 01-.54 1.06l-1.548.773a11.037 11.037 0 006.105 6.105l.774-1.548a1 1 0 011.059-.54l4.435.74a1 1 0 01.836.986V17a1 1 0 01-1 1h-2C7.82 18 2 12.18 2 5V3z"/>
            </svg>
            <h2 class="text-xl font-bold text-gray-900">הגדרות Maya Mobile</h2>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            {{-- מפתח API --}}
            <div class="space-y-2">
                <label for="maya_api_key" class="block text-sm font-medium text-gray-700">
                    מפתח API *
                </label>
                <input type="password" 
                       id="maya_api_key" 
                       name="api_key" 
                       class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-purple-500 focus:border-purple-500"
                       placeholder="API Key"
                       required>
                <p class="text-xs text-gray-500">מפתח ה-API שקיבלת מMaya Mobile</p>
            </div>

            {{-- סוד API --}}
            <div class="space-y-2">
                <label for="maya_api_secret" class="block text-sm font-medium text-gray-700">
                    סוד API *
                </label>
                <input type="password" 
                       id="maya_api_secret" 
                       name="api_secret" 
                       class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-purple-500 focus:border-purple-500"
                       placeholder="API Secret"
                       required>
                <p class="text-xs text-gray-500">הסוד לאימות מול Maya Mobile API</p>
            </div>

            {{-- URL API --}}
            <div class="space-y-2">
                <label for="maya_api_url" class="block text-sm font-medium text-gray-700">
                    כתובת API
                </label>
                <input type="url" 
                       id="maya_api_url" 
                       name="api_url" 
                       class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-purple-500 focus:border-purple-500"
                       value="https://api.maya.net"
                       placeholder="https://api.maya.net">
                <p class="text-xs text-gray-500">כתובת ה-API של Maya Mobile</p>
            </div>

            {{-- סביבת עבודה --}}
            <div class="space-y-2">
                <label for="maya_environment" class="block text-sm font-medium text-gray-700">
                    סביבת עבודה
                </label>
                <select id="maya_environment" 
                        name="environment" 
                        class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-purple-500 focus:border-purple-500">
                    <option value="production">ייצור (Production)</option>
                    <option value="sandbox">בדיקות (Sandbox)</option>
                </select>
            </div>
        </div>

        {{-- הגדרות עסקיות --}}
        <div class="mt-6 border-t pt-6">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">הגדרות עסקיות</h3>
            
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                {{-- אחוז רווח --}}
                <div class="space-y-2">
                    <label for="maya_markup" class="block text-sm font-medium text-gray-700">
                        אחוז רווח (%)
                    </label>
                    <input type="number" 
                           id="maya_markup" 
                           name="default_markup_percentage" 
                           class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-purple-500 focus:border-purple-500"
                           value="20.00"
                           min="0"
                           step="0.01">
                    <p class="text-xs text-gray-500">אחוז הרווח על מחירי Maya Mobile</p>
                </div>

                {{-- מטבע חיוב --}}
                <div class="space-y-2">
                    <label for="maya_currency" class="block text-sm font-medium text-gray-700">
                        מטבע חיוב
                    </label>
                    <select id="maya_currency" 
                            name="billing_currency" 
                            class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-purple-500 focus:border-purple-500">
                        <option value="ILS">שקל ישראלי (ILS)</option>
                        <option value="USD">דולר אמריקני (USD)</option>
                        <option value="EUR">יורו (EUR)</option>
                    </select>
                </div>

                {{-- חיוב מינימלי --}}
                <div class="space-y-2">
                    <label for="maya_min_charge" class="block text-sm font-medium text-gray-700">
                        חיוב מינימלי
                    </label>
                    <input type="number" 
                           id="maya_min_charge" 
                           name="min_charge" 
                           class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-purple-500 focus:border-purple-500"
                           value="1.00"
                           min="0"
                           step="0.01">
                    <p class="text-xs text-gray-500">הסכום המינימלי לחיוב</p>
                </div>
            </div>
        </div>

        {{-- הגדרות מתקדמות --}}
        <div class="mt-6 border-t pt-6">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">הגדרות מתקדמות</h3>
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                {{-- מצב בדיקות --}}
                <div class="space-y-2">
                    <label class="flex items-center">
                        <input type="checkbox" 
                               id="maya_test_mode" 
                               name="test_mode" 
                               class="rounded border-gray-300 text-purple-600 shadow-sm focus:border-purple-300 focus:ring focus:ring-purple-200 focus:ring-opacity-50">
                        <span class="mr-2 text-sm font-medium text-gray-700">מצב בדיקות</span>
                    </label>
                    <p class="text-xs text-gray-500">השתמש ב-API בדיקות לפיתוח</p>
                </div>

                {{-- חיוב אוטומטי --}}
                <div class="space-y-2">
                    <label class="flex items-center">
                        <input type="checkbox" 
                               id="maya_auto_billing" 
                               name="auto_billing" 
                               checked
                               class="rounded border-gray-300 text-purple-600 shadow-sm focus:border-purple-300 focus:ring focus:ring-purple-200 focus:ring-opacity-50">
                        <span class="mr-2 text-sm font-medium text-gray-700">חיוב אוטומטי</span>
                    </label>
                    <p class="text-xs text-gray-500">חיוב אוטומטי עבור הזמנות</p>
                </div>

                {{-- הפעלה אוטומטית --}}
                <div class="space-y-2">
                    <label class="flex items-center">
                        <input type="checkbox" 
                               id="maya_auto_provision" 
                               name="auto_provision" 
                               checked
                               class="rounded border-gray-300 text-purple-600 shadow-sm focus:border-purple-300 focus:ring focus:ring-purple-200 focus:ring-opacity-50">
                        <span class="mr-2 text-sm font-medium text-gray-700">הפעלה אוטומטית</span>
                    </label>
                    <p class="text-xs text-gray-500">הפעלה אוטומטית של eSIM לאחר תשלום</p>
                </div>

                {{-- Maya Mobile מופעל --}}
                <div class="space-y-2">
                    <label class="flex items-center">
                        <input type="checkbox" 
                               id="maya_enabled" 
                               name="enabled" 
                               checked
                               class="rounded border-gray-300 text-purple-600 shadow-sm focus:border-purple-300 focus:ring focus:ring-purple-200 focus:ring-opacity-50">
                        <span class="mr-2 text-sm font-medium text-gray-700">Maya Mobile מופעל</span>
                    </label>
                    <p class="text-xs text-gray-500">האם להפעיל את שירותי Maya Mobile</p>
                </div>
            </div>
        </div>

        {{-- כפתורי פעולה --}}
        <div class="mt-6 flex justify-between items-center border-t pt-6">
            <button type="button" 
                    onclick="testMayaConnection()" 
                    class="bg-green-600 hover:bg-green-700 text-white font-medium py-2 px-4 rounded-md transition duration-200">
                בדוק חיבור
            </button>
            
            <div class="space-x-3">
                <button type="button" 
                        onclick="syncMayaPackages()" 
                        class="bg-purple-600 hover:bg-purple-700 text-white font-medium py-2 px-4 rounded-md transition duration-200">
                    סנכרן חבילות
                </button>
                <button type="button" 
                        onclick="resetMayaSettings()" 
                        class="bg-gray-300 hover:bg-gray-400 text-gray-800 font-medium py-2 px-4 rounded-md transition duration-200">
                    איפוס
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
function testMayaConnection() {
    const data = {
        api_key: document.getElementById('maya_api_key').value,
        api_secret: document.getElementById('maya_api_secret').value,
        api_url: document.getElementById('maya_api_url').value,
        test_mode: document.getElementById('maya_test_mode').checked
    };

    fetch('/api/payment-gateway/test-maya-connection', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
        },
        body: JSON.stringify(data)
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('✅ החיבור ל-Maya Mobile בוצע בהצלחה!');
        } else {
            alert('❌ שגיאה בחיבור ל-Maya Mobile: ' + data.error);
        }
    })
    .catch(error => {
        alert('❌ שגיאה בבדיקת החיבור: ' + error.message);
    });
}

function syncMayaPackages() {
    if (confirm('האם לסנכרן חבילות מMaya Mobile?')) {
        fetch('/api/payment-gateway/sync-maya-packages', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('✅ סינכרון חבילות הושלם! סונכרנו ' + data.synced + ' חבילות.');
            } else {
                alert('❌ שגיאה בסינכרון: ' + data.error);
            }
        })
        .catch(error => {
            alert('❌ שגיאה בסינכרון: ' + error.message);
        });
    }
}

function resetMayaSettings() {
    if (confirm('האם אתה בטוח שברצונך לאפס את הגדרות Maya Mobile?')) {
        document.getElementById('maya_api_key').value = '';
        document.getElementById('maya_api_secret').value = '';
        document.getElementById('maya_api_url').value = 'https://api.maya.net';
        document.getElementById('maya_environment').value = 'production';
        document.getElementById('maya_markup').value = '20.00';
        document.getElementById('maya_currency').value = 'ILS';
        document.getElementById('maya_min_charge').value = '1.00';
        document.getElementById('maya_test_mode').checked = false;
        document.getElementById('maya_auto_billing').checked = true;
        document.getElementById('maya_auto_provision').checked = true;
        document.getElementById('maya_enabled').checked = true;
    }
}
</script>