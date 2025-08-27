<?php

namespace NMDigitalHub\PaymentGateway\Tests\Feature;

use NMDigitalHub\PaymentGateway\Models\PaymentPage;
use NMDigitalHub\PaymentGateway\Models\User;
use NMDigitalHub\PaymentGateway\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * בדיקות Admin Workflow
 * מתמקדת בdraft/published states
 */
class AdminWorkflowTest extends TestCase
{
    use RefreshDatabase;
    
    protected User $admin;
    
    protected function setUp(): void
    {
        parent::setUp();
        
        $this->admin = User::factory()->create([
            'name' => 'Admin User',
            'email' => 'admin@example.com',
            'role' => 'admin'
        ]);
    }
    
    public function test_admin_can_create_draft_payment_page()
    {
        $pageData = [
            'title' => 'עמוד תשלום חדש',
            'slug' => 'new-payment-page',
            'content' => 'תוכן העמוד',
            'type' => PaymentPage::TYPE_CHECKOUT,
            'status' => PaymentPage::STATUS_DRAFT
        ];
        
        $response = $this->actingAs($this->admin)
            ->post('/admin/payment-pages', $pageData);
            
        $response->assertStatus(302); // Redirect after creation
        
        $this->assertDatabaseHas('payment_pages', [
            'title' => 'עמוד תשלום חדש',
            'status' => PaymentPage::STATUS_DRAFT,
            'slug' => 'new-payment-page'
        ]);
    }
    
    public function test_draft_pages_not_visible_to_public()
    {
        $draftPage = PaymentPage::create([
            'title' => 'עמוד טיוטה',
            'slug' => 'draft-page',
            'content' => 'תוכן טיוטה',
            'type' => PaymentPage::TYPE_CHECKOUT,
            'status' => PaymentPage::STATUS_DRAFT
        ]);
        
        // משתמש רגיל לא אמור לראות את העמוד
        $response = $this->get('/p/draft-page');
        $response->assertStatus(404);
        
        // אדמין אמור לראות את העמוד (עם preview mode)
        $response = $this->actingAs($this->admin)
            ->get('/admin/payment-pages/' . $draftPage->id . '/preview');
        $response->assertStatus(200);
    }
    
    public function test_admin_can_publish_draft_page()
    {
        $draftPage = PaymentPage::create([
            'title' => 'עמוד לפרסום',
            'slug' => 'to-publish',
            'content' => 'תוכן לפרסום',
            'type' => PaymentPage::TYPE_CHECKOUT,
            'status' => PaymentPage::STATUS_DRAFT
        ]);
        
        // פרסום העמוד
        $response = $this->actingAs($this->admin)
            ->patch('/admin/payment-pages/' . $draftPage->id . '/publish');
            
        $response->assertStatus(200);
        
        // בדיקה שהסטטוס שונה
        $this->assertDatabaseHas('payment_pages', [
            'id' => $draftPage->id,
            'status' => PaymentPage::STATUS_PUBLISHED
        ]);
        
        // עכשיו העמוד אמור להיות נגיש לציבור
        $response = $this->get('/p/to-publish');
        $response->assertStatus(200);
    }
    
    public function test_admin_can_unpublish_page()
    {
        $publishedPage = PaymentPage::create([
            'title' => 'עמוד מפורסם',
            'slug' => 'published-page',
            'content' => 'תוכן מפורסם',
            'type' => PaymentPage::TYPE_CHECKOUT,
            'status' => PaymentPage::STATUS_PUBLISHED
        ]);
        
        // ביטול פרסום
        $response = $this->actingAs($this->admin)
            ->patch('/admin/payment-pages/' . $publishedPage->id . '/unpublish');
            
        $response->assertStatus(200);
        
        $this->assertDatabaseHas('payment_pages', [
            'id' => $publishedPage->id,
            'status' => PaymentPage::STATUS_DRAFT
        ]);
        
        // העמוד לא אמור להיות נגיש לציבור
        $response = $this->get('/p/published-page');
        $response->assertStatus(404);
    }
    
    public function test_admin_can_schedule_page_publication()
    {
        $futureDate = now()->addDays(3);
        
        $pageData = [
            'title' => 'עמוד מתוזמן',
            'slug' => 'scheduled-page',
            'content' => 'תוכן מתוזמן',
            'type' => PaymentPage::TYPE_CHECKOUT,
            'status' => PaymentPage::STATUS_SCHEDULED,
            'published_at' => $futureDate
        ];
        
        $response = $this->actingAs($this->admin)
            ->post('/admin/payment-pages', $pageData);
            
        $response->assertStatus(302);
        
        $this->assertDatabaseHas('payment_pages', [
            'title' => 'עמוד מתוזמן',
            'status' => PaymentPage::STATUS_SCHEDULED,
            'published_at' => $futureDate
        ]);
        
        // העמוד עדיין לא אמור להיות נגיש
        $response = $this->get('/p/scheduled-page');
        $response->assertStatus(404);
    }
    
