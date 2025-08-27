<?php

namespace NMDigitalHub\PaymentGateway\Tests\Feature;

use NMDigitalHub\PaymentGateway\Services\PackageSyncService;
use NMDigitalHub\PaymentGateway\Models\ServiceProvider;
use NMDigitalHub\PaymentGateway\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;

/**
 * בדיקות שירות סינכרון חבילות
 * מתמקדת בספקים רבים וסינכרון
 */
class PackageSyncTest extends TestCase
{
    use RefreshDatabase;
    
    protected PackageSyncService $syncService;
    
    protected function setUp(): void
    {
        parent::setUp();
        $this->syncService = new PackageSyncService();
        Cache::flush();
    }
    
    public function test_can_get_active_providers()
    {
        // יצירת ספקים לבדיקה
        ServiceProvider::factory()->create([
            'name' => 'ResellerClub',
            'type' => 'domains',
            'is_active' => true,
            'priority' => 1
        ]);
        
        ServiceProvider::factory()->create([
            'name' => 'MayaMobile',
            'type' => 'esim',
            'is_active' => true,
            'priority' => 2
        ]);
        
        ServiceProvider::factory()->create([
            'name' => 'DisabledProvider',
            'type' => 'hosting',
            'is_active' => false,
            'priority' => 3
        ]);
        
        $activeProviders = $this->syncService->getActiveProviders();
        
        $this->assertCount(2, $activeProviders);
        $this->assertTrue($activeProviders->contains('name', 'ResellerClub'));
        $this->assertTrue($activeProviders->contains('name', 'MayaMobile'));
        $this->assertFalse($activeProviders->contains('name', 'DisabledProvider'));
    }
    
    public function test_can_sync_packages_from_multiple_providers()
    {
        // משיק תגובות API לספקים שונים
        Http::fake([
            '*/resellerclub/api/*' => Http::response([
                'packages' => [
                    [
                        'id' => 'domain_001',
                        'name' => '.com Domain',
                        'price' => 12.99,
                        'type' => 'domain'
                    ]
                ]
            ]),
            
            '*/maya-mobile/api/*' => Http::response([
                'esim_packages' => [
                    [
                        'id' => 'esim_001',
                        'name' => 'Israel eSIM 30 Days',
                        'price' => 25.00,
                        'type' => 'esim'
                    ]
                ]
            ])
        ]);
        
        ServiceProvider::factory()->create([
            'name' => 'ResellerClub',
            'type' => 'domains',
            'is_active' => true,
            'api_endpoint' => 'https://api.resellerclub.com'
        ]);
        
        ServiceProvider::factory()->create([
            'name' => 'MayaMobile',
            'type' => 'esim',
            'is_active' => true,
            'api_endpoint' => 'https://api.maya-mobile.com'
        ]);
        
        $result = $this->syncService->syncAllProviders();
        
        $this->assertTrue($result['success']);
        $this->assertEquals(2, $result['providers_synced']);
        $this->assertGreaterThan(0, $result['total_packages']);
    }
    
    public function test_sync_handles_api_failures_gracefully()
    {
        // משיק כשל API
        Http::fake([
            '*' => Http::response(null, 500)
        ]);
        
        ServiceProvider::factory()->create([
            'name' => 'FailingProvider',
            'type' => 'domains',
            'is_active' => true
        ]);
        
        $result = $this->syncService->syncAllProviders();
        
        $this->assertFalse($result['success']);
        $this->assertArrayHasKey('errors', $result);
        $this->assertGreaterThan(0, count($result['errors']));
    }
    
    public function test_can_sync_specific_provider_type()
    {
        Http::fake([
            '*/esim-provider/*' => Http::response([
                'packages' => [
                    ['id' => 'esim_test', 'name' => 'Test eSIM', 'price' => 20.00]
                ]
            ])
        ]);
        
        ServiceProvider::factory()->create([
            'name' => 'eSIMProvider',
            'type' => 'esim',
            'is_active' => true
        ]);
        
        ServiceProvider::factory()->create([
            'name' => 'DomainProvider',
            'type' => 'domains',
            'is_active' => true
        ]);
        
        $result = $this->syncService->syncProvidersByType('esim');
        
        $this->assertTrue($result['success']);
        $this->assertEquals(1, $result['providers_synced']); // רק ספק eSIM
    }
    
    public function test_sync_respects_provider_priority_order()
    {
        ServiceProvider::factory()->create([
            'name' => 'LowPriority',
            'type' => 'domains',
            'priority' => 5,
            'is_active' => true
        ]);
        
        ServiceProvider::factory()->create([
            'name' => 'HighPriority',
            'type' => 'domains',
            'priority' => 1,
            'is_active' => true
        ]);
        
        $providers = $this->syncService->getActiveProviders('domains');
        
        // בדיקה שהספק עם העדיפות הגבוהה בא ראשון
        $this->assertEquals('HighPriority', $providers->first()->name);
        $this->assertEquals('LowPriority', $providers->last()->name);
    }
    
    public function test_sync_caches_results()
    {
        Http::fake([
            '*' => Http::response(['packages' => []])
        ]);
        
        ServiceProvider::factory()->create([
            'name' => 'TestProvider',
            'type' => 'domains',
            'is_active' => true
        ]);
        
        // סינכרון ראשון
        $this->syncService->syncAllProviders();
        
        // בדיקה שcache ניצר
        $this->assertTrue(Cache::has('package_sync_last_run'));
        
        // סינכרון שני - אמור להשתמש בcache
        $cached_result = $this->syncService->getLastSyncStatus();
        $this->assertIsArray($cached_result);
    }
    
    public function test_can_clear_sync_cache()
    {
        Cache::put('package_sync_last_run', now(), 3600);
        Cache::put('package_sync_results', ['test'], 3600);
        
        $this->assertTrue(Cache::has('package_sync_last_run'));
        
        $this->syncService->clearSyncCache();
        
        $this->assertFalse(Cache::has('package_sync_last_run'));
        $this->assertFalse(Cache::has('package_sync_results'));
    }
    
    public function test_sync_tracks_performance_metrics()
    {
        Http::fake([
            '*' => Http::response(['packages' => []], 200, [], 500) // 500ms delay
        ]);
        
        ServiceProvider::factory()->create([
            'name' => 'SlowProvider',
            'type' => 'domains',
            'is_active' => true
        ]);
        
        $result = $this->syncService->syncAllProviders();
        
        $this->assertArrayHasKey('performance', $result);
        $this->assertArrayHasKey('total_time', $result['performance']);
        $this->assertArrayHasKey('memory_usage', $result['performance']);
        $this->assertGreaterThan(0, $result['performance']['total_time']);
    }
    
    public function test_sync_validates_package_data()
    {
        // משיק תגובה עם נתונים לא תקינים
        Http::fake([
            '*' => Http::response([
                'packages' => [
                    ['id' => '', 'name' => 'Valid Package', 'price' => 10], // ID ריק
                    ['id' => 'valid_001', 'name' => '', 'price' => 15], // שם ריק
                    ['id' => 'valid_002', 'name' => 'Good Package', 'price' => 20] // תקין
                ]
            ])
        ]);
        
        ServiceProvider::factory()->create([
            'name' => 'TestProvider',
            'type' => 'domains',
            'is_active' => true
        ]);
        
        $result = $this->syncService->syncAllProviders();
        
        $this->assertTrue($result['success']);
        $this->assertEquals(1, $result['valid_packages']); // רק חבילה אחת תקינה
        $this->assertEquals(2, $result['invalid_packages']); // 2 לא תקינות
    }
}
