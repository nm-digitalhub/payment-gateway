<?php

namespace NMDigitalHub\PaymentGateway\Repositories\Eloquent;

use NMDigitalHub\PaymentGateway\Contracts\PaymentTokenRepositoryInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

/**
 * Eloquent implementation of PaymentTokenRepositoryInterface
 * Works with main app's PaymentToken model or fallback table queries
 */
class PaymentTokenRepository implements PaymentTokenRepositoryInterface
{
    protected string $table = 'payment_tokens';

    /**
     * קבלת tokens של משתמש
     */
    public function getUserTokens(int $userId): array
    {
        $cacheKey = "user_tokens_{$userId}";
        
        return Cache::remember($cacheKey, 1800, function () use ($userId) {
            return DB::table($this->table)
                ->where('user_id', $userId)
                ->where('is_active', true)
                ->where(function ($query) {
                    $query->whereNull('expires_at')
                          ->orWhere('expires_at', '>', now());
                })
                ->orderBy('is_default', 'desc')
                ->orderBy('created_at', 'desc')
                ->get()
                ->toArray();
        });
    }

    /**
     * קבלת token לפי ID
     */
    public function getTokenById(int $tokenId): ?array
    {
        $token = DB::table($this->table)
            ->where('id', $tokenId)
            ->first();

        return $token ? (array) $token : null;
    }

    /**
     * יצירת token חדש
     */
    public function createToken(array $tokenData): ?int
    {
        try {
            $tokenData['created_at'] = now();
            $tokenData['updated_at'] = now();
            
            $id = DB::table($this->table)->insertGetId($tokenData);
            
            // Clear user cache
            $this->clearUserCache($tokenData['user_id'] ?? null);
            
            Log::info('Payment token created', [
                'token_id' => $id,
                'user_id' => $tokenData['user_id'] ?? null,
                'gateway' => $tokenData['gateway'] ?? null,
            ]);
            
            return $id;
        } catch (\Exception $e) {
            Log::error('Failed to create payment token', [
                'error' => $e->getMessage(),
                'data' => $tokenData,
            ]);
            
            return null;
        }
    }

    /**
     * עדכון token
     */
    public function updateToken(int $tokenId, array $updateData): bool
    {
        try {
            $updateData['updated_at'] = now();
            
            // Get user_id for cache clearing
            $token = $this->getTokenById($tokenId);
            $userId = $token['user_id'] ?? null;
            
            $updated = DB::table($this->table)
                ->where('id', $tokenId)
                ->update($updateData);
            
            if ($updated && $userId) {
                $this->clearUserCache($userId);
            }
            
            return $updated > 0;
        } catch (\Exception $e) {
            Log::error('Failed to update payment token', [
                'token_id' => $tokenId,
                'error' => $e->getMessage(),
            ]);
            
            return false;
        }
    }

    /**
     * מחיקת token
     */
    public function deleteToken(int $tokenId): bool
    {
        try {
            // Get user_id for cache clearing before deletion
            $token = $this->getTokenById($tokenId);
            $userId = $token['user_id'] ?? null;
            
            $deleted = DB::table($this->table)
                ->where('id', $tokenId)
                ->update([
                    'is_active' => false,
                    'deleted_at' => now(),
                    'updated_at' => now(),
                ]);
            
            if ($deleted && $userId) {
                $this->clearUserCache($userId);
            }
            
            Log::info('Payment token deleted', ['token_id' => $tokenId]);
            
            return $deleted > 0;
        } catch (\Exception $e) {
            Log::error('Failed to delete payment token', [
                'token_id' => $tokenId,
                'error' => $e->getMessage(),
            ]);
            
            return false;
        }
    }

    /**
     * סימון token כ-default
     */
    public function markAsDefault(int $tokenId, int $userId): bool
    {
        try {
            DB::transaction(function () use ($tokenId, $userId) {
                // Remove default from all user tokens
                DB::table($this->table)
                    ->where('user_id', $userId)
                    ->update(['is_default' => false, 'updated_at' => now()]);
                
                // Set new default
                DB::table($this->table)
                    ->where('id', $tokenId)
                    ->where('user_id', $userId)
                    ->update(['is_default' => true, 'updated_at' => now()]);
            });
            
            $this->clearUserCache($userId);
            
            Log::info('Payment token marked as default', [
                'token_id' => $tokenId,
                'user_id' => $userId,
            ]);
            
            return true;
        } catch (\Exception $e) {
            Log::error('Failed to mark token as default', [
                'token_id' => $tokenId,
                'user_id' => $userId,
                'error' => $e->getMessage(),
            ]);
            
            return false;
        }
    }

    /**
     * קבלת default token של משתמש
     */
    public function getDefaultToken(int $userId): ?array
    {
        $token = DB::table($this->table)
            ->where('user_id', $userId)
            ->where('is_active', true)
            ->where('is_default', true)
            ->where(function ($query) {
                $query->whereNull('expires_at')
                      ->orWhere('expires_at', '>', now());
            })
            ->first();

        return $token ? (array) $token : null;
    }

    /**
     * אימות token
     */
    public function validateToken(int $tokenId, int $userId): bool
    {
        $exists = DB::table($this->table)
            ->where('id', $tokenId)
            ->where('user_id', $userId)
            ->where('is_active', true)
            ->where(function ($query) {
                $query->whereNull('expires_at')
                      ->orWhere('expires_at', '>', now());
            })
            ->exists();

        return $exists;
    }

    /**
     * בדיקת תוקף token
     */
    public function isTokenExpired(int $tokenId): bool
    {
        $token = DB::table($this->table)
            ->where('id', $tokenId)
            ->first(['expires_at']);

        if (!$token || !$token->expires_at) {
            return false; // No expiration date = never expires
        }

        return now()->isAfter($token->expires_at);
    }

    /**
     * רישום שימוש ב-token
     */
    public function recordTokenUsage(int $tokenId, array $usageData): void
    {
        try {
            // Update last_used_at and increment usage count
            DB::table($this->table)
                ->where('id', $tokenId)
                ->update([
                    'last_used_at' => now(),
                    'usage_count' => DB::raw('usage_count + 1'),
                    'updated_at' => now(),
                ]);

            Log::info('Token usage recorded', [
                'token_id' => $tokenId,
                'usage_data' => $usageData,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to record token usage', [
                'token_id' => $tokenId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * ניקוי tokens שפגו
     */
    public function cleanupExpiredTokens(): int
    {
        try {
            $deleted = DB::table($this->table)
                ->where('expires_at', '<', now())
                ->where('is_active', true)
                ->update([
                    'is_active' => false,
                    'deleted_at' => now(),
                    'updated_at' => now(),
                ]);

            if ($deleted > 0) {
                Log::info('Expired tokens cleaned up', ['count' => $deleted]);
                
                // Clear all user caches (expensive but necessary)
                $this->clearAllUserCaches();
            }

            return $deleted;
        } catch (\Exception $e) {
            Log::error('Failed to cleanup expired tokens', [
                'error' => $e->getMessage(),
            ]);

            return 0;
        }
    }

    /**
     * ניקוי cache של משתמש
     */
    protected function clearUserCache(?int $userId): void
    {
        if ($userId) {
            Cache::forget("user_tokens_{$userId}");
        }
    }

    /**
     * ניקוי כל user caches (שימוש נדיר)
     */
    protected function clearAllUserCaches(): void
    {
        // This is expensive - only use for cleanup operations
        Cache::flush();
    }
}