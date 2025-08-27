<?php

namespace NMDigitalHub\PaymentGateway\Contracts;

/**
 * ממשק repository לניהול ספקי שירות
 */
interface ServiceProviderRepositoryInterface
{
    /**
     * קבלת כל הספקים הפעילים
     */
    public function getActiveProviders(): array;

    /**
     * קבלת ספק לפי שם
     */
    public function getProviderByName(string $name): ?array;

    /**
     * קבלת API endpoints של ספק
     */
    public function getProviderEndpoints(string $providerName): array;

    /**
     * עדכון הגדרות ספק
     */
    public function updateProviderSettings(string $providerName, array $settings): bool;

    /**
     * אימות התקשרות לספק
     */
    public function validateProviderConnection(string $providerName): bool;

    /**
     * קבלת היסטוריית sync של ספק
     */
    public function getSyncHistory(string $providerName, int $limit = 10): array;

    /**
     * רישום פעולת sync
     */
    public function recordSyncOperation(string $providerName, string $operation, array $result): void;
}