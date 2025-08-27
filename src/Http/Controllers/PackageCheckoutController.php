<?php

namespace NMDigitalHub\PaymentGateway\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\View\View;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use NMDigitalHub\PaymentGateway\PaymentGatewayManager;
use NMDigitalHub\PaymentGateway\Services\CardComService;
use NMDigitalHub\PaymentGateway\Services\MayaMobileService;
use NMDigitalHub\PaymentGateway\Services\ResellerClubService;

/**
 * Package Checkout Controller - Based on eSIM Unified Checkout System
 * קונטרולר מאוחד לכל תהליכי Checkout של החבילה - מבוסס על מערכת ההזמנות המאוחדת של eSIM
 */
class PackageCheckoutController
{
    protected PaymentGatewayManager $manager;
    protected CardComService $cardcom;
    protected MayaMobileService $maya;
    protected ResellerClubService $resellerclub;

    public function __construct(
        PaymentGatewayManager $manager,
        CardComService $cardcom,
        MayaMobileService $maya,
        ResellerClubService $resellerclub
    ) {
        $this->manager = $manager;
        $this->cardcom = $cardcom;
        $this->maya = $maya;
        $this->resellerclub = $resellerclub;
    }

    /**
     * הצגת טופס הזמנה - נקודת כניסה יחידה עם Slug
     * Based on EsimCheckoutController::show()
     */
    public function show(string $packageSlug): View
    {
        try {
            // מציאת החבילה לפי Slug
            $package = $this->findPackageBySlug($packageSlug);
            
            if (!$package) {
                Log::warning('Package not found', ['slug' => $packageSlug]);
                abort(404, 'החבילה המבוקשת לא נמצאה');
            }

            // בדיקות זמינות
            if (!$this->isPackageAvailable($package)) {
                Log::warning('Attempt to checkout unavailable package', [
                    'package_slug' => $packageSlug,
                    'package_id' => $package['id'] ?? null
                ]);
                
                return redirect()->route('payment-gateway.packages.index')
                    ->with('error', 'החבילה שנבחרה אינה זמינה כרגע');
            }

            // הכנת נתונים לתצוגה - דומה ל-EsimDataService::getCheckoutData()
            $data = $this->prepareCheckoutData($package);

            return view('payment-gateway::checkout.checkout-with-slug', compact('package', 'data'));
            
        } catch (\Exception $e) {
            Log::error('Checkout page error', [
                'slug' => $packageSlug,
                'error' => $e->getMessage()
            ]);
            
            return redirect()->route('payment-gateway.packages.index')
                ->with('error', 'אירעה שגיאה בטעינת העמוד');
        }
    }

