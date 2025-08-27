<?php

namespace NMDigitalHub\PaymentGateway\Tests\Feature;

use NMDigitalHub\PaymentGateway\Models\WebhookLog;
use NMDigitalHub\PaymentGateway\Models\PaymentTransaction;
use NMDigitalHub\PaymentGateway\Http\Controllers\WebhookController;
use NMDigitalHub\PaymentGateway\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

/**
 * בדיקות Webhook Idempotency
 * מתמקדת במניעת עיבוד כפול
 */
class WebhookIdempotencyTest extends TestCase
{
    use RefreshDatabase;
    
    public function test_webhook_processes_only_once_with_same_id()
    {
        $webhookId = 'webhook_' . Str::uuid();
        $transactionId = 'txn_' . Str::uuid();
        
        $webhookData = [
            'webhook_id' => $webhookId,
            'transaction_id' => $transactionId,
            'amount' => 100.00,
            'status' => 'success',
            'timestamp' => now()->toISOString()
        ];
        
        // שליחת webhook ראשונה
        $response1 = $this->postJson('/webhooks/payment', $webhookData);
        $response1->assertStatus(200);
        
        // בדיקה שעסקה ניצרה
        $this->assertDatabaseHas('payment_transactions', [
            'transaction_id' => $transactionId,
            'status' => 'success'
        ]);
        
        // שליחת webhook כפולה עם אותו ID
        $response2 = $this->postJson('/webhooks/payment', $webhookData);
        $response2->assertStatus(200);
        
        // בדיקה שרק עסקה אחת ניצרה
        $transactionCount = PaymentTransaction::where('transaction_id', $transactionId)->count();
        $this->assertEquals(1, $transactionCount);
        
        // בדיקה שwebhook נרשם שתי פעמים בלוג
        $webhookCount = WebhookLog::where('webhook_id', $webhookId)->count();
        $this->assertEquals(2, $webhookCount);
    }
    
    public function test_webhook_idempotency_key_is_stored_correctly()
    {
        $webhookId = 'idempotent_' . Str::uuid();
        
        $webhookData = [
            'webhook_id' => $webhookId,
            'transaction_id' => 'txn_12345',
            'amount' => 50.00,
            'status' => 'pending'
        ];
        
        $response = $this->postJson('/webhooks/payment', $webhookData);
        $response->assertStatus(200);
        
        // בדיקה שwebhook log נשמר עם המידע הנכון
        $this->assertDatabaseHas('webhook_logs', [
            'webhook_id' => $webhookId,
            'processed' => true,
            'response_status' => 200
        ]);
    }
    
    public function test_different_webhook_ids_process_separately()
    {
        $webhookId1 = 'webhook_1_' . Str::uuid();
        $webhookId2 = 'webhook_2_' . Str::uuid();
        
        $webhookData1 = [
            'webhook_id' => $webhookId1,
            'transaction_id' => 'txn_001',
            'amount' => 75.00,
            'status' => 'success'
        ];
        
        $webhookData2 = [
            'webhook_id' => $webhookId2,
            'transaction_id' => 'txn_002',
            'amount' => 125.00,
            'status' => 'success'
        ];
        
        // שליחת שני webhooks שונים
        $response1 = $this->postJson('/webhooks/payment', $webhookData1);
        $response2 = $this->postJson('/webhooks/payment', $webhookData2);
        
        $response1->assertStatus(200);
        $response2->assertStatus(200);
        
        // בדיקה ששני העסקאות ניצרו
        $this->assertDatabaseHas('payment_transactions', ['transaction_id' => 'txn_001']);
        $this->assertDatabaseHas('payment_transactions', ['transaction_id' => 'txn_002']);
        
        // בדיקה ששני webhook logs נשמרו
        $this->assertDatabaseHas('webhook_logs', ['webhook_id' => $webhookId1]);
        $this->assertDatabaseHas('webhook_logs', ['webhook_id' => $webhookId2]);
    }
    
