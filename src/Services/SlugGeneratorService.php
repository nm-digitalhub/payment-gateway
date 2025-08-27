<?php

namespace NMDigitalHub\PaymentGateway\Services;

use NMDigitalHub\PaymentGateway\Contracts\SlugGeneratorInterface;
use NMDigitalHub\PaymentGateway\Models\Page;
use NMDigitalHub\PaymentGateway\Models\PageRedirect;
use Illuminate\Support\Str;

/**
 * שירות יצירת Slug עם תמיכה עברית ו-redirects
 */
class SlugGeneratorService implements SlugGeneratorInterface
{
    /**
     * יצירת slug ייחודי לפי locale וכותרת
     */
    public function generate(string $locale, string $title, ?int $excludeId = null): string
    {
        $baseSlug = $this->normalize($title, $locale);
        return $this->resolveCollisions($baseSlug, $locale, $excludeId);
    }

    /**
     * נירמול טקסט ל-slug בסיסי
     */
    public function normalize(string $text, string $locale = 'en'): string
    {
        if ($locale === 'he') {
            return $this->normalizeHebrew($text);
        }
        
        return Str::slug($text);
    }

    /**
     * נירמול עברית ל-slug
     */
    protected function normalizeHebrew(string $text): string
    {
        // מפת תמלול עברית לאנגלית
        $hebrewMap = [
            'א' => 'a', 'ב' => 'b', 'ג' => 'g', 'ד' => 'd', 'ה' => 'h', 'ו' => 'v',
            'ז' => 'z', 'ח' => 'ch', 'ט' => 't', 'י' => 'y', 'כ' => 'k', 'ל' => 'l',
            'מ' => 'm', 'ן' => 'n', 'נ' => 'n', 'ס' => 's', 'ע' => 'a', 'פ' => 'p',
            'ף' => 'f', 'צ' => 'tz', 'ץ' => 'tz', 'ק' => 'k', 'ר' => 'r', 'ש' => 'sh',
            'ת' => 't', 'ך' => 'ch', 'ם' => 'm'
        ];
        
        // תמלול
        $transliterated = strtr($text, $hebrewMap);
        
        // ניקוי תווים מיוחדים והחלפת רווחים במקפים
        $cleaned = preg_replace('/[^\w\s-]/', '', $transliterated);
        $cleaned = preg_replace('/[-\s]+/', '-', $cleaned);
        
        return strtolower(trim($cleaned, '-'));
    }

    /**
     * בדיקת ייחודיות slug
     */
    public function isUnique(string $slug, string $locale, ?int $excludeId = null): bool
    {
        $query = Page::where('slug', $slug)->where('locale', $locale);
        
        if ($excludeId) {
            $query->where('id', '!=', $excludeId);
        }
        
        return !$query->exists();
    }

    /**
     * פתרון התנגשויות slug עם הוספת מספר
     */
    public function resolveCollisions(string $baseSlug, string $locale, ?int $excludeId = null): string
    {
        if ($this->isUnique($baseSlug, $locale, $excludeId)) {
            return $baseSlug;
        }
        
        $counter = 2;
        do {
            $candidateSlug = $baseSlug . '-' . $counter;
            $counter++;
        } while (!$this->isUnique($candidateSlug, $locale, $excludeId));
        
        return $candidateSlug;
    }

    /**
     * קבלת slug עם היסטוריה (כולל redirects)
     */
    public function getSlugHistory(int $pageId): array
    {
        $redirects = PageRedirect::where('page_id', $pageId)
            ->orderBy('created_at', 'desc')
            ->get(['old_slug', 'new_slug', 'created_at', 'hits_count'])
            ->toArray();
            
        return $redirects;
    }

    /**
     * יצירת redirect מ-slug ישן לחדש
     */
    public function createRedirect(string $oldSlug, string $newSlug, string $locale, int $pageId): void
    {
        if ($oldSlug === $newSlug) {
            return;
        }
        
        // בדיקה אם כבר קיים redirect
        $existingRedirect = PageRedirect::where('old_slug', $oldSlug)
            ->where('locale', $locale)
            ->first();
            
        if ($existingRedirect) {
            // עדכון redirect קיים
            $existingRedirect->update([
                'new_slug' => $newSlug,
                'page_id' => $pageId
            ]);
        } else {
            // יצירת redirect חדש
            PageRedirect::create([
                'old_slug' => $oldSlug,
                'new_slug' => $newSlug,
                'locale' => $locale,
                'page_id' => $pageId,
                'status_code' => 301,
                'is_active' => true
            ]);
        }
    }
}