<?php

namespace NMDigitalHub\PaymentGateway\Contracts;

use NMDigitalHub\PaymentGateway\DataObjects\PackageDTO;

/**
 * ממשק ספקי סנכרון לקטלוגים חיצוניים
 */
interface SyncProviderInterface
{
    /**
     * שליפת קטלוג מהספק החיצוני
     */
    public function fetchCatalog(?array $filters = []): iterable;

    /**
     * שליפת חבילה ספציפית לפי מזהה חיצוני
     */
    public function fetchPackageById(string $externalId): ?PackageDTO;

    /**
     * בדיקת זמינות חבילה
     */
    public function checkAvailability(string $externalId): bool;

    /**
     * קבלת שם הספק
     */
    public function getProviderName(): string;

    /**
     * קבלת הגדרות הספק
     */
    public function getProviderConfig(): array;

    /**
     * בדיקת חיבור לספק
     */
    public function testConnection(): bool;

    /**
     * קבלת סטטיסטיקות הספק
     */
    public function getStats(): array;
}