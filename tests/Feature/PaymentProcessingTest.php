<?php

namespace NMDigitalHub\PaymentGateway\Tests\Feature;

use NMDigitalHub\PaymentGateway\Tests\TestCase;
use NMDigitalHub\PaymentGateway\Jobs\ProcessPaymentWebhook;
use NMDigitalHub\PaymentGateway\Events\PaymentProcessed;
use NMDigitalHub\PaymentGateway\Events\PaymentFailed;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Event;

class PaymentProcessingTest extends TestCase
{
    /** @test */
    public function it_can_process_successful_cardcom_payment(): void
    {
        Queue::fake();
        Event::fake();

        // Mock CardCom successful response
        $this->mockPaymentProvider('CardCom', [
            'processPayment' => new \NMDigitalHub\PaymentGateway\DataObjects\PaymentResponse(
                success: true,
                transactionId: 'cc_trans_123',
                amount: 250.00,
                currency: 'ILS',
                metadata: ['provider_transaction_id' => 'cc_123456']
            )
        ]);

        $response = $this->post('/payment/test-payment/process', [
            'provider' => 'cardcom',
            'amount' => 250.00,
            'currency' => 'ILS',
            'customer_name' => 'Test Customer',
            'customer_email' => 'test@example.com',
            'customer_phone' => '050-1234567',
        ]);

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'transaction_id',
            'checkout_url'
        ]);

        // Assert payment processed event was dispatched
        Event::assertDispatched(PaymentProcessed::class, function ($event) {
            return $event->provider === 'cardcom' && 
                   $event->getTransactionId() === 'cc_trans_123';
        });
    }

    /** @test */
    public function it_can_handle_failed_payment(): void
    {
        Event::fake();

        // Mock payment failure
        $this->mockPaymentProvider('CardCom', [
            'processPayment' => new \NMDigitalHub\PaymentGateway\DataObjects\PaymentResponse(
                success: false,
                errorMessage: 'Insufficient funds',
                errorCode: 'insufficient_funds'
            )
        ]);

        $response = $this->post('/payment/test-payment/process', [
            'provider' => 'cardcom',
            'amount' => 1000.00,
            'currency' => 'ILS',
            'customer_email' => 'test@example.com',
        ]);

        $response->assertStatus(422);
        $response->assertJson([
            'success' => false,
            'error' => 'Insufficient funds'
        ]);

        Event::assertDispatched(PaymentFailed::class, function ($event) {
            return $event->provider === 'cardcom' && 
                   $event->errorCode === 'insufficient_funds';
        });
    }

    /** @test */
    public function it_can_process_webhook_data(): void
    {
        Queue::fake();

        $webhookData = [
            'transaction_id' => 'webhook_trans_456',
            'status' => 'completed',
            'amount' => 150.00,
            'currency' => 'ILS',
            'timestamp' => now()->toISOString(),
        ];

        $response = $this->post('/webhooks/payment/cardcom', $webhookData, [
            'X-Signature' => 'test_signature_hash',
            'Content-Type' => 'application/json'
        ]);

        $response->assertStatus(200);
        
        // Assert webhook job was dispatched
        Queue::assertPushed(ProcessPaymentWebhook::class, function ($job) use ($webhookData) {
            return $job->provider === 'cardcom' && 
                   $job->webhookData === $webhookData;
        });
    }

    /** @test */
    public function it_validates_required_payment_fields(): void
    {
        $response = $this->post('/payment/test-payment/process', [
            'provider' => 'cardcom',
            // Missing required fields
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors([
            'amount',
            'currency',
            'customer_email'
        ]);
    }

    /** @test */
    public function it_can_handle_token_payment(): void
    {
        Event::fake();

        // Create a test payment token
        $token = \App\Models\PaymentToken::factory()->create([
            'user_id' => $this->clientUser->id,
            'provider' => 'cardcom',
            'token' => 'test_token_123',
            'is_active' => true,
            'expires_at' => now()->addYear(),
        ]);

        // Mock successful token payment
        $this->mockPaymentProvider('CardCom', [
            'processTokenPayment' => new \NMDigitalHub\PaymentGateway\DataObjects\PaymentResponse(
                success: true,
                transactionId: 'token_trans_789',
                amount: 100.00,
                currency: 'ILS'
            )
        ]);

        $response = $this->actingAs($this->clientUser)
            ->post('/payment/test-payment/process', [
                'provider' => 'cardcom',
                'payment_method' => 'token',
                'token_id' => $token->id,
                'cvv' => '123',
                'amount' => 100.00,
                'currency' => 'ILS',
            ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'transaction_id' => 'token_trans_789'
        ]);
    }

    /** @test */
    public function it_can_create_payment_token_during_processing(): void
    {
        Event::fake();

        // Mock payment with token creation
        $this->mockPaymentProvider('CardCom', [
            'processPayment' => new \NMDigitalHub\PaymentGateway\DataObjects\PaymentResponse(
                success: true,
                transactionId: 'trans_with_token_456',
                amount: 300.00,
                currency: 'ILS',
                paymentToken: 'new_token_abc123'
            )
        ]);

        $response = $this->actingAs($this->clientUser)
            ->post('/payment/test-payment/process', [
                'provider' => 'cardcom',
                'amount' => 300.00,
                'currency' => 'ILS',
                'customer_email' => $this->clientUser->email,
                'save_payment_method' => true,
            ]);

        $response->assertStatus(200);
        
        // Assert token was created
        $this->assertDatabaseHas('payment_tokens', [
            'user_id' => $this->clientUser->id,
            'provider' => 'cardcom',
            'is_active' => true,
        ]);

        Event::assertDispatched(\NMDigitalHub\PaymentGateway\Events\TokenCreated::class);
    }
}