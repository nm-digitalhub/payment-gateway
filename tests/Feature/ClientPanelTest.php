<?php

namespace NMDigitalHub\PaymentGateway\Tests\Feature;

use NMDigitalHub\PaymentGateway\Models\User;
use NMDigitalHub\PaymentGateway\Models\PaymentTransaction;
use NMDigitalHub\PaymentGateway\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * בדיקות פאנל לקוח
 * מתמקדת בניווט ודשבורד לקוח
 */
class ClientPanelTest extends TestCase
{
    use RefreshDatabase;
    
    protected User $user;
    
    protected function setUp(): void
    {
        parent::setUp();
        
        $this->user = User::factory()->create([
            'name' => 'בדיקה משתמש',
            'email' => 'test@example.com'
        ]);
    }
    
    public function test_client_can_access_dashboard()
    {
        $response = $this->actingAs($this->user)
            ->get(route('account.dashboard'));
            
        $response->assertStatus(200);
        $response->assertSee('שלום, ' . $this->user->name);
        $response->assertViewIs('payment-gateway::client.dashboard');
    }
    
    public function test_dashboard_shows_payment_statistics()
    {
        // יצירת כמה עסקאות לבדיקה
        PaymentTransaction::factory()->create([
            'user_id' => $this->user->id,
            'status' => 'success',
            'amount' => 100.50,
            'currency' => 'ILS'
        ]);
        
        PaymentTransaction::factory()->create([
            'user_id' => $this->user->id,
            'status' => 'failed',
            'amount' => 50.25,
            'currency' => 'ILS'
        ]);
        
        $response = $this->actingAs($this->user)
            ->get(route('account.dashboard'));
            
        $response->assertStatus(200);
        $response->assertSee('2'); // סה"כ תשלומים
        $response->assertSee('1'); // תשלומים מוצלחים
        $response->assertSee('₪100.50'); // סכום כולל
    }
    
    public function test_client_can_view_payments_page()
    {
        $response = $this->actingAs($this->user)
            ->get(route('account.payments'));
            
        $response->assertStatus(200);
        $response->assertViewIs('payment-gateway::client.payments.index');
    }
    
    public function test_client_can_view_orders_page()
    {
        $response = $this->actingAs($this->user)
            ->get(route('account.orders'));
            
        $response->assertStatus(200);
        $response->assertViewIs('payment-gateway::client.orders.index');
    }
    
    public function test_client_can_access_payment_methods_page()
    {
        $response = $this->actingAs($this->user)
            ->get(route('account.payment-methods'));
            
        $response->assertStatus(200);
        $response->assertViewIs('payment-gateway::client.payment-methods.index');
    }
    
    public function test_client_can_update_profile()
    {
        $response = $this->actingAs($this->user)
            ->get(route('account.profile'));
            
        $response->assertStatus(200);
        $response->assertViewIs('payment-gateway::client.profile');
    }
    
    public function test_client_navigation_links_are_working()
    {
        $routes = [
            'account.dashboard',
            'account.payments',
            'account.orders',
            'account.payment-methods',
            'account.profile'
        ];
        
        foreach ($routes as $route) {
            $response = $this->actingAs($this->user)
                ->get(route($route));
                
            $response->assertStatus(200);
        }
    }
    
    public function test_dashboard_quick_actions_are_present()
    {
        $response = $this->actingAs($this->user)
            ->get(route('account.dashboard'));
            
        $response->assertStatus(200);
        $response->assertSee('צפייה בתשלומים');
        $response->assertSee('היסטוריית הזמנות');
        $response->assertSee('אמצעי תשלום');
        $response->assertSee('עריכת פרופיל');
    }
    
    public function test_recent_transactions_display_on_dashboard()
    {
        $transaction = PaymentTransaction::factory()->create([
            'user_id' => $this->user->id,
            'status' => 'success',
            'amount' => 150.75,
            'currency' => 'ILS',
            'transaction_id' => 'TXN-12345678901234567890'
        ]);
        
        $response = $this->actingAs($this->user)
            ->get(route('account.dashboard'));
            
        $response->assertStatus(200);
        $response->assertSee('תשלומים אחרונים');
        $response->assertSee('TXN-123456789012'); // מקוצר
        $response->assertSee('₪150.75');
        $response->assertSee('הושלם');
    }
    
    public function test_guest_cannot_access_client_panel()
    {
        $response = $this->get(route('account.dashboard'));
        
        $response->assertStatus(302); // Redirect to login
    }
    
    public function test_user_menu_contains_correct_items()
    {
        $response = $this->actingAs($this->user)
            ->get(route('account.dashboard'));
            
        $response->assertStatus(200);
        $response->assertSee($this->user->name);
        $response->assertSee('פרופיל');
        $response->assertSee('התנתקות');
    }
}