    /**
     * עיבוד ההזמנה - דומה ל-EsimCheckoutController::process()
     */
    public function process(Request $request): JsonResponse
    {
        try {
            // Validation
            $validated = $request->validate([
                'package_slug' => 'nullable|string',
                'package_id' => 'nullable|string',
                'customer_name' => 'required|string|max:255',
                'customer_email' => 'required|email|max:255',
                'customer_phone' => 'required|string|max:20',
                'customer_id' => 'nullable|string|size:9',
                'billing_address' => 'nullable|string|max:500',
                'payment_method' => 'required|string|in:cardcom',
                'terms_accepted' => 'accepted',
                
                // Service-specific fields (dynamic)
                'domain_name' => 'nullable|string|max:63',
                'domain_extension' => 'nullable|string|max:10',
                'privacy_protection' => 'nullable|boolean',
                'destination_country' => 'nullable|string|max:2',
                'travel_dates' => 'nullable|string|max:50',
                'site_name' => 'nullable|string|max:255',
                'admin_email' => 'nullable|email|max:255',
            ]);

            // מציאת החבילה
            $package = $this->findPackage($validated);
            
            if (!$package) {
                return response()->json([
                    'success' => false,
                    'message' => 'החבילה המבוקשת לא נמצאה'
                ], 404);
            }

            // יצירת הזמנה - מבוסס על EsimOrderService
            $orderData = $this->prepareOrderData($validated, $package);
            $order = $this->createOrder($orderData);

            // עיבוד תשלום
            $paymentResult = $this->processPayment($order, $validated);

            if ($paymentResult['success']) {
                // הצלחה - החזרת URL להמשך התהליך
                return response()->json([
                    'success' => true,
                    'order_id' => $order['id'],
                    'redirect_url' => $paymentResult['redirect_url'] ?? null,
                    'message' => 'ההזמנה נקלטה בהצלחה'
                ]);
            } else {
                // כישלון בתשלום
                return response()->json([
                    'success' => false,
                    'message' => $paymentResult['message'] ?? 'שגיאה בעיבוד התשלום'
                ], 400);
            }

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'נתונים לא תקינים',
                'errors' => $e->errors()
            ], 422);
            
        } catch (\Exception $e) {
            Log::error('Checkout process error', [
                'request_data' => $request->except(['card_number', 'card_cvv', 'card_holder']),
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'אירעה שגיאה בעיבוד ההזמנה. אנא נסה שוב.'
            ], 500);
        }
    }

    /**
     * מציאת חבילה לפי Slug
     */
    protected function findPackageBySlug(string $slug): ?array
    {
        try {
            // חיפוש בכל הספקים
            $providers = ['cardcom', 'maya_mobile', 'resellerclub'];
            
            foreach ($providers as $provider) {
                $service = $this->getProviderService($provider);
                if (!$service || !$service->isConfigured()) {
                    continue;
                }

                // כאן נוכל לחפש חבילות בגירסה מתקדמת
                // לעת עתה נחזיר דמי data
                if ($slug === 'demo-package') {
                    return $this->getDemoPackage($provider);
                }
            }

            return null;
            
        } catch (\Exception $e) {
            Log::error('Error finding package by slug', [
                'slug' => $slug,
                'error' => $e->getMessage()
            ]);
            
            return null;
        }
    }

    /**
     * מציאת חבילה לפי ID או Slug
     */
    protected function findPackage(array $validated): ?array
    {
        if (!empty($validated['package_slug'])) {
            return $this->findPackageBySlug($validated['package_slug']);
        }
        
        if (!empty($validated['package_id'])) {
            return $this->findPackageById($validated['package_id']);
        }
        
        return null;
    }

    /**
     * מציאת חבילה לפי ID
     */
    protected function findPackageById(string $id): ?array
    {
        // Implementation similar to findPackageBySlug but by ID
        if ($id === 'demo-package-id') {
            return $this->getDemoPackage('cardcom');
        }
        
        return null;
    }

    /**
     * בדיקת זמינות החבילה - מבוסס על MayaNetEsimProduct::isAvailableForOrder()
     */
    protected function isPackageAvailable(array $package): bool
    {
        return ($package['is_active'] ?? false) && 
               ($package['stock'] ?? 0) > 0 &&
               ($package['price'] ?? 0) > 0;
    }

    /**
     * הכנת נתונים לחזית - דומה ל-EsimDataService::getCheckoutData()
     */
    protected function prepareCheckoutData(array $package): array
    {
        $provider = $package['provider'] ?? 'unknown';
        
        return [
            'package' => $package,
            'provider_info' => $this->getProviderInfo($provider),
            'payment_methods' => $this->getAvailablePaymentMethods($provider),
            'currencies' => ['ILS', 'USD', 'EUR'],
            'countries' => $this->getCountriesList(),
            'meta' => [
                'csrf_token' => csrf_token(),
                'api_url' => route('payment-gateway.api.process-checkout'),
                'return_url' => route('payment-gateway.checkout.success'),
                'cancel_url' => route('payment-gateway.checkout.cancelled'),
            ]
        ];
    }

    /**
     * הכנת נתוני הזמנה - מבוסס על EsimOrderService::prepareOrderData()
     */
    protected function prepareOrderData(array $validated, array $package): array
    {
        return [
            'id' => Str::ulid()->toString(),
            'package_id' => $package['id'],
            'package_slug' => $package['slug'] ?? null,
            'package_name' => $package['name'],
            'provider' => $package['provider'],
            'category' => $package['category'] ?? 'service',
            
            // Customer data
            'customer_name' => $validated['customer_name'],
            'customer_email' => $validated['customer_email'],
            'customer_phone' => $validated['customer_phone'],
            'customer_id' => $validated['customer_id'] ?? null,
            'billing_address' => $validated['billing_address'] ?? null,
            
            // Pricing
            'base_price' => $package['price'],
            'setup_fee' => $package['setup_fee'] ?? 0,
            'discount' => $package['discount'] ?? 0,
            'tax_rate' => 0.17, // 17% VAT
            'currency' => $package['currency'] ?? 'ILS',
            
            // Service configuration
            'service_config' => $this->prepareServiceConfig($validated, $package),
            
            // Order metadata
            'status' => 'pending',
            'payment_method' => $validated['payment_method'],
            'created_at' => now()->toISOString(),
            'source' => 'payment_gateway_package'
        ];
    }

    /**
     * הכנת הגדרות השירות בהתאם לקטגוריה
     */
    protected function prepareServiceConfig(array $validated, array $package): array
    {
        $category = $package['category'] ?? 'service';
        
        switch ($category) {
            case 'domain':
                return [
                    'domain_name' => $validated['domain_name'] ?? null,
                    'domain_extension' => $validated['domain_extension'] ?? '.com',
                    'privacy_protection' => $validated['privacy_protection'] ?? false,
                    'nameservers' => $validated['nameservers'] ?? null,
                ];
                
            case 'hosting':
                return [
                    'site_name' => $validated['site_name'] ?? null,
                    'admin_email' => $validated['admin_email'] ?? $validated['customer_email'],
                ];
                
            case 'esim':
                return [
                    'destination_country' => $validated['destination_country'] ?? null,
                    'travel_dates' => $validated['travel_dates'] ?? null,
                ];
                
            default:
                return [];
        }
    }

    /**
     * יצירת הזמנה
     */
    protected function createOrder(array $orderData): array
    {
        // כאן נשמור את ההזמנה במסד נתונים או במטמון
        // לעת עתה נחזיר את הנתונים כפי שהם
        
        Log::info('Order created', [
            'order_id' => $orderData['id'],
            'package_name' => $orderData['package_name'],
            'customer_email' => $orderData['customer_email']
        ]);
        
        return $orderData;
    }

    /**
     * עיבוד תשלום - מבוסס על PaymentService
     */
    protected function processPayment(array $order, array $validated): array
    {
        try {
            $provider = $validated['payment_method'] ?? 'cardcom';
            $service = $this->getProviderService($provider);
            
            if (!$service || !$service->isConfigured()) {
                return [
                    'success' => false,
                    'message' => "ספק התשלום {$provider} אינו זמין כרגע"
                ];
            }

            // חישוב סכום סופי
            $baseAmount = $order['base_price'] + $order['setup_fee'] - $order['discount'];
            $taxAmount = $baseAmount * $order['tax_rate'];
            $totalAmount = $baseAmount + $taxAmount;

            // הכנת פרמטרי תשלום - דומה לCardComOpenFieldsService
            $paymentParams = [
                'amount' => $totalAmount,
                'currency_code' => $order['currency'] === 'ILS' ? 1 : 2, // CardCom format
                'product_name' => $order['package_name'],
                'customer_name' => $order['customer_name'],
                'customer_email' => $order['customer_email'],
                'success_url' => route('payment-gateway.checkout.success', ['order' => $order['id']]),
                'failed_url' => route('payment-gateway.checkout.failed', ['order' => $order['id']]),
                'webhook_url' => route('payment-gateway.webhook.cardcom'),
                'save_payment_method' => false, // לא שומרים כרטיס במצב זה
                'language' => app()->isLocale('he') ? 'he' : 'en',
            ];

            // יצירת session תשלום
            if ($provider === 'cardcom') {
                $result = $service->createPaymentSession($paymentParams);
                
                return [
                    'success' => true,
                    'redirect_url' => $result->redirect_url,
                    'session_id' => $result->session_id
                ];
            }

            return [
                'success' => false,
                'message' => 'ספק תשלום לא נתמך'
            ];
            
        } catch (\Exception $e) {
            Log::error('Payment processing error', [
                'order_id' => $order['id'],
                'error' => $e->getMessage()
            ]);
            
            return [
                'success' => false,
                'message' => 'שגיאה בעיבוד התשלום: ' . $e->getMessage()
            ];
        }
    }

    /**
     * קבלת שירות ספק
     */
    protected function getProviderService(string $provider): mixed
    {
        return match($provider) {
            'cardcom' => $this->cardcom,
            'maya_mobile', 'maya-mobile' => $this->maya,
            'resellerclub' => $this->resellerclub,
            default => null
        };
    }

    /**
     * קבלת מידע על ספק
     */
    protected function getProviderInfo(string $provider): array
    {
        $service = $this->getProviderService($provider);
        return $service ? $service->getProviderInfo() : [];
    }

    /**
     * קבלת אמצעי תשלום זמינים
     */
    protected function getAvailablePaymentMethods(string $provider): array
    {
        // כרגע רק CardCom זמין
        return [
            'cardcom' => [
                'name' => 'כרטיס אשראי',
                'icon' => '💳',
                'available' => $this->cardcom->isConfigured()
            ]
        ];
    }

    /**
     * קבלת רשימת מדינות
     */
    protected function getCountriesList(): array
    {
        return [
            'US' => 'ארצות הברית',
            'GB' => 'בריטניה',
            'FR' => 'צרפת',
            'DE' => 'גרמניה',
            'ES' => 'ספרד',
            'IT' => 'איטליה',
            'JP' => 'יפן',
            'AU' => 'אוסטרליה',
            'CA' => 'קנדה',
            'NL' => 'הולנד'
        ];
    }

    /**
     * קבלת חבילת דמו לבדיקות
     */
    protected function getDemoPackage(string $provider): array
    {
        return [
            'id' => 'demo-package-id',
            'slug' => 'demo-package',
            'name' => 'חבילת דמו - ' . $this->getProviderName($provider),
            'description' => 'חבילת דמו לבדיקת מערכת התשלום',
            'provider' => $provider,
            'category' => 'service',
            'price' => 99.99,
            'setup_fee' => 0,
            'discount' => 0,
            'currency' => 'ILS',
            'is_active' => true,
            'stock' => 100,
            'features' => [
                'תמיכה מלאה 24/7',
                'הקמה מהירה',
                'ביטול עד 30 יום'
            ],
            'icon' => '🎁',
            'provider_name' => $this->getProviderName($provider)
        ];
    }

    /**
     * קבלת שם ספק
     */
    protected function getProviderName(string $provider): string
    {
        return match($provider) {
            'cardcom' => 'CardCom',
            'maya_mobile', 'maya-mobile' => 'Maya Mobile',
            'resellerclub' => 'ResellerClub',
            default => ucfirst($provider)
        };
    }

    /**
     * עמוד הצלחה
     */
    public function success(Request $request): View
    {
        $orderId = $request->get('order');
        
        // כאן נטען את ההזמנה ממסד הנתונים
        // לעת עתה נחזיר עמוד הצלחה פשוט
        
        return view('payment-gateway::checkout.success', [
            'order_id' => $orderId,
            'message' => 'ההזמנה הושלמה בהצלחה!'
        ]);
    }

    /**
     * עמוד כישלון
     */
    public function failed(Request $request): View
    {
        $orderId = $request->get('order');
        
        return view('payment-gateway::checkout.failed', [
            'order_id' => $orderId,
            'message' => 'התשלום נכשל. אנא נסה שוב.'
        ]);
    }

    /**
     * עמוד ביטול
     */
    public function cancelled(Request $request): View
    {
        $orderId = $request->get('order');
        
        return view('payment-gateway::checkout.cancelled', [
            'order_id' => $orderId,
            'message' => 'התשלום בוטל על ידי המשתמש.'
        ]);
    }
}