<?php

namespace NMDigitalHub\PaymentGateway\Console\Commands;

use Illuminate\Console\Command;
use NMDigitalHub\PaymentGateway\Services\CardComService;
use NMDigitalHub\PaymentGateway\Services\MayaMobileService;
use NMDigitalHub\PaymentGateway\Services\ResellerClubService;

class TestApiConnectionsCommand extends Command
{
    protected $signature = 'payment-gateway:test-api-connections';
    protected $description = 'Test API connections to all providers using admin panel settings';

    public function handle()
    {
        $this->info('🔄 Testing API connections with admin panel settings...');
        $this->newLine();

        // Test CardCom
        $this->info('1. Testing CardCom API...');
        try {
            $cardcom = app(CardComService::class);
            $info = $cardcom->getProviderInfo();
            $connection = $cardcom->testConnection();
            
            if ($cardcom->isConfigured()) {
                if ($connection['success']) {
                    $this->info('   ✅ CardCom: Connected successfully');
                    $this->line("   📊 Terminal: {$info['terminal']}, Test Mode: " . ($info['test_mode'] ? 'Yes' : 'No'));
                } else {
                    $this->error('   ❌ CardCom: Connection failed - ' . ($connection['error'] ?? 'Unknown error'));
                }
            } else {
                $this->warn('   ⚠️ CardCom: Not configured (missing terminal/username/password in admin panel)');
            }
        } catch (\Exception $e) {
            $this->error('   ❌ CardCom: Exception - ' . $e->getMessage());
        }

        $this->newLine();

        // Test Maya Mobile
        $this->info('2. Testing Maya Mobile API...');
        try {
            $maya = app(MayaMobileService::class);
            $info = $maya->getProviderInfo();
            $connection = $maya->testConnection();
            
            if ($maya->isConfigured()) {
                if ($connection['success']) {
                    $this->info('   ✅ Maya Mobile: Connected successfully');
                    $this->line("   📊 Base URL: {$info['base_url']}, Test Mode: " . ($info['test_mode'] ? 'Yes' : 'No'));
                } else {
                    $this->error('   ❌ Maya Mobile: Connection failed - ' . ($connection['error'] ?? 'Unknown error'));
                }
            } else {
                $this->warn('   ⚠️ Maya Mobile: Not configured (missing API key/secret in admin panel)');
            }
        } catch (\Exception $e) {
            $this->error('   ❌ Maya Mobile: Exception - ' . $e->getMessage());
        }

        $this->newLine();

        // Test ResellerClub
        $this->info('3. Testing ResellerClub API...');
        try {
            $resellerclub = app(ResellerClubService::class);
            $info = $resellerclub->getProviderInfo();
            $connection = $resellerclub->testConnection();
            
            if ($resellerclub->isConfigured()) {
                if ($connection['success']) {
                    $this->info('   ✅ ResellerClub: Connected successfully');
                    $this->line("   📊 Base URL: {$info['base_url']}, Test Mode: " . ($info['test_mode'] ? 'Yes' : 'No'));
                } else {
                    $this->error('   ❌ ResellerClub: Connection failed - ' . ($connection['error'] ?? 'Unknown error'));
                }
            } else {
                $this->warn('   ⚠️ ResellerClub: Not configured (missing reseller ID/API key in admin panel)');
            }
        } catch (\Exception $e) {
            $this->error('   ❌ ResellerClub: Exception - ' . $e->getMessage());
        }

        $this->newLine();
        $this->info('✨ API connection test completed!');
        $this->line('💡 Configure missing providers via admin panel at /admin/external-services-settings');
        
        return 0;
    }
}