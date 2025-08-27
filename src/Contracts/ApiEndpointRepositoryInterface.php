<?php

namespace NMDigitalHub\PaymentGateway\Contracts;

/**
 * ממשק repository לניהול API endpoints
 */
interface ApiEndpointRepositoryInterface
{
    /**
     * קבלת endpoint לפי ספק וסוג
     */
    public function getEndpoint(string $provider, string $type): ?string;

    /**
     * קבלת כל endpoints של ספק
     */
    public function getProviderEndpoints(string $provider): array;

    /**
     * עדכון endpoint
     */
    public function updateEndpoint(string $provider, string $type, string $url): bool;

    /**
     * הוספת endpoint חדש
     */
    public function addEndpoint(string $provider, string $type, string $url, array $metadata = []): bool;

    /**
     * מחיקת endpoint
     */
    public function deleteEndpoint(string $provider, string $type): bool;

    /**
     * בדיקת זמינות endpoint
     */
    public function checkEndpointHealth(string $provider, string $type): array;

    /**
     * קבלת metadata של endpoint
     */
    public function getEndpointMetadata(string $provider, string $type): array;

    /**
     * רישום פעולה על endpoint
     */
    public function logEndpointActivity(string $provider, string $type, string $activity, array $details = []): void;
}