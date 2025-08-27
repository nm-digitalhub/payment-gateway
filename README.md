# חבילת תשלומים מקצועית - NM Digital Hub

<div dir="rtl">

חבילה מקצועית לעיבוד תשלומים ב-Laravel עם תמיכה מלאה ב-CardCom, Maya Mobile, ו-ResellerClub.

## תכונות עיקריות

### 🚀 תכונות מתקדמות
- **CardCom API v11** - תמיכה מלאה ב-LowProfile עם 3D Secure
- **ניהול טוקנים** - שמירת כרטיסי אשראי בצורה מאובטחת
- **סנכרון קטלוגים** - עדכון אוטומטי של מוצרים מספקים
- **עמודי תשלום ציבוריים** - יצירת עמודי checkout מותאמים אישית
- **פאנלי Filament** - ניהול מלא דרך ממשק האדמין

### 🛡️ אבטחה
- תמיכה מלאה ב-PCI DSS
- אימות HMAC עבור webhooks
- הצפנת נתוני תשלום במאגר הנתונים
- תמיכה מלאה ב-3D Secure

### 🌍 תמיכה רב-לשונית
- עברית (RTL) - ברירת המחדל
- אנגלית
- צרפתית

## התקנה

### דרישות מערכת

- PHP 8.2+
- Laravel 11.0+ או 12.0+
- Filament v3
- MySQL/PostgreSQL/SQLite
- הרחבות PHP: json, curl, mbstring, openssl, pdo

### התקנה מהירה

```bash
# הוספת החבילה דרך Composer
composer require nmdigitalhub/payment-gateway

# התקנה אוטומטית מלאה
php artisan payment-gateway:install --demo --optimize

# או התקנה ידנית צעד אחר צעד
php artisan payment-gateway:install --verbose
```

### אפשרויות התקנה

```bash
# התקנה בכוח (עריפת הגדרות קיימות)
php artisan payment-gateway:install --force

# התקנה עם נתוני דמו
php artisan payment-gateway:install --demo

# התקנה ללא migrations
php artisan payment-gateway:install --skip-migrations

# התקנה עם אופטימיזציה
php artisan payment-gateway:install --optimize

# פרטי התקנה מלאים
php artisan payment-gateway:install --verbose
```

## הגדרת CardCom

### קבלת פרטי החיבור
1. פנה לשירות לקוחות של CardCom
2. בקש מספר טרמינל וממשק API
3. קבל את פרטי החיבור

### הגדרה ב-.env
```env
# CardCom Configuration
CARDCOM_TERMINAL_NUMBER=172204
CARDCOM_API_NAME=your_api_name
CARDCOM_API_PASSWORD=your_api_password
CARDCOM_TEST_MODE=false

# Payment Gateway
PAYMENT_GATEWAY_ENABLED=true
PAYMENT_GATEWAY_DEFAULT=cardcom
```

### הגדרת Webhooks
```bash
# URL לקבלת webhooks מ-CardCom
https://your-domain.com/webhooks/cardcom
```

## שימוש בחבילה

### יצירת תשלום פשוט

```php
use NMDigitalHub\PaymentGateway\Facades\Payment;

// יצירת תשלום בסיסי
$payment = Payment::amount(100)
    ->currency('ILS')
    ->customerEmail('customer@example.com')
    ->customerName('יוסי כהן')
    ->description('תשלום עבור מוצר')
    ->create();

// תוצאה: URL לדף התשלום של CardCom
return redirect($payment->checkout_url);
```

### יצירת תשלום מתקדם עם טוכן

```php
use NMDigitalHub\PaymentGateway\Facades\Payment;

$payment = Payment::amount(250.50)
    ->currency('ILS')
    ->customerEmail('customer@example.com')
    ->customerName('רחל לוי')
    ->customerPhone('0501234567')
    ->description('חידוש מנוי שנתי')
    ->savePaymentMethod() // שמירת כרטיס לעתיד
    ->successUrl('https://mysite.com/payment/success')
    ->failedUrl('https://mysite.com/payment/failed')
    ->webhookUrl('https://mysite.com/webhooks/cardcom')
    ->metadata(['order_id' => 123, 'user_id' => 456])
    ->create();
```

