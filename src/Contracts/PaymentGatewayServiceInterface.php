<?php

namespace NMDigitalHub\PaymentGateway\Contracts;

/**
 * Interface for unified payment gateway services
 * חוזה שירותי שער תשלום מאוחד
 */
interface PaymentGatewayServiceInterface
{
    /**
     * Test connection to the service
     * בדיקת חיבור לשירות
     */
    public function testConnection(): array;

    /**
     * Check if service is configured
     * בדיקה אם השירות מוגדר
     */
    public function isConfigured(): bool;

    /**
     * Get provider information
     * קבלת מידע על הספק
     */
    public function getProviderInfo(): array;
}