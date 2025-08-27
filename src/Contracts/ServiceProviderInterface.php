<?php

namespace NMDigitalHub\PaymentGateway\Contracts;

interface ServiceProviderInterface
{
    /**
     * בדיקת חיבור לספק השירות
     */
    public function testConnection(): bool;

    /**
     * שליפת יתרת חשבון / אשראי
     */
    public function getBalance(): array;

    /**
     * שליפת רשימת מוצרים / חבילות
     */
    public function getProducts(array $filters = []): array;

    /**
     * שליפת פרטי מוצר ספציפי
     */
    public function getProduct(string $productId): array;

    /**
     * יצירת הזמנה / רכישה
     */
    public function createOrder(array $orderData): array;

    /**
     * שליפת סטטוס הזמנה
     */
    public function getOrderStatus(string $orderId): array;

    /**
     * ביטול הזמנה
     */
    public function cancelOrder(string $orderId): bool;

    /**
     * שינוי הגדרות מוצר
     */
    public function updateProduct(string $productId, array $updates): array;

    /**
     * שליפת מידע על הספק
     */
    public function getProviderInfo(): array;

    /**
     * שליפת הגדרות נדרשות
     */
    public function getRequiredConfig(): array;

    /**
     * אימות webhook מהספק
     */
    public function validateWebhook(array $payload, string $signature): bool;

    /**
     * עיבוד webhook מהספק
     */
    public function handleWebhook(array $payload): ?array;

    /**
     * סנכרון מוצרים / מחירים
     */
    public function syncProducts(): array;

    /**
     * סנכרון הזמנות
     */
    public function syncOrders(string $from = null, string $to = null): array;

    /**
     * קבלת דוחות וסטטיסטיקות
     */
    public function getReports(string $type, array $filters = []): array;
}