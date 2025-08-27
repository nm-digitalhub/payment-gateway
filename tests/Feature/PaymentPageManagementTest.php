<?php

namespace NMDigitalHub\PaymentGateway\Tests\Feature;

use NMDigitalHub\PaymentGateway\Models\PaymentPage;
use NMDigitalHub\PaymentGateway\Models\PageRedirect;
use NMDigitalHub\PaymentGateway\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * בדיקות ניהול עמודי תשלום
 * מתמקדת בdraft/published workflow וredirects
 */
class PaymentPageManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_create_payment_page_in_draft_status()
    {
        $pageData = [
            'title' => 'עמוד תשלום חדש',
            'slug' => 'test-payment-page',
            'type' => PaymentPage::TYPE_CHECKOUT,
            'status' => PaymentPage::STATUS_DRAFT,
            'description' => 'עמוד בדיקה'
        ];

        $page = PaymentPage::create($pageData);

        $this->assertDatabaseHas('payment_pages', [
            'slug' => 'test-payment-page',
            'status' => PaymentPage::STATUS_DRAFT,
            'is_public' => true
        ]);

        $this->assertTrue($page->isDraft());
        $this->assertFalse($page->isPublished());
    }

    public function test_can_publish_draft_page()
    {
        $page = PaymentPage::create([
            'title' => 'עמוד לפרסום',
            'slug' => 'page-to-publish',
            'type' => PaymentPage::TYPE_CHECKOUT,
            'status' => PaymentPage::STATUS_DRAFT
        ]);

        // פרסום העמוד
        $page->update([
            'status' => PaymentPage::STATUS_PUBLISHED,
            'published_at' => now()
        ]);

        $this->assertDatabaseHas('payment_pages', [
            'id' => $page->id,
            'status' => PaymentPage::STATUS_PUBLISHED
        ]);

        $this->assertTrue($page->fresh()->isPublished());
    }

    public function test_slug_change_creates_redirect()
    {
        // יצירת עמוד מפורסם
        $page = PaymentPage::create([
            'title' => 'עמוד לשינוי slug',
            'slug' => 'original-slug',
            'type' => PaymentPage::TYPE_CHECKOUT,
            'status' => PaymentPage::STATUS_PUBLISHED
        ]);

        $oldSlug = $page->slug;
        $newSlug = 'new-updated-slug';

        // שינוי slug
        $page->updateSlug($newSlug);

        // בדיקה שנוצר redirect
        $this->assertDatabaseHas('page_redirects', [
            'old_slug' => $oldSlug,
            'new_slug' => $newSlug,
            'page_id' => $page->id,
            'status_code' => 301
        ]);

        // בדיקה שהעמוד עודכן
        $this->assertDatabaseHas('payment_pages', [
            'id' => $page->id,
            'slug' => $newSlug
        ]);
    }

    public function test_redirect_is_found_and_executed()
    {
        $page = PaymentPage::create([
            'title' => 'עמוד עם redirect',
            'slug' => 'current-slug',
            'type' => PaymentPage::TYPE_CHECKOUT,
            'status' => PaymentPage::STATUS_PUBLISHED
        ]);

        // יצירת redirect ידני
        PageRedirect::create([
            'old_slug' => 'old-slug',
            'new_slug' => 'current-slug',
            'page_id' => $page->id,
            'locale' => 'he',
            'status_code' => 301,
            'is_active' => true
        ]);

        // חיפוש redirect
        $redirect = PageRedirect::findActiveRedirect('old-slug', 'he');

        $this->assertNotNull($redirect);
        $this->assertEquals('current-slug', $redirect->new_slug);
        $this->assertEquals(301, $redirect->status_code);
    }

    public function test_only_published_pages_are_public()
    {
        // עמוד טיוטה
        $draftPage = PaymentPage::create([
            'title' => 'עמוד טיוטה',
            'slug' => 'draft-page',
            'status' => PaymentPage::STATUS_DRAFT
        ]);

        // עמוד מפורסם
        $publishedPage = PaymentPage::create([
            'title' => 'עמוד מפורסם',
            'slug' => 'published-page',
            'status' => PaymentPage::STATUS_PUBLISHED
        ]);

        // בדיקת scope published
        $publishedPages = PaymentPage::published()->get();

        $this->assertTrue($publishedPages->contains($publishedPage));
        $this->assertFalse($publishedPages->contains($draftPage));
    }

    public function test_page_with_future_publish_date_is_not_published()
    {
        $page = PaymentPage::create([
            'title' => 'עמוד עתידי',
            'slug' => 'future-page',
            'status' => PaymentPage::STATUS_PUBLISHED,
            'published_at' => now()->addDays(1) // עתיד
        ]);

        $publishedPages = PaymentPage::published()->get();

        $this->assertFalse($publishedPages->contains($page));
        $this->assertFalse($page->isPublishedAndActive());
    }

    public function test_expired_page_is_not_published()
    {
        $page = PaymentPage::create([
            'title' => 'עמוד פג',
            'slug' => 'expired-page',
            'status' => PaymentPage::STATUS_PUBLISHED,
            'published_at' => now()->subDays(2),
            'expires_at' => now()->subDay() // פג
        ]);

        $publishedPages = PaymentPage::published()->get();

        $this->assertFalse($publishedPages->contains($page));
        $this->assertTrue($page->isExpired());
        $this->assertFalse($page->isPublishedAndActive());
    }

    public function test_page_content_generation_by_type()
    {
        $checkoutPage = new PaymentPage(['type' => PaymentPage::TYPE_CHECKOUT]);
        $checkoutContent = $checkoutPage->generateDefaultContent();

        $this->assertArrayHasKey('heading', $checkoutContent);
        $this->assertEquals('השלמת הזמנה', $checkoutContent['heading']);

        $successPage = new PaymentPage(['type' => PaymentPage::TYPE_SUCCESS]);
        $successContent = $successPage->generateDefaultContent();

        $this->assertArrayHasKey('heading', $successContent);
        $this->assertEquals('תודה על ההזמנה!', $successContent['heading']);
    }

    public function test_page_url_generation()
    {
        $page = PaymentPage::create([
            'title' => 'עמוד לבדיקת URL',
            'slug' => 'url-test-page',
            'type' => PaymentPage::TYPE_CHECKOUT
        ]);

        $expectedUrl = url('p/url-test-page');
        $this->assertEquals($expectedUrl, $page->url);
    }

    public function test_seo_data_extraction()
    {
        $page = PaymentPage::create([
            'title' => 'עמוד SEO',
            'slug' => 'seo-page',
            'description' => 'תיאור לבדיקת SEO',
            'seo_meta' => [
                'title' => 'כותרת SEO מותאמת',
                'description' => 'תיאור SEO מותאם',
                'keywords' => 'מילות מפתח'
            ]
        ]);

        $this->assertEquals('כותרת SEO מותאמת', $page->getSeoTitle());
        $this->assertEquals('תיאור SEO מותאם', $page->getSeoDescription());
        $this->assertEquals('מילות מפתח', $page->getSeoKeywords());
    }
}