    public function test_admin_can_bulk_publish_pages()
    {
        // יצירת כמה עמודי טיוטה
        $draftPages = PaymentPage::factory(3)->create([
            'status' => PaymentPage::STATUS_DRAFT
        ]);
        
        $pageIds = $draftPages->pluck('id')->toArray();
        
        $response = $this->actingAs($this->admin)
            ->post('/admin/payment-pages/bulk-publish', [
                'page_ids' => $pageIds
            ]);
            
        $response->assertStatus(200);
        
        // בדיקה שכל העמודים פורסמו
        foreach ($pageIds as $pageId) {
            $this->assertDatabaseHas('payment_pages', [
                'id' => $pageId,
                'status' => PaymentPage::STATUS_PUBLISHED
            ]);
        }
    }
    
    public function test_admin_can_view_page_revision_history()
    {
        $page = PaymentPage::create([
            'title' => 'עמוד עם היסטוריה',
            'slug' => 'page-with-history',
            'content' => 'תוכן ראשוני',
            'status' => PaymentPage::STATUS_PUBLISHED
        ]);
        
        // עדכון העמוד
        $response = $this->actingAs($this->admin)
            ->patch('/admin/payment-pages/' . $page->id, [
                'content' => 'תוכן מעודכן'
            ]);
            
        $response->assertStatus(200);
        
        // צפייה בהיסטוריה
        $response = $this->actingAs($this->admin)
            ->get('/admin/payment-pages/' . $page->id . '/revisions');
            
        $response->assertStatus(200);
        $response->assertViewIs('payment-gateway::admin.payment-pages.revisions');
    }
    
    public function test_admin_workflow_includes_content_approval()
    {
        $editor = User::factory()->create(['role' => 'editor']);
        
        // עורך יוצר עמוד טיוטה
        $pageData = [
            'title' => 'עמוד לאישור',
            'slug' => 'pending-approval',
            'content' => 'תוכן לאישור',
            'status' => PaymentPage::STATUS_PENDING_REVIEW
        ];
        
        $response = $this->actingAs($editor)
            ->post('/admin/payment-pages', $pageData);
            
        $response->assertStatus(302);
        
        // אדמין מאשר את העמוד
        $page = PaymentPage::where('slug', 'pending-approval')->first();
        
        $response = $this->actingAs($this->admin)
            ->patch('/admin/payment-pages/' . $page->id . '/approve');
            
        $response->assertStatus(200);
        
        $this->assertDatabaseHas('payment_pages', [
            'id' => $page->id,
            'status' => PaymentPage::STATUS_PUBLISHED
        ]);
    }
    
    public function test_admin_can_reject_pending_content()
    {
        $page = PaymentPage::create([
            'title' => 'עמוד לדחייה',
            'slug' => 'to-reject',
            'content' => 'תוכן שיידחה',
            'status' => PaymentPage::STATUS_PENDING_REVIEW
        ]);
        
        $response = $this->actingAs($this->admin)
            ->patch('/admin/payment-pages/' . $page->id . '/reject', [
                'rejection_reason' => 'תוכן לא מתאים'
            ]);
            
        $response->assertStatus(200);
        
        $this->assertDatabaseHas('payment_pages', [
            'id' => $page->id,
            'status' => PaymentPage::STATUS_REJECTED
        ]);
    }
    
    public function test_non_admin_cannot_access_admin_workflow()
    {
        $regularUser = User::factory()->create(['role' => 'user']);
        
        $response = $this->actingAs($regularUser)
            ->get('/admin/payment-pages');
            
        $response->assertStatus(403); // Forbidden
    }
    
    public function test_workflow_state_transitions_are_logged()
    {
        $page = PaymentPage::create([
            'title' => 'עמוד עם לוגים',
            'slug' => 'logged-page',
            'content' => 'תוכן',
            'status' => PaymentPage::STATUS_DRAFT
        ]);
        
        // פרסום העמוד
        $response = $this->actingAs($this->admin)
            ->patch('/admin/payment-pages/' . $page->id . '/publish');
            
        $response->assertStatus(200);
        
        // בדיקה ששינוי הסטטוס נרשם בלוג
        $this->assertDatabaseHas('page_status_logs', [
            'page_id' => $page->id,
            'from_status' => PaymentPage::STATUS_DRAFT,
            'to_status' => PaymentPage::STATUS_PUBLISHED,
            'user_id' => $this->admin->id
        ]);
    }
}
