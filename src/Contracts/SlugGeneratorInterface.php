<?php

namespace NMDigitalHub\PaymentGateway\Contracts;

/**
 * ממשק יצירת Slug ייחודי עם תמיכה רב-לשונית
 */
interface SlugGeneratorInterface
{
    /**
     * יצירת slug ייחודי לפי locale וכותרת
     */
    public function generate(string $locale, string $title, ?int $excludeId = null): string;

    /**
     * נירמול טקסט ל-slug בסיסי
     */
    public function normalize(string $text, string $locale = 'en'): string;

    /**
     * בדיקת ייחודיות slug
     */
    public function isUnique(string $slug, string $locale, ?int $excludeId = null): bool;

    /**
     * פתרון התנגשויות slug עם הוספת מספר
     */
    public function resolveCollisions(string $baseSlug, string $locale, ?int $excludeId = null): string;

    /**
     * קבלת slug עם היסטוריה (כולל redirects)
     */
    public function getSlugHistory(int $pageId): array;

    /**
     * יצירת redirect מ-slug ישן לחדש
     */
    public function createRedirect(string $oldSlug, string $newSlug, string $locale, int $pageId): void;
}