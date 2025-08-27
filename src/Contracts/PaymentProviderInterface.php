<?php

namespace NMDigitalHub\PaymentGateway\Contracts;

use NMDigitalHub\PaymentGateway\DataObjects\PaymentSessionData;
use NMDigitalHub\PaymentGateway\DataObjects\TransactionData;
use Laravel\SerializableClosure\SerializableClosure;

interface PaymentProviderInterface
{
    /**
     * יצירת סשן תשלום חדש
     */
    public function createPaymentSession(array $parameters): PaymentSessionData;

    /**
     * אימות עסקה לפי מזהה יחוד
     */
    public function verifyTransaction(string $reference): TransactionData;

    /**
     * שליפת פרטי עסקה
     */
    public function getTransaction(string $transactionId): TransactionData;

    /**
     * זיכוי עסקה
     */
    public function refundTransaction(string $transactionId, ?float $amount = null): TransactionData;

    /**
     * ביטול עסקה
     */
    public function cancelTransaction(string $transactionId): TransactionData;

    /**
     * קבלת רשימת עסקאות
     */
    public function listTransactions(
        ?string $from = null,
        ?string $to = null,
        ?int $limit = 100,
        ?string $status = null
    ): array;

    /**
     * אימות חתימת Webhook
     */
    public function validateWebhookSignature(string $payload, string $signature): bool;

    /**
     * עיבוד Webhook מספק התשלום
     */
    public function handleWebhook(array $payload): ?TransactionData;

    /**
     * בדיקת חיבור לספק התשלום
     */
    public function testConnection(): bool;

    /**
     * קבלת מידע על הספק
     */
    public function getProviderInfo(): array;

    /**
     * הגדרת השדות הנדרשים לתצורה
     */
    public function getRequiredConfigFields(): array;

    /**
     * הגדרת עמלות נתמכות
     */
    public function getSupportedCurrencies(): array;

    /**
     * בדיקה האם העמלה נתמכת
     */
    public function supportsCurrency(string $currency): bool;

    /**
     * הגדרת callback להצלחה
     */
    public function onSuccess(SerializableClosure $callback): self;

    /**
     * הגדרת callback לכשלון
     */
    public function onFailure(SerializableClosure $callback): self;
}