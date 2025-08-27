<?php

namespace NMDigitalHub\PaymentGateway\Contracts;

/**
 * ממשק מדיניות מחירים והמרות מטבע
 */
interface PricingPolicyInterface
{
    /**
     * המרת מחיר בין מטבעות
     */
    public function convertPrice(float $amount, string $fromCurrency, string $toCurrency): float;

    /**
     * עיגול מחיר לפי מטבע
     */
    public function roundPrice(float $amount, string $currency): float;

    /**
     * חישוב הנחה
     */
    public function applyDiscount(float $basePrice, array $discountRules): float;

    /**
     * חישוב מס
     */
    public function calculateTax(float $basePrice, string $country = 'IL'): array;

    /**
     * קבלת מחיר סופי עם כל התוספות
     */
    public function getFinalPrice(float $basePrice, string $currency, array $context = []): array;

    /**
     * קבלת שער המרה נוכחי
     */
    public function getExchangeRate(string $fromCurrency, string $toCurrency): float;

    /**
     * פורמט מחיר לתצוגה לפי מטבע ולוקאל
     */
    public function formatPrice(float $amount, string $currency, string $locale = 'he-IL'): string;

    /**
     * קבלת מטבעות נתמכים
     */
    public function getSupportedCurrencies(): array;

    /**
     * בדיקה אם מטבע נתמך
     */
    public function isCurrencySupported(string $currency): bool;
}