### תשלום עם טוכן שמור

```php
// קבלת טוכנים שמורים של המשתמש
$tokens = auth()->user()->paymentTokens()->active()->get();

// תשלום עם טוכן
$payment = Payment::useToken($tokenId)
    ->amount(99.99)
    ->cvv('123') // נדרש לאימות 3D
    ->description('תשלום חוזר')
    ->create();

// תוצאה: תשלום מיידי או הפנייה ל-3D Secure
if ($payment->requires_3ds) {
    return redirect($payment->three_ds_url);
}
```

## עבודה עם עמודי תשלום

### יצירת עמוד תשלום
```php
use NMDigitalHub\PaymentGateway\Models\PaymentPage;

$page = PaymentPage::create([
    'title' => 'תשלום עבור קורס',
    'slug' => 'course-payment',
    'description' => 'תשלום מאובטח עבור הקורס שלנו',
    'type' => 'checkout',
    'status' => 'published',
    'is_public' => true,
    'language' => 'he',
    'content' => [
        [
            'type' => 'heading',
            'data' => ['content' => 'תשלום מאובטח', 'level' => 'h1']
        ],
        [
            'type' => 'payment_form',
            'data' => ['allowed_methods' => ['cardcom']]
        ]
    ]
]);

// URL לעמוד התשלום: /payment/course-payment
```

### הצגת עמוד תשלום בנתיב

```php
// routes/web.php
use NMDigitalHub\PaymentGateway\Http\Controllers\CheckoutController;

Route::get('/payment/{slug}', [CheckoutController::class, 'showPaymentPage']);
Route::post('/payment/{slug}', [CheckoutController::class, 'processPayment']);
```

## ניהול דרך פאנל האדמין

### מעבר לפאנלים
- **פאנל האדמין**: `/admin/payment-transactions`
- **פאנל הלקוח**: `/client/payment-tokens`

### תכונות האדמין
- צפייה בעסקאות
- ניהול דפי תשלום  
- סנכרון קטלוגים
- ניהול ספקים
- דוחות ואנליטיקה

## פקודות Artisan

### פקודות ניהול
```bash
# בדיקת חיבור לספקים
php artisan payment-gateway:health-check

# סנכרון קטלוגים
php artisan payment-gateway:sync

# בדיקת ספק
php artisan payment-gateway:test cardcom

# יצירת עמוד תשלום
php artisan payment-gateway:create-page
```

### פקודות תחזוקה
```bash
# ניקוי טוכנים שפגו
php artisan payment-gateway:cleanup-tokens

# ייצוא עסקאות לדוח
php artisan payment-gateway:export --from="2024-01-01" --to="2024-12-31"

# גיבוי נתוני תשלום
php artisan payment-gateway:backup
```

## Webhooks

### הגדרת Webhook Handler

```php
use NMDigitalHub\PaymentGateway\Services\CardComLowProfileService;

Route::post('/webhooks/cardcom', function (Request $request) {
    $service = app(CardComLowProfileService::class);
    $transaction = $service->handleWebhook($request->all());
    
    if ($transaction && $transaction->status === 'success') {
        // עיבוד הזמנה מוצלחת
        $order = Order::where('reference', $transaction->reference)->first();
        $order->markAsCompleted();
        
        // שליחת מייל אישור
        Mail::to($transaction->customerEmail)
            ->send(new PaymentConfirmationMail($transaction));
    }
    
    return response('OK', 200);
});
```

## מודלים ומידע

