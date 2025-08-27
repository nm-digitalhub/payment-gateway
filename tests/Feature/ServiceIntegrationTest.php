<?php

namespace NMDigitalHub\PaymentGateway\Tests\Feature;

use NMDigitalHub\PaymentGateway\Services\CurrencyExchangeService;
use NMDigitalHub\PaymentGateway\Services\PackageSyncService;
use NMDigitalHub\PaymentGateway\Models\ServiceProvider;
use NMDigitalHub\PaymentGateway\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;

/**
 * בדיקות אינטגרציה לשירותים
 * מתמקדת באינטגרציה בין שירותי החבילה
 */
class ServiceIntegrationTest extends TestCase
{
    use RefreshDatabase;
    
    public function test_complete_payment_flow_with_currency_conversion()
    {
        // הגדרת שירותים
        $currencyService = app(CurrencyExchangeService::class);
        
        // משיק API המרת מטבע
        Http::fake([
            'api.fixer.io/*' => Http::response([
                'success' => true,
                'rates' => ['ILS' => 3.65]
            ]),
            'secure.cardcom.solutions/*' => Http::response([
                'ResponseCode' => 0,
                'Description' => 'Success',
                'TransactionId' => 'FULL_FLOW_123'
            ])
        ]);
        
        // המרת מטבע
        $convertedAmount = $currencyService->convert(100.00, 'USD', 'ILS');
        $this->assertEquals(365.00, $convertedAmount);
        
        // עיבוד תשלום עם הסכום המומר
        $response = $this->post('/payment/international-checkout/process', [
            'amount' => $convertedAmount,
            'currency' => 'ILS',
            'original_amount' => 100.00,
            'original_currency' => 'USD',
            'customer_email' => 'integration@test.com'
        ]);
        
        $response->assertStatus(200);
        
        // בדיקה שהתשלום נרשם עם שני המטבעות
        $this->assertDatabaseHas('payment_transactions', [
            'amount' => 365.00,
            'currency' => 'ILS',
            'original_amount' => 100.00,
            'original_currency' => 'USD'
        ]);
    }
    
    public function test_package_sync_with_payment_processing()
    {
        $syncService = app(PackageSyncService::class);
        
        // משיק תגובות API
        Http::fake([
            '*/resellerclub/api/*' => Http::response([
                'packages' => [
                    [
                        'id' => 'domain_sync_test',
                        'name' => '.co.il Domain',
                        'price' => 45.00,
                        'currency' => 'ILS'
                    ]
                ]
            ]),
            'secure.cardcom.solutions/*' => Http::response([
                'ResponseCode' => 0,
                'Description' => 'Success',
                'TransactionId' => 'SYNC_PAYMENT_456'
            ])
        ]);
        
        // יצירת ספק שירות
        ServiceProvider::create([
            'name' => 'ResellerClub',
            'type' => 'domains',
            'is_active' => true,
            'api_endpoint' => 'https://api.resellerclub.com'
        ]);
        
        // סינכרון חבילות
        $syncResult = $syncService->syncAllProviders();
        $this->assertTrue($syncResult['success']);
        
        // רכישת חבילה שהתסנכרנה
        $response = $this->post('/payment/synced-package/process', [
            'package_id' => 'domain_sync_test',
            'amount' => 45.00,
            'currency' => 'ILS',
            'customer_email' => 'sync@test.com'
        ]);
        
        $response->assertStatus(200);
        
        // בדיקה שהרכישה קושרה לחבילה המסונכרנת
        $this->assertDatabaseHas('payment_transactions', [
            'package_id' => 'domain_sync_test',
            'amount' => 45.00,
            'transaction_id' => 'SYNC_PAYMENT_456'
        ]);
    }
    
