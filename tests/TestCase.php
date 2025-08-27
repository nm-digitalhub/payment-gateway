<?php

namespace NMDigitalHub\PaymentGateway\Tests;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use NMDigitalHub\PaymentGateway\PaymentGatewayServiceProvider;
use Filament\FilamentServiceProvider;
use Filament\Actions\ActionsServiceProvider;
use Filament\Forms\FormsServiceProvider;
use Filament\Infolists\InfolistsServiceProvider;
use Filament\Notifications\NotificationsServiceProvider;
use Filament\Support\SupportServiceProvider;
use Filament\Tables\TablesServiceProvider;
use Filament\Widgets\WidgetsServiceProvider;

abstract class TestCase extends BaseTestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Configure test environment
        $this->app['config']->set('database.default', 'testing');
        $this->app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);

        // Configure queue for testing
        $this->app['config']->set('queue.default', 'sync');

        // Configure payment gateway for testing
        $this->app['config']->set('payment-gateway.test_mode', true);
        $this->app['config']->set('payment-gateway.providers.cardcom.test_mode', true);
        $this->app['config']->set('payment-gateway.providers.maya_mobile.test_mode', true);

        $this->setUpDatabase();
        $this->setUpTestData();
    }

    protected function getPackageProviders($app): array
    {
        return [
            // Filament providers
            FilamentServiceProvider::class,
            ActionsServiceProvider::class,
            FormsServiceProvider::class,
            InfolistsServiceProvider::class,
            NotificationsServiceProvider::class,
            SupportServiceProvider::class,
            TablesServiceProvider::class,
            WidgetsServiceProvider::class,
            
            // Our package provider
            PaymentGatewayServiceProvider::class,
        ];
    }

    protected function getEnvironmentSetUp($app): void
    {
        $app['config']->set('app.key', 'base64:' . base64_encode(random_bytes(32)));
        $app['config']->set('app.debug', true);
        $app['config']->set('logging.default', 'testing');
    }

    protected function setUpDatabase(): void
    {
        // Load migrations
        $this->loadLaravelMigrations();
        
        // Load package migrations if they exist
        if (is_dir(__DIR__ . '/../database/migrations')) {
            $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');
        }

        // Load test migrations
        if (is_dir(__DIR__ . '/database/migrations')) {
            $this->loadMigrationsFrom(__DIR__ . '/database/migrations');
        }
    }

    protected function setUpTestData(): void
    {
        // Create test users with roles
        $this->createTestUsers();
        
        // Create test service providers
        $this->createTestProviders();
        
        // Create test payment pages
        $this->createTestPaymentPages();
    }

    protected function createTestUsers(): void
    {
        // Create admin user
        $this->adminUser = \App\Models\User::factory()->create([
            'name' => 'Admin User',
            'email' => 'admin@test.com',
            'is_admin' => true,
        ]);

        // Create regular user
        $this->clientUser = \App\Models\User::factory()->create([
            'name' => 'Client User',
            'email' => 'client@test.com',
            'is_admin' => false,
        ]);
    }

    protected function createTestProviders(): void
    {
        // Create test service providers
        \App\Models\ServiceProvider::factory()->create([
            'name' => 'cardcom',
            'display_name' => 'CardCom Test',
            'is_active' => true,
            'supports_payments' => true,
        ]);

        \App\Models\ServiceProvider::factory()->create([
            'name' => 'maya_mobile',
            'display_name' => 'Maya Mobile Test',
            'is_active' => true,
            'supports_payments' => true,
        ]);
    }

    protected function createTestPaymentPages(): void
    {
        \NMDigitalHub\PaymentGateway\Models\PaymentPage::factory()->create([
            'title' => 'Test Payment Page',
            'slug' => 'test-payment',
            'is_active' => true,
            'created_by' => $this->adminUser->id,
        ]);
    }

    /**
     * Mock a payment provider
     */
    protected function mockPaymentProvider(string $provider, array $responses = []): void
    {
        $mock = \Mockery::mock("NMDigitalHub\\PaymentGateway\\Providers\\{$provider}Provider");
        
        foreach ($responses as $method => $response) {
            $mock->shouldReceive($method)->andReturn($response);
        }

        $this->app->instance("payment-gateway.providers.{$provider}", $mock);
    }

    /**
     * Assert that a job was dispatched
     */
    protected function assertJobDispatched(string $job, ?callable $callback = null): void
    {
        \Illuminate\Support\Facades\Queue::fake();
        \Illuminate\Support\Facades\Queue::assertPushed($job, $callback);
    }

    /**
     * Assert that an event was dispatched
     */
    protected function assertEventDispatched(string $event, ?callable $callback = null): void
    {
        \Illuminate\Support\Facades\Event::fake();
        \Illuminate\Support\Facades\Event::assertDispatched($event, $callback);
    }

    protected function tearDown(): void
    {
        \Mockery::close();
        parent::tearDown();
    }
}