    public function test_webhook_handles_invalid_data_gracefully()
    {
        $webhookId = 'invalid_' . Str::uuid();
        
        $invalidData = [
            'webhook_id' => $webhookId,
            // חסר transaction_id
            'amount' => 'invalid_amount', // סכום לא תקין
            'status' => 'unknown_status' // סטטוס לא מוכר
        ];
        
        $response = $this->postJson('/webhooks/payment', $invalidData);
        
        // בדיקה שwebhook מחזיר שגיאה מתאימה
        $response->assertStatus(422); // Validation error
        
        // בדיקה שלא ניצרה עסקה
        $this->assertDatabaseMissing('payment_transactions', ['webhook_id' => $webhookId]);
        
        // בדיקה ששגיאה נרשמה בלוג
        $this->assertDatabaseHas('webhook_logs', [
            'webhook_id' => $webhookId,
            'processed' => false
        ]);
    }
    
    public function test_webhook_timeout_handling()
    {
        $webhookId = 'timeout_' . Str::uuid();
        
        // סימולציה של timeout - נשלח webhook עם timestamp ישן
        $webhookData = [
            'webhook_id' => $webhookId,
            'transaction_id' => 'txn_timeout',
            'amount' => 200.00,
            'status' => 'success',
            'timestamp' => now()->subHours(2)->toISOString() // 2 שעות בעבר
        ];
        
        $response = $this->postJson('/webhooks/payment', $webhookData);
        
        // בדיקה שwebhook מתעבד למרות הtimestamp הישן
        $response->assertStatus(200);
        
        // בדיקה שנרשם בלוג עם הערה על timeout
        $this->assertDatabaseHas('webhook_logs', [
            'webhook_id' => $webhookId,
            'processed' => true
        ]);
    }
    
    public function test_webhook_signature_verification()
    {
        $webhookId = 'signed_' . Str::uuid();
        $secret = 'webhook_secret_key';
        
        $webhookData = [
            'webhook_id' => $webhookId,
            'transaction_id' => 'txn_signed',
            'amount' => 150.00,
            'status' => 'success'
        ];
        
        // יצירת חתימה תקינה
        $signature = hash_hmac('sha256', json_encode($webhookData), $secret);
        
        // שליחה עם חתימה תקינה
        $response = $this->postJson('/webhooks/payment', $webhookData, [
            'X-Webhook-Signature' => $signature
        ]);
        
        $response->assertStatus(200);
        
        // שליחה עם חתימה לא תקינה
        $response2 = $this->postJson('/webhooks/payment', $webhookData, [
            'X-Webhook-Signature' => 'invalid_signature'
        ]);
        
        $response2->assertStatus(401); // Unauthorized
    }
    
    public function test_webhook_rate_limiting()
    {
        $webhookId = 'rate_limit_' . Str::uuid();
        
        $webhookData = [
            'webhook_id' => $webhookId,
            'transaction_id' => 'txn_rate_limit',
            'amount' => 100.00,
            'status' => 'success'
        ];
        
        // שליחת בקשות רבות מאותה IP
        for ($i = 0; $i < 15; $i++) {
            $response = $this->postJson('/webhooks/payment', array_merge($webhookData, [
                'webhook_id' => $webhookId . '_' . $i
            ]));
            
            if ($i < 10) {
                $response->assertStatus(200); // עד 10 בקשות מוצלחות
            } else {
                $response->assertStatus(429); // Too Many Requests
            }
        }
    }
    
    public function test_webhook_retry_mechanism()
    {
        $webhookId = 'retry_' . Str::uuid();
        
        // יצירת webhook log שכשל בעבר
        WebhookLog::create([
            'webhook_id' => $webhookId,
            'payload' => json_encode([
                'transaction_id' => 'txn_retry',
                'amount' => 75.00,
                'status' => 'failed'
            ]),
            'processed' => false,
            'attempts' => 2,
            'last_error' => 'Connection timeout',
            'created_at' => now()->subHours(1)
        ]);
        
        // ניסיון נוסף
        $webhookData = [
            'webhook_id' => $webhookId,
            'transaction_id' => 'txn_retry',
            'amount' => 75.00,
            'status' => 'success' // כעת מוצלח
        ];
        
        $response = $this->postJson('/webhooks/payment', $webhookData);
        $response->assertStatus(200);
        
        // בדיקה שהעסקה עודכנה להצלחה
        $this->assertDatabaseHas('payment_transactions', [
            'transaction_id' => 'txn_retry',
            'status' => 'success'
        ]);
        
        // בדיקה שהlog עודכן
        $this->assertDatabaseHas('webhook_logs', [
            'webhook_id' => $webhookId,
            'processed' => true,
            'attempts' => 3 // עלה מ-2 ל-3
        ]);
    }
}