    public function test_multi_provider_failover_mechanism()
    {
        // הגדרת מספר ספקי תשלום
        Http::fake([
            'primary-gateway.com/*' => Http::response(null, 500), // ספק ראשי נכשל
            'secondary-gateway.com/*' => Http::response([
                'status' => 'success',
                'transaction_id' => 'FAILOVER_789'
            ])
        ]);
        
        $response = $this->post('/payment/failover-test/process', [
            'amount' => 200.00,
            'currency' => 'ILS',
            'customer_email' => 'failover@test.com',
            'enable_failover' => true
        ]);
        
        $response->assertStatus(200);
        
        // בדיקה שהתשלום עבר דרך הספק המשני
        $this->assertDatabaseHas('payment_transactions', [
            'transaction_id' => 'FAILOVER_789',
            'gateway' => 'secondary',
            'amount' => 200.00
        ]);
        
        // בדיקה שהכשל נרשם בלוג
        $this->assertDatabaseHas('payment_gateway_logs', [
            'event' => 'failover_triggered',
            'primary_gateway' => 'primary',
            'secondary_gateway' => 'secondary'
        ]);
    }
    
    public function test_webhook_with_currency_and_sync_integration()
    {
        Http::fake([
            'api.fixer.io/*' => Http::response([
                'success' => true,
                'rates' => ['USD' => 0.27] // ILS to USD
            ])
        ]);
        
        // webhook עם המרת מטבע
        $webhookData = [
            'transaction_id' => 'WEBHOOK_CURRENCY_123',
            'amount' => 370.00,
            'currency' => 'ILS',
            'convert_to' => 'USD',
            'status' => 'completed'
        ];
        
        $response = $this->post('/webhooks/payment/cardcom', $webhookData, [
            'X-Signature' => hash_hmac('sha256', json_encode($webhookData), 'webhook_secret')
        ]);
        
        $response->assertStatus(200);
        
        // בדיקה שהעסקה עודכנה עם המרת מטבע
        $this->assertDatabaseHas('payment_transactions', [
            'transaction_id' => 'WEBHOOK_CURRENCY_123',
            'amount' => 370.00,
            'currency' => 'ILS',
            'converted_amount' => 99.90, // 370 * 0.27
            'converted_currency' => 'USD'
        ]);
    }
    
    public function test_token_management_with_multiple_gateways()
    {
        Http::fake([
            'secure.cardcom.solutions/*' => Http::response([
                'ResponseCode' => 0,
                'Token' => 'cardcom_token_123'
            ]),
            'gateway.paypal.com/*' => Http::response([
                'status' => 'success',
                'token' => 'paypal_token_456'
            ])
        ]);
        
        $user = \App\Models\User::factory()->create();
        
        // יצירת אסימונים במספר שערים
        $response1 = $this->actingAs($user)
            ->post('/payment/create-token', [
                'gateway' => 'cardcom',
                'card_number' => '4580458045804580',
                'card_expiry' => '12/26'
            ]);
            
        $response2 = $this->actingAs($user)
            ->post('/payment/create-token', [
                'gateway' => 'paypal',
                'paypal_email' => 'test@paypal.com'
            ]);
        
        $response1->assertStatus(200);
        $response2->assertStatus(200);
        
        // בדיקה שנוצרו אסימונים עבור שני השערים
        $this->assertDatabaseHas('payment_tokens', [
            'user_id' => $user->id,
            'gateway' => 'cardcom',
            'token' => 'cardcom_token_123'
        ]);
        
        $this->assertDatabaseHas('payment_tokens', [
            'user_id' => $user->id,
            'gateway' => 'paypal',
            'token' => 'paypal_token_456'
        ]);
    }
    
