{{-- טופס הגדרת ResellerClub --}}
<div class="space-y-6" dir="rtl">
    <div class="bg-white rounded-lg shadow p-6">
        <div class="flex items-center space-x-3 mb-4">
            <svg class="w-8 h-8 text-orange-600" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M4 4a2 2 0 00-2 2v8a2 2 0 002 2h12a2 2 0 002-2V6a2 2 0 00-2-2H4zm12 12V8l-4 4-4-4v8h8z" clip-rule="evenodd"/>
            </svg>
            <h2 class="text-xl font-bold text-gray-900">הגדרות ResellerClub</h2>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            {{-- מזהה משווק --}}
            <div class="space-y-2">
                <label for="rc_reseller_id" class="block text-sm font-medium text-gray-700">
                    מזהה משווק (Reseller ID) *
                </label>
                <input type="text" 
                       id="rc_reseller_id" 
                       name="reseller_id" 
                       class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-orange-500 focus:border-orange-500"
                       placeholder="123456"
                       required>
                <p class="text-xs text-gray-500">מזהה המשווק שקיבלת מResellerClub</p>
            </div>

            {{-- מפתח API --}}
            <div class="space-y-2">
                <label for="rc_api_key" class="block text-sm font-medium text-gray-700">
                    מפתח API *
                </label>
                <input type="password" 
                       id="rc_api_key" 
                       name="api_key" 
                       class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-orange-500 focus:border-orange-500"
                       placeholder="API Key"
                       required>
                <p class="text-xs text-gray-500">מפתח ה-API שקיבלת מResellerClub</p>
            </div>

            {{-- שם משתמש --}}
            <div class="space-y-2">
                <label for="rc_username" class="block text-sm font-medium text-gray-700">
                    שם משתמש
                </label>
                <input type="text" 
                       id="rc_username" 
                       name="username" 
                       class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-orange-500 focus:border-orange-500"
                       placeholder="Username">
                <p class="text-xs text-gray-500">שם המשתמש בResellerClub</p>
            </div>

            {{-- סיסמה --}}
            <div class="space-y-2">
                <label for="rc_password" class="block text-sm font-medium text-gray-700">
                    סיסמה
                </label>
                <input type="password" 
                       id="rc_password" 
                       name="password" 
                       class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-orange-500 focus:border-orange-500"
                       placeholder="Password">
                <p class="text-xs text-gray-500">הסיסמה שלך בResellerClub</p>
            </div>
        </div>

        {{-- כתובת API --}}
        <div class="mt-6 space-y-2">
            <label for="rc_api_url" class="block text-sm font-medium text-gray-700">
                כתובת API
            </label>
            <input type="url" 
                   id="rc_api_url" 
                   name="api_url" 
                   class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-orange-500 focus:border-orange-500"
                   value="https://httpapi.com"
                   placeholder="https://httpapi.com">
            <p class="text-xs text-gray-500">כתובת ה-API של ResellerClub</p>
        </div>

        {{-- הגדרות מתקדמות --}}
        <div class="mt-6 border-t pt-6">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">הגדרות מתקדמות</h3>
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                {{-- מצב בדיקות --}}
                <div class="space-y-2">
                    <label class="flex items-center">
                        <input type="checkbox" 
                               id="rc_test_mode" 
                               name="test_mode" 
                               class="rounded border-gray-300 text-orange-600 shadow-sm focus:border-orange-300 focus:ring focus:ring-orange-200 focus:ring-opacity-50">
                        <span class="mr-2 text-sm font-medium text-gray-700">מצב בדיקות</span>
                    </label>
                    <p class="text-xs text-gray-500">שימוש בשרת הבדיקות של ResellerClub</p>
                </div>

                {{-- חבילה מופעלת --}}
                <div class="space-y-2">
                    <label class="flex items-center">
                        <input type="checkbox" 
                               id="rc_enabled" 
                               name="enabled" 
                               checked
                               class="rounded border-gray-300 text-orange-600 shadow-sm focus:border-orange-300 focus:ring focus:ring-orange-200 focus:ring-opacity-50">
                        <span class="mr-2 text-sm font-medium text-gray-700">ResellerClub מופעל</span>
                    </label>
                    <p class="text-xs text-gray-500">האם להפעיל את שירותי ResellerClub</p>
                </div>
            </div>
        </div>

        {{-- שירותים נתמכים --}}
        <div class="mt-6 border-t pt-6">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">שירותים נתמכים</h3>
            
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                <div class="flex items-center space-x-2 p-3 bg-gray-50 rounded-md">
                    <svg class="w-5 h-5 text-green-600" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                    </svg>
                    <span class="text-sm font-medium text-gray-700">דומיינים</span>
                </div>
                
                <div class="flex items-center space-x-2 p-3 bg-gray-50 rounded-md">
                    <svg class="w-5 h-5 text-green-600" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                    </svg>
                    <span class="text-sm font-medium text-gray-700">אירוח</span>
                </div>
                
                <div class="flex items-center space-x-2 p-3 bg-gray-50 rounded-md">
                    <svg class="w-5 h-5 text-green-600" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                    </svg>
                    <span class="text-sm font-medium text-gray-700">תעודות SSL</span>
                </div>
                
                <div class="flex items-center space-x-2 p-3 bg-gray-50 rounded-md">
                    <svg class="w-5 h-5 text-green-600" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                    </svg>
                    <span class="text-sm font-medium text-gray-700">דואר אלקטרוני</span>
                </div>
            </div>
        </div>

        {{-- כפתורי פעולה --}}
        <div class="mt-6 flex justify-between items-center border-t pt-6">
            <button type="button" 
                    onclick="testResellerClubConnection()" 
                    class="bg-green-600 hover:bg-green-700 text-white font-medium py-2 px-4 rounded-md transition duration-200">
                בדוק חיבור
            </button>
            
            <div class="space-x-3">
                <button type="button" 
                        onclick="getResellerInfo()" 
                        class="bg-orange-600 hover:bg-orange-700 text-white font-medium py-2 px-4 rounded-md transition duration-200">
                    פרטי משווק
                </button>
                <button type="button" 
                        onclick="resetResellerClubSettings()" 
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
function testResellerClubConnection() {
    const data = {
        reseller_id: document.getElementById('rc_reseller_id').value,
        api_key: document.getElementById('rc_api_key').value,
        api_url: document.getElementById('rc_api_url').value,
        test_mode: document.getElementById('rc_test_mode').checked
    };

    fetch('/api/payment-gateway/test-resellerclub-connection', {
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
            alert('✅ החיבור ל-ResellerClub בוצע בהצלחה!');
        } else {
            alert('❌ שגיאה בחיבור ל-ResellerClub: ' + data.error);
        }
    })
    .catch(error => {
        alert('❌ שגיאה בבדיקת החיבור: ' + error.message);
    });
}

function getResellerInfo() {
    fetch('/api/payment-gateway/get-reseller-info', {
        method: 'GET',
        headers: {
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            const info = `
📊 פרטי משווק:
• שם: ${data.name || 'לא זמין'}
• חברה: ${data.company || 'לא זמין'}
• אימייל: ${data.email || 'לא זמין'}
• יתרה: ${data.balance || '0'} ${data.currency || 'USD'}
• מיקום: ${data.location || 'לא זמין'}
            `;
            alert(info);
        } else {
            alert('❌ שגיאה בקבלת פרטי המשווק: ' + data.error);
        }
    })
    .catch(error => {
        alert('❌ שגיאה בקבלת פרטי המשווק: ' + error.message);
    });
}

function resetResellerClubSettings() {
    if (confirm('האם אתה בטוח שברצונך לאפס את הגדרות ResellerClub?')) {
        document.getElementById('rc_reseller_id').value = '';
        document.getElementById('rc_api_key').value = '';
        document.getElementById('rc_username').value = '';
        document.getElementById('rc_password').value = '';
        document.getElementById('rc_api_url').value = 'https://httpapi.com';
        document.getElementById('rc_test_mode').checked = false;
        document.getElementById('rc_enabled').checked = true;
    }
}
</script>