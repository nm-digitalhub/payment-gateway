<?php

namespace NMDigitalHub\PaymentGateway\Contracts;

/**
 * ממשק repository לניהול payment tokens
 */
interface PaymentTokenRepositoryInterface
{
    /**
     * קבלת tokens של משתמש
     */
    public function getUserTokens(int $userId): array;

    /**
     * קבלת token לפי ID
     */
    public function getTokenById(int $tokenId): ?array;

    /**
     * יצירת token חדש
     */
    public function createToken(array $tokenData): ?int;

    /**
     * עדכון token
     */
    public function updateToken(int $tokenId, array $updateData): bool;

    /**
     * מחיקת token
     */
    public function deleteToken(int $tokenId): bool;

    /**
     * סימון token כ-default
     */
    public function markAsDefault(int $tokenId, int $userId): bool;

    /**
     * קבלת default token של משתמש
     */
    public function getDefaultToken(int $userId): ?array;

    /**
     * אימות token
     */
    public function validateToken(int $tokenId, int $userId): bool;

    /**
     * בדיקת תוקף token
     */
    public function isTokenExpired(int $tokenId): bool;

    /**
     * רישום שימוש ב-token
     */
    public function recordTokenUsage(int $tokenId, array $usageData): void;

    /**
     * ניקוי tokens שפגו
     */
    public function cleanupExpiredTokens(): int;
}