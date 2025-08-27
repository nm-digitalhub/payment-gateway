<?php

namespace NMDigitalHub\PaymentGateway\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use NMDigitalHub\PaymentGateway\PaymentGatewayManager;

class ApiController
{
    protected PaymentGatewayManager $paymentManager;

    public function __construct(PaymentGatewayManager $paymentManager)
    {
        $this->paymentManager = $paymentManager;
    }

    /**
     * Get payment status by reference
     */
    public function getStatus(string $reference): JsonResponse
    {
        try {
            $transaction = $this->paymentManager->getTransactionByReference($reference);
            
            if (!$transaction) {
                return response()->json([
                    'error' => 'Transaction not found'
                ], 404);
            }
            
            return response()->json([
                'reference' => $transaction->reference,
                'status' => $transaction->status,
                'amount' => $transaction->amount,
                'currency' => $transaction->currency,
                'created_at' => $transaction->created_at?->toISOString(),
                'completed_at' => $transaction->completed_at?->toISOString(),
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to retrieve transaction status'
            ], 500);
        }
    }

    /**
     * Get transaction details
     */
    public function getTransaction(string $id): JsonResponse
    {
        try {
            $transaction = $this->paymentManager->getTransactionById($id);
            
            if (!$transaction) {
                return response()->json([
                    'error' => 'Transaction not found'
                ], 404);
            }
            
            return response()->json($transaction->toArray());
            
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to retrieve transaction'
            ], 500);
        }
    }

    /**
     * Health check endpoint
     */
    public function health(): JsonResponse
    {
        try {
            $providers = $this->paymentManager->getAvailableProviders();
            $health = [];
            
            foreach ($providers as $provider) {
                $health[$provider['name']] = [
                    'status' => $provider['status'] ?? 'unknown',
                    'last_check' => $provider['last_health_check'] ?? null,
                    'enabled' => $provider['enabled'] ?? false,
                ];
            }
            
            return response()->json([
                'status' => 'ok',
                'providers' => $health,
                'timestamp' => now()->toISOString(),
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Health check failed',
                'timestamp' => now()->toISOString(),
            ], 500);
        }
    }

    /**
     * Get available providers
     */
    public function getProviders(): JsonResponse
    {
        try {
            $providers = $this->paymentManager->getAvailableProviders();
            
            return response()->json([
                'providers' => $providers->toArray(),
                'count' => $providers->count(),
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to retrieve providers'
            ], 500);
        }
    }
}