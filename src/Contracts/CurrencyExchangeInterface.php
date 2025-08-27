<?php

namespace NMDigitalHub\PaymentGateway\Contracts;

/**
 * ינטרפייס לשירותי המרת מטבע
 * מגדיר מתודות להמרת מטבע בזמן אמת
 */
interface CurrencyExchangeInterface
{
    /**
     * המרת סכום בין מטבעות
     */
    public function convert(
        float $amount, 
        string $fromCurrency, 
        string $toCurrency,
        ?string $provider = null
    ): float;

    /**
     * קבלת שער החלפה בין מטבעות
     */
    public function getExchangeRate(
        string $fromCurrency, 
        string $toCurrency,
        ?string $provider = null
    ): float;

    /**
     * קבלת רשימת מטבעות נתמכים
     */
    public function getSupportedCurrencies(): array;

    /**
     * בדיקת תקינות API key
     */
    public function validateApiKey(?string $provider = null): bool;

    /**
     * ניקוי cache שערי החלפה
     */
    public function clearCache(?string $fromCurrency = null, ?string $toCurrency = null): void;

    /**
     * קבלת סטטיסטיקות שימוש
     */
    public function getUsageStats(): array;
}
