<?php

namespace NMDigitalHub\PaymentGateway\Tests\Feature;

use NMDigitalHub\PaymentGateway\Tests\TestCase;
use NMDigitalHub\PaymentGateway\Models\PaymentToken;
use NMDigitalHub\PaymentGateway\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

/**
 * בדיקות אבטחה
 * מתמקדת בהגנה ואימותים
 */
class SecurityTest extends TestCase
{
    use RefreshDatabase;
    
    public function test_webhook_signature_validation()
    {
        $secret = 'test_webhook_secret';
        config(['payment-gateway.webhook_secret' => $secret]);
        
        $payload = json_encode([
            'transaction_id' => 'secure_webhook_123',
            'amount' => 150.00,
            'status' => 'completed'
        ]);
        
        $validSignature = hash_hmac('sha256', $payload, $secret);
        
        // בקשה עם חתימה תקינה
        $response = $this->postJson('/webhooks/payment/cardcom', json_decode($payload, true), [
            'X-Webhook-Signature' => 'sha256=' . $validSignature
        ]);
        
        $response->assertStatus(200);
        
        // בקשה עם חתימה לא תקינה
        $response = $this->postJson('/webhooks/payment/cardcom', json_decode($payload, true), [
            'X-Webhook-Signature' => 'sha256=invalid_signature'
        ]);
        
        $response->assertStatus(401);
        $response->assertJson(['error' => 'Invalid webhook signature']);
    }
    
    public function test_payment_data_encryption()
    {
        $user = User::factory()->create();
        
        // יצירת אסימון תשלום
        $token = PaymentToken::create([
            'user_id' => $user->id,
            'token' => 'sensitive_token_123',
            'card_last_4' => '4580',
            'card_holder_name' => 'יוסי כהן',
            'is_active' => true
        ]);
        
        // בדיקה שהנתונים הרגישים מוצפנים בבסיס הנתונים
        $rawToken = \DB::table('payment_tokens')->where('id', $token->id)->first();
        
        // הטוקן לא אמור להיות זהה לערך הגולמי בבסיס הנתונים
        $this->assertNotEquals('sensitive_token_123', $rawToken->token);
        
        // אבל כשאנחנו קוראים דרך המודל, הוא אמור להיות מפוענח
        $this->assertEquals('sensitive_token_123', $token->fresh()->token);
    }
    
    public function test_rate_limiting_on_payment_endpoints()
    {
        // ביצוע 12 בקשות (מעל הגבול של 10 לדקה)
        for ($i = 0; $i < 12; $i++) {
            $response = $this->post('/payment/rate-limit-test/process', [
                'amount' => 100.00,
                'currency' => 'ILS',
                'customer_email' => "rate{$i}@test.com"
            ]);
            
            if ($i < 10) {
                $response->assertStatus(200);
            } else {
                $response->assertStatus(429); // Too Many Requests
            }
        }
    }
    
    public function test_sql_injection_prevention()
    {
        // ניסיון SQL injection בפרמטרי תשלום
        $maliciousInput = "'; DROP TABLE payment_transactions; --";
        
        $response = $this->post('/payment/sql-test/process', [
            'amount' => 100.00,
            'currency' => 'ILS',
            'customer_name' => $maliciousInput,
            'customer_email' => 'test@example.com'
        ]);
        
        // הבקשה לא אמורה לגרום לשגיאת מסד נתונים
        $response->assertStatus(200);
        
        // בדיקה שהטבלה עדיין קיימת
        $this->assertTrue(\Schema::hasTable('payment_transactions'));
        
        // בדיקה שהקלט המזיק נוטרל
        $this->assertDatabaseHas('payment_transactions', [
            'customer_name' => $maliciousInput // נשמר כטקסט רגיל
        ]);
    }
    
    public function test_xss_prevention_in_views()
    {
        $user = User::factory()->create(['name' => '<script>alert("xss")</script>']);
        
        $response = $this->actingAs($user)
            ->get('/client/dashboard');
            
        $response->assertStatus(200);
        
        // בדיקה שהסקריפט לא מופיע בתגובה
        $response->assertDontSee('<script>alert("xss")</script>', false);
        
        // אבל הטקסט המוחרג אמור להופיע
        $response->assertSee('&lt;script&gt;alert("xss")&lt;/script&gt;', false);
    }
    
    public function test_csrf_protection_on_payment_forms()
    {
        // בקשה ללא CSRF token
        $response = $this->post('/payment/csrf-test/process', [
            'amount' => 100.00,
            'currency' => 'ILS'
        ]);
        
        $response->assertStatus(419); // CSRF Token Mismatch
    }
    
    public function test_authorization_for_payment_tokens()
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();
        