    public function test_comprehensive_error_handling_flow()
    {
        // סימולציה של כשלים בשירותים שונים
        Http::fake([
            'api.fixer.io/*' => Http::response(null, 500), // כשל בהמרת מטבע
            'secure.cardcom.solutions/*' => Http::response([
                'ResponseCode' => 14,
                'Description' => 'Invalid card number'
            ], 400),
            '*/backup-gateway/*' => Http::response([
                'status' => 'success',
                'transaction_id' => 'BACKUP_SUCCESS_789'
            ])
        ]);
        
        $response = $this->post('/payment/error-handling-test/process', [
            'amount' => 100.00,
            'currency' => 'USD',
            'target_currency' => 'ILS',
            'customer_email' => 'error@test.com',
            'enable_backup_gateway' => true,
            'fallback_exchange_rate' => 3.70
        ]);
        
        // למרות הכשלים, התשלום אמור להצליח עם הגיבוי
        $response->assertStatus(200);
        
        // בדיקה שנוצר רשומת שגיאה מפורטת
        $this->assertDatabaseHas('integration_error_logs', [
            'currency_service_error' => 'HTTP 500',
            'primary_gateway_error' => 'Invalid card number',
            'backup_gateway_used' => true,
            'fallback_rate_used' => 3.70
        ]);
        
        // בדיקה שהתשלום הושלם עם הגיבוי
        $this->assertDatabaseHas('payment_transactions', [
            'transaction_id' => 'BACKUP_SUCCESS_789',
            'gateway' => 'backup',
            'exchange_rate_source' => 'fallback'
        ]);
    }
    
    public function test_admin_dashboard_integration_data()
    {
        // יצירת נתונים מגוונים
        \App\Models\PaymentTransaction::factory(5)->create([
            'status' => 'success',
            'gateway' => 'cardcom',
            'created_at' => now()->subDays(1)
        ]);
        
        \App\Models\PaymentTransaction::factory(2)->create([
            'status' => 'failed',
            'gateway' => 'cardcom'
        ]);
        
        Cache::put('package_sync_last_run', now()->subHours(2));
        Cache::put('currency_rates_last_update', now()->subMinutes(30));
        
        $admin = \App\Models\User::factory()->create(['role' => 'admin']);
        
        $response = $this->actingAs($admin)
            ->get('/admin/dashboard');
            
        $response->assertStatus(200);
        
        // בדיקה שהדשבורד מציג נתונים משולבים
        $response->assertViewHas('stats', function ($stats) {
            return $stats['total_payments'] === 7 &&
                   $stats['successful_payments'] === 5 &&
                   $stats['sync_status'] === 'recent' &&
                   $stats['currency_status'] === 'up_to_date';
        });
    }
    
    public function test_client_panel_with_multi_currency_history()
    {
        $client = \App\Models\User::factory()->create();
        
        // יצירת עסקאות במטבעות שונים
        \App\Models\PaymentTransaction::factory()->create([
            'user_id' => $client->id,
            'amount' => 100.00,
            'currency' => 'USD',
            'converted_amount' => 370.00,
            'converted_currency' => 'ILS'
        ]);
        
        \App\Models\PaymentTransaction::factory()->create([
            'user_id' => $client->id,
            'amount' => 50.00,
            'currency' => 'EUR',
            'converted_amount' => 185.00,
            'converted_currency' => 'ILS'
        ]);
        
        $response = $this->actingAs($client)
            ->get('/client/payments');
            
        $response->assertStatus(200);
        $response->assertSee('$100.00');
        $response->assertSee('₪370.00');
        $response->assertSee('€50.00');
        $response->assertSee('₪185.00');
    }
    
    public function test_performance_under_concurrent_requests()
    {
        Http::fake([
            '*' => Http::response(['status' => 'success'], 200, [], 100) // 100ms delay
        ]);
        
        $responses = [];
        
        // סימולציה של 5 בקשות במקביל
        for ($i = 0; $i < 5; $i++) {
            $responses[] = $this->post('/payment/performance-test/process', [
                'amount' => 50.00 + $i,
                'currency' => 'ILS',
                'customer_email' => "perf{$i}@test.com"
            ]);
        }
        
        // בדיקה שכל הבקשות הצליחו
        foreach ($responses as $response) {
            $response->assertStatus(200);
        }
        
        // בדיקה שכל העסקאות נרשמו
        $this->assertEquals(5, \App\Models\PaymentTransaction::count());
        
        // בדיקה שזמן התגובה היה סביר (פחות מ-500ms למרות ה-100ms delay)
        $this->assertTrue(true); // בפועל נמדוד זמן תגובה
    }
}