<?php

namespace NMDigitalHub\PaymentGateway\Contracts;

/**
 * ממשק פרסום ונהול עמודים ציבוריים
 */
interface PagePublisherInterface
{
    /**
     * יצירת/עדכון עמוד
     */
    public function publish(array $pageData): int;

    /**
     * הסרת עמוד מפרסום (לא מחיקה)
     */
    public function unpublish(int $pageId): void;

    /**
     * מחיקת עמוד לגמרי
     */
    public function retire(int $pageId): void;

    /**
     * עדכון SEO עמוד
     */
    public function updateSeo(int $pageId, array $seoData): void;

    /**
     * בדיקת סטטוס פרסום
     */
    public function isPublished(int $pageId): bool;

    /**
     * קבלת מטא-דאטה עמוד
     */
    public function getPageMetadata(int $pageId): array;

    /**
     * יצירת תצוגה מקדימה של עמוד
     */
    public function generatePreview(array $pageData): string;

    /**
     * בולק פעולות על עמודים
     */
    public function bulkPublish(array $pageIds): array;

    /**
     * בולק הסרה מפרסום
     */
    public function bulkUnpublish(array $pageIds): array;
}