        // יצירת אסימון עבור משתמש ראשון
        $token = PaymentToken::create([
            'user_id' => $user1->id,
            'token' => 'user1_token',
            'card_last_4' => '1234',
            'is_active' => true
        ]);
        
        // ניסיון של משתמש שני לגשת לאסימון
        $response = $this->actingAs($user2)
            ->get("/client/payment-tokens/{$token->id}");
            
        $response->assertStatus(403); // Forbidden
        
        // משתמש ראשון אמור לגשת לאסימון שלו
        $response = $this->actingAs($user1)
            ->get("/client/payment-tokens/{$token->id}");
            
        $response->assertStatus(200);
    }
    
    public function test_secure_token_generation()
    {
        // בדיקה שהאסימונים שנוצרים הם אקראיים ובטוחים
        $tokens = [];
        
        for ($i = 0; $i < 10; $i++) {
            $response = $this->post('/payment/generate-test-token');
            $response->assertStatus(200);
            
            $data = $response->json();
            $tokens[] = $data['token'];
        }
        
        // בדיקה שאין אסימונים כפולים
        $this->assertEquals(count($tokens), count(array_unique($tokens)));
        
        // בדיקה שהאסימונים מספיק ארוכים (לפחות 32 תווים)
        foreach ($tokens as $token) {
            $this->assertGreaterThanOrEqual(32, strlen($token));
        }
    }
    
    public function test_payment_amount_tampering_prevention()
    {
        $originalAmount = 100.00;
        $tamperedAmount = 1.00;
        
        // יצירת session עם הסכום המקורי
        $session = $this->withSession(['payment_amount' => $originalAmount]);
        
        // ניסיון לשלוח סכום שונה בבקשה
        $response = $session->post('/payment/tamper-test/process', [
            'amount' => $tamperedAmount,
            'currency' => 'ILS',
            'customer_email' => 'tamper@test.com'
        ]);
        
        $response->assertStatus(422);
        $response->assertJson([
            'error' => 'Payment amount mismatch'
        ]);
    }
    
    public function test_sensitive_data_logging_prevention()
    {
        // ביצוע תשלום עם נתונים רגישים
        $this->post('/payment/logging-test/process', [
            'amount' => 200.00,
            'currency' => 'ILS',
            'card_number' => '4580458045804580',
            'card_cvv' => '123',
            'customer_email' => 'logging@test.com'
        ]);
        
        // בדיקה שנתונים רגישים לא נרשמו בלוג
        $logContent = file_get_contents(storage_path('logs/laravel.log'));
        
        $this->assertStringNotContainsString('4580458045804580', $logContent);
        $this->assertStringNotContainsString('123', $logContent); // CVV
    }
    
    public function test_admin_access_restriction()
    {
        $regularUser = User::factory()->create(['role' => 'user']);
        $adminUser = User::factory()->create(['role' => 'admin']);
        
        // משתמש רגיל לא אמור לגשת לאזור האדמין
        $response = $this->actingAs($regularUser)
            ->get('/admin/payment-pages');
            
        $response->assertStatus(403);
        
        // אדמין אמור לגשת
        $response = $this->actingAs($adminUser)
            ->get('/admin/payment-pages');
            
        $response->assertStatus(200);
    }
    
    public function test_session_security()
    {
        $user = User::factory()->create();
        
        // התחברות
        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'password'
        ]);
        
        // בדיקה שהsession כולל נתוני אבטחה
        $sessionData = session()->all();
        
        $this->assertArrayHasKey('_token', $sessionData);
        $this->assertArrayHasKey('login_web_' . sha1('web'), $sessionData);
        
        // בדיקה שהsession מתחדש לאחר התחברות
        $oldSessionId = session()->getId();
        
        $this->post('/login', [
            'email' => $user->email,
            'password' => 'password'
        ]);
        
        $newSessionId = session()->getId();
        $this->assertNotEquals($oldSessionId, $newSessionId);
    }
    
    public function test_webhook_replay_attack_prevention()
    {
        $payload = [
            'transaction_id' => 'replay_test_123',
            'amount' => 100.00,
            'status' => 'completed',
            'timestamp' => now()->toISOString()
        ];
        
        $signature = hash_hmac('sha256', json_encode($payload), 'webhook_secret');
        
        // שליחת webhook ראשונה
        $response1 = $this->postJson('/webhooks/payment/cardcom', $payload, [
            'X-Webhook-Signature' => 'sha256=' . $signature
        ]);
        
        $response1->assertStatus(200);
        
        // ניסיון לשלוח שוב את אותו webhook (replay attack)
        $response2 = $this->postJson('/webhooks/payment/cardcom', $payload, [
            'X-Webhook-Signature' => 'sha256=' . $signature
        ]);
        
        $response2->assertStatus(409); // Conflict - already processed
    }
}