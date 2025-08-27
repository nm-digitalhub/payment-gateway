<?php

namespace NMDigitalHub\PaymentGateway\Services;

use NMDigitalHub\PaymentGateway\Contracts\PagePublisherInterface;
use NMDigitalHub\PaymentGateway\Models\Page;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * שירות פרסום ונהול עמודים ציבוריים
 */
class PagePublisherService implements PagePublisherInterface
{
    /**
     * יצירת/עדכון עמוד
     */
    public function publish(array $pageData): int
    {
        $pageId = $pageData['id'] ?? null;
        
        if ($pageId && Page::find($pageId)) {
            // עדכון עמוד קיים
            $page = Page::find($pageId);
            $page->update($pageData + [
                'is_published' => true,
                'published_at' => $pageData['published_at'] ?? now()
            ]);
        } else {
            // יצירת עמוד חדש
            $page = Page::create($pageData + [
                'is_published' => true,
                'published_at' => $pageData['published_at'] ?? now()
            ]);
        }

        // ניקוי cache רלוונטי
        $this->clearPageCache($page);
        
        Log::info('Page published successfully', [
            'page_id' => $page->id,
            'slug' => $page->slug,
            'locale' => $page->locale
        ]);

        return $page->id;
    }

    /**
     * הסרת עמוד מפרסום (לא מחיקה)
     */
    public function unpublish(int $pageId): void
    {
        $page = Page::find($pageId);
        
        if ($page) {
            $page->update([
                'is_published' => false
            ]);
            
            $this->clearPageCache($page);
            
            Log::info('Page unpublished', [
                'page_id' => $pageId,
                'slug' => $page->slug
            ]);
        }
    }

    /**
     * מחיקת עמוד לגמרי
     */
    public function retire(int $pageId): void
    {
        $page = Page::find($pageId);
        
        if ($page) {
            $this->clearPageCache($page);
            
            // מחיקה רכה
            $page->delete();
            
            Log::info('Page retired (soft deleted)', [
                'page_id' => $pageId,
                'slug' => $page->slug
            ]);
        }
    }

    /**
     * עדכון SEO עמוד
     */
    public function updateSeo(int $pageId, array $seoData): void
    {
        $page = Page::find($pageId);
        
        if ($page) {
            $page->update([
                'seo_title' => $seoData['seo_title'] ?? $page->seo_title,
                'seo_description' => $seoData['seo_description'] ?? $page->seo_description,
                'seo_keywords' => $seoData['seo_keywords'] ?? $page->seo_keywords,
            ]);
            
            $this->clearPageCache($page);
        }
    }

    /**
     * בדיקת סטטוס פרסום
     */
    public function isPublished(int $pageId): bool
    {
        $page = Page::find($pageId);
        
        return $page && $page->is_published && 
               ($page->published_at === null || $page->published_at->isPast());
    }

    /**
     * קבלת מטא-דאטה עמוד
     */
    public function getPageMetadata(int $pageId): array
    {
        $page = Page::find($pageId);
        
        if (!$page) {
            return [];
        }
        
        return [
            'id' => $page->id,
            'title' => $page->title,
            'slug' => $page->slug,
            'locale' => $page->locale,
            'is_published' => $page->is_published,
            'published_at' => $page->published_at?->toISOString(),
            'seo_title' => $page->seo_title,
            'seo_description' => $page->seo_description,
            'url' => $page->url,
            'package_id' => $page->package_id,
            'metadata' => $page->metadata ?? []
        ];
    }

    /**
     * יצירת תצוגה מקדימה של עמוד
     */
    public function generatePreview(array $pageData): string
    {
        // יצירת HTML preview בסיסי
        $title = $pageData['title'] ?? 'Untitled';
        $excerpt = $pageData['excerpt'] ?? '';
        $body = $pageData['body'] ?? '';
        $locale = $pageData['locale'] ?? 'he';
        $dir = $locale === 'he' ? 'rtl' : 'ltr';
        
        return "
        <div dir='{$dir}' style='max-width: 800px; margin: 0 auto; padding: 20px; font-family: system-ui;'>
            <h1 style='color: #333; margin-bottom: 16px;'>{$title}</h1>
            " . ($excerpt ? "<p style='font-size: 18px; color: #666; margin-bottom: 24px;'>{$excerpt}</p>" : "") . "
            <div style='line-height: 1.6; color: #444;'>{$body}</div>
        </div>
        ";
    }

    /**
     * בולק פעולות על עמודים
     */
    public function bulkPublish(array $pageIds): array
    {
        $results = [
            'success' => [],
            'failed' => []
        ];
        
        foreach ($pageIds as $pageId) {
            try {
                $page = Page::find($pageId);
                if ($page) {
                    $page->update([
                        'is_published' => true,
                        'published_at' => now()
                    ]);
                    
                    $this->clearPageCache($page);
                    $results['success'][] = $pageId;
                } else {
                    $results['failed'][] = ['id' => $pageId, 'error' => 'Page not found'];
                }
            } catch (\Exception $e) {
                $results['failed'][] = ['id' => $pageId, 'error' => $e->getMessage()];
            }
        }
        
        Log::info('Bulk publish completed', $results);
        
        return $results;
    }

    /**
     * בולק הסרה מפרסום
     */
    public function bulkUnpublish(array $pageIds): array
    {
        $results = [
            'success' => [],
            'failed' => []
        ];
        
        foreach ($pageIds as $pageId) {
            try {
                $page = Page::find($pageId);
                if ($page) {
                    $page->update(['is_published' => false]);
                    $this->clearPageCache($page);
                    $results['success'][] = $pageId;
                } else {
                    $results['failed'][] = ['id' => $pageId, 'error' => 'Page not found'];
                }
            } catch (\Exception $e) {
                $results['failed'][] = ['id' => $pageId, 'error' => $e->getMessage()];
            }
        }
        
        return $results;
    }

    /**
     * ניקוי cache עמוד
     */
    protected function clearPageCache(Page $page): void
    {
        $cacheKeys = [
            "page_{$page->locale}_{$page->slug}",
            "page_meta_{$page->id}",
            "page_seo_{$page->id}",
            "pages_list_{$page->locale}",
            "sitemap_{$page->locale}"
        ];
        
        foreach ($cacheKeys as $key) {
            Cache::forget($key);
        }
    }
}