### עבודה עם עסקאות
```php
use NMDigitalHub\PaymentGateway\Models\PaymentTransaction;

// קבלת עסקאות לפי משתמש
$transactions = PaymentTransaction::where('customer_email', 'user@example.com')
    ->whereIn('status', ['success', 'completed'])
    ->orderBy('created_at', 'desc')
    ->get();

// קבלת עסקה לפי מזהה
$transaction = PaymentTransaction::where('transaction_id', 'PAY-123')->first();

// סטטיסטיקות תשלומים
$stats = PaymentTransaction::selectRaw('
    COUNT(*) as total_transactions,
    SUM(CASE WHEN status = "success" THEN amount ELSE 0 END) as total_amount,
    AVG(amount) as average_amount
')->where('created_at', '>=', now()->subMonth())->first();
```

### עבודה עם טוכנים
```php
use App\Models\PaymentToken;

// קבלת טוכנים פעילים של משתמש
$tokens = PaymentToken::where('user_id', auth()->id())
    ->active()
    ->notExpired()
    ->get();

// הגדרת טוכן כברירת מחדל
$token->setAsDefault();

// ביטול טוכן
$token->deactivate();
```

## אבטחה ואופטימיזציה

### הגדרות אבטחה מומלצות
```php
// config/payment-gateway.php
return [
    'security' => [
        'encryption_key' => env('PAYMENT_GATEWAY_ENCRYPTION_KEY'),
        'hmac_secret' => env('PAYMENT_GATEWAY_HMAC_SECRET'),
        'session_timeout' => 1800, // 30 דקות
        'max_attempts' => 3,
        'lockout_duration' => 900, // 15 דקות
    ],
    
    'logging' => [
        'enabled' => true,
        'include_sensitive' => false, // לא לכלול נתונים רגישים
        'retention_days' => 30,
    ],
];
```

### אופטימיזציה ביצועים
```php
// שימוש ב-cache עבור שאילתות כבדות
$providers = Cache::remember('active_payment_providers', 3600, function () {
    return ServiceProvider::active()->get();
});

// אינדקסים מומלצים במאגר הנתונים
Schema::table('payment_transactions', function (Blueprint $table) {
    $table->index(['customer_email', 'status']);
    $table->index(['status', 'created_at']);
    $table->index(['provider', 'status']);
});
```

## פתרון בעיות נפוצות

### שגיאת חיבור ל-CardCom
```bash
# בדיקת פרטי החיבור
php artisan payment-gateway:test cardcom

# בדיקת הגדרות
php artisan config:cache
php artisan config:clear
```

### בעיות עם Webhooks
```bash
# בדיקת URL פנוי
curl -X POST https://your-domain.com/webhooks/cardcom

# בדיקת הגדרות HMAC
php artisan tinker
>>> config('payment-gateway.security.hmac_secret')
```

### בעיות עם Filament
```bash
# ניקוי cache של Filament
php artisan filament:cache-components
php artisan filament:optimize
```

## תמיכה ועזרה

### קישורים חשובים
- **תיעוד CardCom**: [developer.cardcom.solutions](https://developer.cardcom.solutions)
- **תיעוד Filament**: [filamentphp.com](https://filamentphp.com)
- **GitHub Issues**: [github.com/nmdigitalhub/payment-gateway/issues](https://github.com/nmdigitalhub/payment-gateway/issues)

### פקודות עזרה
```bash
# מידע על החבילה
php artisan payment-gateway:info

# רשימת פקודות זמינות  
php artisan payment-gateway:help

# בדיקת תקינות המערכת
php artisan payment-gateway:doctor
```

### יצירת קשר לתמיכה
- **אימייל**: support@nm-digitalhub.com
- **אתר**: https://nm-digitalhub.com
- **טלפון**: 03-1234567

## רישיון

החבילה מופצת תחת רישיון MIT. ראה [LICENSE](LICENSE) לפרטים נוספים.

---

פותחה בידי **NM Digital Hub** עם ❤️

</div>