<?php

namespace NMDigitalHub\PaymentGateway\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use NMDigitalHub\PaymentGateway\Jobs\ProcessPaymentWebhook;
use NMDigitalHub\PaymentGateway\Models\PaymentTransaction;
use NMDigitalHub\PaymentGateway\Models\ProviderSetting;

class WebhookController
{
    /**
     * Handle CardCom webhook
     */
    public function handleCardCom(Request $request): JsonResponse
    {
        return $this->handleWebhook($request, 'cardcom');
    }

    /**
     * Handle Maya Mobile webhook
     */
    public function handleMayaMobile(Request $request): JsonResponse
    {
        return $this->handleWebhook($request, 'maya_mobile');
    }

    /**
     * Handle ResellerClub webhook
     */
    public function handleResellerClub(Request $request): JsonResponse
    {
        return $this->handleWebhook($request, 'resellerclub');
    }

    /**
     * Generic webhook handler
     */
    protected function handleWebhook(Request $request, string $provider): JsonResponse
    {
        try {
            // Log incoming webhook
            Log::info('Webhook received', [
                'provider' => $provider,
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'content_type' => $request->header('Content-Type'),
                'payload_size' => strlen($request->getContent()),
            ]);

            // Get webhook payload
            $payload = $this->getWebhookPayload($request);
            
            if (empty($payload)) {
                Log::warning('Empty webhook payload', ['provider' => $provider]);
                return response()->json(['error' => 'Empty payload'], 400);
            }

            // Get provider settings
            $providerSettings = $this->getProviderSettings($provider);
            if (!$providerSettings) {
                Log::error('Provider settings not found', ['provider' => $provider]);
                return response()->json(['error' => 'Provider not configured'], 400);
            }

            // Verify webhook signature if available
            if (!$this->verifyWebhookSignature($request, $payload, $providerSettings)) {
                Log::error('Invalid webhook signature', [
                    'provider' => $provider,
                    'ip' => $request->ip()
                ]);
                return response()->json(['error' => 'Invalid signature'], 401);
            }

            // Check for duplicate webhooks (idempotency)
            $webhookId = $this->getWebhookId($payload, $provider);
            if ($this->isDuplicateWebhook($webhookId, $provider)) {
                Log::info('Duplicate webhook ignored', [
                    'provider' => $provider,
                    'webhook_id' => $webhookId
                ]);
                return response()->json(['message' => 'Webhook already processed'], 200);
            }

            // Store webhook for processing
            $this->storeWebhookRecord($request, $payload, $provider, $webhookId);

            // Dispatch webhook processing job
            ProcessPaymentWebhook::dispatch(
                $provider,
                $payload,
                $request->headers->all(),
                $request->header('X-Signature') ?? $request->header('Authorization')
            );

            Log::info('Webhook processed successfully', [
                'provider' => $provider,
                'webhook_id' => $webhookId
            ]);

            return response()->json(['message' => 'Webhook received'], 200);

        } catch (\Exception $e) {
            Log::error('Webhook processing error', [
                'provider' => $provider,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json(['error' => 'Internal server error'], 500);
        }
    }

    /**
     * Get webhook payload from request
     */
    protected function getWebhookPayload(Request $request): array
    {
        $contentType = $request->header('Content-Type', '');
        
        if (str_contains($contentType, 'application/json')) {
            return $request->json()->all();
        }
        
        if (str_contains($contentType, 'application/x-www-form-urlencoded')) {
            return $request->all();
        }
        
        // Try to decode as JSON first, then fall back to form data
        $content = $request->getContent();
        $jsonData = json_decode($content, true);
        
        if (json_last_error() === JSON_ERROR_NONE) {
            return $jsonData;
        }
        
        return $request->all();
    }

    /**
     * Get provider settings
     */
    protected function getProviderSettings(string $provider): ?ProviderSetting
    {
        return ProviderSetting::where('provider_name', $provider)
            ->where('is_active', true)
            ->first();
    }

    /**
     * Verify webhook signature
     */
    protected function verifyWebhookSignature(Request $request, array $payload, ProviderSetting $settings): bool
    {
        $webhookSecret = $settings->getCredential('webhook_secret');
        
        if (!$webhookSecret) {
            // If no webhook secret is configured, skip verification
            return true;
        }

        return match ($settings->provider_name) {
            'cardcom' => $this->verifyCardComSignature($request, $payload, $webhookSecret),
            'maya_mobile' => $this->verifyMayaMobileSignature($request, $payload, $webhookSecret),
            'resellerclub' => $this->verifyResellerClubSignature($request, $payload, $webhookSecret),
            default => true
        };
    }

    /**
     * Verify CardCom signature
     */
    protected function verifyCardComSignature(Request $request, array $payload, string $secret): bool
    {
        $signature = $request->header('X-CC-Signature') ?? $request->header('X-Signature');
        
        if (!$signature) {
            return true; // CardCom doesn't always send signatures
        }

        $expectedSignature = hash_hmac('sha256', $request->getContent(), $secret);
        return hash_equals($expectedSignature, $signature);
    }

    /**
     * Verify Maya Mobile signature
     */
    protected function verifyMayaMobileSignature(Request $request, array $payload, string $secret): bool
    {
        $signature = $request->header('X-Maya-Signature');
        
        if (!$signature) {
            return false;
        }

        $expectedSignature = hash_hmac('sha256', $request->getContent(), $secret);
        return hash_equals($expectedSignature, $signature);
    }

    /**
     * Verify ResellerClub signature
     */
    protected function verifyResellerClubSignature(Request $request, array $payload, string $secret): bool
    {
        $signature = $request->header('X-RC-Signature');
        
        if (!$signature) {
            return false;
        }

        $expectedSignature = hash_hmac('sha256', $request->getContent(), $secret);
        return hash_equals($expectedSignature, $signature);
    }

    /**
     * Get webhook ID for idempotency checking
     */
    protected function getWebhookId(array $payload, string $provider): string
    {
        return match ($provider) {
            'cardcom' => $payload['DealId'] ?? $payload['LowProfileId'] ?? $payload['TransactionId'] ?? uniqid(),
            'maya_mobile' => $payload['webhook_id'] ?? $payload['transaction_id'] ?? uniqid(),
            'resellerclub' => $payload['webhook_id'] ?? $payload['order_id'] ?? uniqid(),
            default => uniqid()
        };
    }

    /**
     * Check if webhook is duplicate
     */
    protected function isDuplicateWebhook(string $webhookId, string $provider): bool
    {
        $cacheKey = "webhook_{$provider}_{$webhookId}";
        
        if (Cache::has($cacheKey)) {
            return true;
        }
        
        // Check database
        $exists = \DB::table('processed_webhooks')
            ->where('webhook_id', $webhookId)
            ->where('provider', $provider)
            ->exists();

        if ($exists) {
            // Cache for 1 hour to avoid DB hits
            Cache::put($cacheKey, true, 3600);
            return true;
        }

        return false;
    }

    /**
     * Store webhook record for processing
     */
    protected function storeWebhookRecord(Request $request, array $payload, string $provider, string $webhookId): void
    {
        $externalId = $this->getExternalId($payload, $provider);
        $eventType = $this->getEventType($payload, $provider);
        
        \DB::table('processed_webhooks')->insert([
            'webhook_id' => $webhookId,
            'provider' => $provider,
            'event_type' => $eventType,
            'external_id' => $externalId,
            'payload' => json_encode($payload),
            'headers' => json_encode($request->headers->all()),
            'signature' => $request->header('X-Signature') ?? $request->header('Authorization'),
            'source_ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'status' => 'pending',
            'received_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        
        // Cache to prevent duplicates
        $cacheKey = "webhook_{$provider}_{$webhookId}";
        Cache::put($cacheKey, true, 3600);
    }

    /**
     * Get external ID from payload
     */
    protected function getExternalId(array $payload, string $provider): ?string
    {
        return match ($provider) {
            'cardcom' => $payload['DealId'] ?? $payload['TransactionId'] ?? null,
            'maya_mobile' => $payload['transaction_id'] ?? $payload['order_id'] ?? null,
            'resellerclub' => $payload['order_id'] ?? $payload['entity_id'] ?? null,
            default => null
        };
    }

    /**
     * Get event type from payload
     */
    protected function getEventType(array $payload, string $provider): string
    {
        return match ($provider) {
            'cardcom' => $this->getCardComEventType($payload),
            'maya_mobile' => $payload['event_type'] ?? 'unknown',
            'resellerclub' => $payload['event_type'] ?? $payload['action'] ?? 'unknown',
            default => 'unknown'
        };
    }

    /**
     * Get CardCom event type
     */
    protected function getCardComEventType(array $payload): string
    {
        // CardCom doesn't send explicit event types, infer from data
        if (isset($payload['ResponseCode'])) {
            $responseCode = (int) $payload['ResponseCode'];
            return $responseCode === 0 ? 'payment.completed' : 'payment.failed';
        }
        
        if (isset($payload['DealId'])) {
            return 'payment.notification';
        }
        
        return 'unknown';
    }

    /**
     * Health check endpoint for webhook URL testing
     */
    public function healthCheck(): JsonResponse
    {
        return response()->json([
            'status' => 'ok',
            'message' => 'Payment Gateway Webhook endpoint is healthy',
            'timestamp' => now()->toISOString(),
        ]);
    }
}