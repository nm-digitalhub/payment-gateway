<?php

namespace NMDigitalHub\PaymentGateway\Tests\Unit;

use NMDigitalHub\PaymentGateway\Tests\TestCase;
use NMDigitalHub\PaymentGateway\PaymentGatewayManager;
use NMDigitalHub\PaymentGateway\Enums\PaymentProvider;
use NMDigitalHub\PaymentGateway\DataObjects\PaymentRequest;

class PaymentGatewayManagerTest extends TestCase
{
    private PaymentGatewayManager $manager;

    protected function setUp(): void
    {
        parent::setUp();
        $this->manager = app(PaymentGatewayManager::class);
    }

    /** @test */
    public function it_can_get_available_providers(): void
    {
        $providers = $this->manager->getAvailableProviders();
        
        $this->assertIsArray($providers);
        $this->assertContains('cardcom', $providers);
        $this->assertContains('maya_mobile', $providers);
    }

    /** @test */
    public function it_can_get_provider_instance(): void
    {
        $provider = $this->manager->getProvider('cardcom');
        
        $this->assertNotNull($provider);
        $this->assertInstanceOf(
            'NMDigitalHub\\PaymentGateway\\Contracts\\PaymentProviderInterface',
            $provider
        );
    }

    /** @test */
    public function it_returns_null_for_invalid_provider(): void
    {
        $provider = $this->manager->getProvider('invalid_provider');
        
        $this->assertNull($provider);
    }

    /** @test */
    public function it_can_get_default_provider(): void
    {
        $provider = $this->manager->getDefaultProvider();
        
        $this->assertNotNull($provider);
        $this->assertEquals('cardcom', $provider);
    }

    /** @test */
    public function it_can_process_payment_with_provider(): void
    {
        // Mock successful payment response
        $this->mockPaymentProvider('CardCom', [
            'processPayment' => new \NMDigitalHub\PaymentGateway\DataObjects\PaymentResponse(
                success: true,
                transactionId: 'test_transaction_123',
                amount: 100.00,
                currency: 'ILS'
            )
        ]);

        $paymentRequest = PaymentRequest::create()
            ->provider('cardcom')
            ->amount(100.00)
            ->currency('ILS')
            ->customerEmail('test@example.com')
            ->build();

        $response = $this->manager->processPayment($paymentRequest);
        
        $this->assertTrue($response->isSuccessful());
        $this->assertEquals('test_transaction_123', $response->getTransactionId());
        $this->assertEquals(100.00, $response->getAmount());
    }

    /** @test */
    public function it_can_validate_provider_configuration(): void
    {
        $isValid = $this->manager->validateProviderConfiguration('cardcom');
        
        $this->assertIsBool($isValid);
    }

    /** @test */
    public function it_can_get_provider_capabilities(): void
    {
        $capabilities = $this->manager->getProviderCapabilities('cardcom');
        
        $this->assertIsArray($capabilities);
        $this->assertArrayHasKey('supports_3ds', $capabilities);
        $this->assertArrayHasKey('supports_tokens', $capabilities);
        $this->assertArrayHasKey('supports_refunds', $capabilities);
    }

    /** @test */
    public function it_throws_exception_for_invalid_payment_request(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        
        $paymentRequest = PaymentRequest::create()
            ->provider('invalid_provider')
            ->amount(-100.00) // Invalid amount
            ->build();

        $this->manager->processPayment($paymentRequest);
    }

    /** @test */
    public function it_can_get_provider_statistics(): void
    {
        $stats = $this->manager->getProviderStatistics('cardcom');
        
        $this->assertIsArray($stats);
        $this->assertArrayHasKey('total_transactions', $stats);
        $this->assertArrayHasKey('success_rate', $stats);
        $this->assertArrayHasKey('average_amount', $stats);
    }
}