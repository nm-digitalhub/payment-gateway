{{-- טופס הגדרת CardCom --}}
<div class="space-y-6" dir="rtl">
    <div class="bg-white rounded-lg shadow p-6">
        <div class="flex items-center space-x-3 mb-4">
            <svg class="w-8 h-8 text-blue-600" fill="currentColor" viewBox="0 0 20 20">
                <path d="M4 4a2 2 0 00-2 2v1h16V6a2 2 0 00-2-2H4z"/>
                <path fill-rule="evenodd" d="M18 9H2v5a2 2 0 002 2h12a2 2 0 002-2V9zM4 13a1 1 0 011-1h1a1 1 0 110 2H5a1 1 0 01-1-1zm5-1a1 1 0 100 2h1a1 1 0 100-2H9z" clip-rule="evenodd"/>
            </svg>
            <h2 class="text-xl font-bold text-gray-900">הגדרות CardCom</h2>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            {{-- מספר טרמינל --}}
            <div class="space-y-2">
                <label for="cardcom_terminal" class="block text-sm font-medium text-gray-700">
                    מספר טרמינל *
                </label>
                <input type="text" 
                       id="cardcom_terminal" 
                       name="terminal" 
                       class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500"
                       placeholder="172204"
                       required>
                <p class="text-xs text-gray-500">מספר הטרמינל שקיבלת מCardCom</p>
            </div>

            {{-- שם משתמש API --}}
            <div class="space-y-2">
                <label for="cardcom_username" class="block text-sm font-medium text-gray-700">
                    שם משתמש API *
                </label>
                <input type="text" 
                       id="cardcom_username" 
                       name="username" 
                       class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500"
                       placeholder="API Username"
                       required>
                <p class="text-xs text-gray-500">שם המשתמש לגישה ל-API של CardCom</p>
            </div>

            {{-- סיסמת API --}}
            <div class="space-y-2">
                <label for="cardcom_password" class="block text-sm font-medium text-gray-700">
                    סיסמת API *
                </label>
                <input type="password" 
                       id="cardcom_password" 
                       name="password" 
                       class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500"
                       placeholder="API Password"
                       required>
                <p class="text-xs text-gray-500">הסיסמה לגישה ל-API של CardCom</p>
            </div>

            {{-- מטבע --}}
            <div class="space-y-2">
                <label for="cardcom_currency" class="block text-sm font-medium text-gray-700">
                    מטבע ברירת מחדל
                </label>
                <select id="cardcom_currency" 
                        name="currency" 
                        class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500">
                    <option value="ILS">שקל ישראלי (ILS)</option>
                    <option value="USD">דולר אמריקני (USD)</option>
                    <option value="EUR">יורו (EUR)</option>
                </select>
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
                               id="cardcom_test_mode" 
                               name="test_mode" 
                               class="rounded border-gray-300 text-blue-600 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50">
                        <span class="mr-2 text-sm font-medium text-gray-700">מצב בדיקות (Sandbox)</span>
                    </label>
                    <p class="text-xs text-gray-500">השתמש במצב בדיקות לפיתוח ובדיקות</p>
                </div>

                {{-- חבילה מופעלת --}}
                <div class="space-y-2">
                    <label class="flex items-center">
                        <input type="checkbox" 
                               id="cardcom_enabled" 
                               name="enabled" 
                               checked
                               class="rounded border-gray-300 text-blue-600 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50">
                        <span class="mr-2 text-sm font-medium text-gray-700">CardCom מופעל</span>
                    </label>
                    <p class="text-xs text-gray-500">האם להפעיל את שירותי CardCom</p>
                </div>

                {{-- URL Webhook --}}
                <div class="space-y-2 md:col-span-2">
                    <label for="cardcom_webhook_url" class="block text-sm font-medium text-gray-700">
                        URL Webhook
                    </label>
                    <input type="url" 
                           id="cardcom_webhook_url" 
                           name="webhook_url" 
                           class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500"
                           placeholder="https://yoursite.com/cardcom/webhook">
                    <p class="text-xs text-gray-500">כתובת לקבלת התראות מCardCom</p>
                </div>
            </div>
        </div>

        {{-- כפתורי פעולה --}}
        <div class="mt-6 flex justify-between items-center border-t pt-6">
            <button type="button" 
                    onclick="testCardComConnection()" 
                    class="bg-green-600 hover:bg-green-700 text-white font-medium py-2 px-4 rounded-md transition duration-200">
                בדוק חיבור
            </button>
            
            <div class="space-x-3">
                <button type="button" 
                        onclick="resetCardComSettings()" 
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
function testCardComConnection() {
    const data = {
        terminal: document.getElementById('cardcom_terminal').value,
        username: document.getElementById('cardcom_username').value,
        password: document.getElementById('cardcom_password').value,
        test_mode: document.getElementById('cardcom_test_mode').checked
    };

    fetch('/api/payment-gateway/test-cardcom-connection', {
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
            alert('✅ החיבור ל-CardCom בוצע בהצלחה!');
        } else {
            alert('❌ שגיאה בחיבור ל-CardCom: ' + data.error);
        }
    })
    .catch(error => {
        alert('❌ שגיאה בבדיקת החיבור: ' + error.message);
    });
}

function resetCardComSettings() {
    if (confirm('האם אתה בטוח שברצונך לאפס את הגדרות CardCom?')) {
        document.getElementById('cardcom_terminal').value = '';
        document.getElementById('cardcom_username').value = '';
        document.getElementById('cardcom_password').value = '';
        document.getElementById('cardcom_test_mode').checked = false;
        document.getElementById('cardcom_enabled').checked = true;
        document.getElementById('cardcom_webhook_url').value = '';
    }
}
</script>