<?php

namespace NMDigitalHub\PaymentGateway\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;

/**
 * מודל הפניות עמודים (redirects)
 */
class PageRedirect extends Model
{
    use HasFactory;

    protected $fillable = [
        'old_slug',
        'new_slug',
        'locale',
        'page_id',
        'status_code',
        'hits_count',
        'is_active',
        'expires_at'
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'expires_at' => 'datetime',
        'hits_count' => 'integer',
        'status_code' => 'integer'
    ];

    protected $attributes = [
        'status_code' => 301,
        'hits_count' => 0,
        'is_active' => true
    ];

    /**
     * קשר לעמוד
     */
    public function page(): BelongsTo
    {
        return $this->belongsTo(Page::class);
    }

    /**
     * סקופ - הפניות פעילות
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true)
                    ->where(function ($q) {
                        $q->whereNull('expires_at')
                          ->orWhere('expires_at', '>', now());
                    });
    }

    /**
     * סקופ - הפניות לפי לוקאל
     */
    public function scopeByLocale($query, string $locale)
    {
        return $query->where('locale', $locale);
    }

    /**
     * סקופ - הפניות לפי slug ישן
     */
    public function scopeByOldSlug($query, string $oldSlug)
    {
        return $query->where('old_slug', $oldSlug);
    }

    /**
     * סקופ - הפניות לפי קוד סטטוס
     */
    public function scopeByStatusCode($query, int $statusCode)
    {
        return $query->where('status_code', $statusCode);
    }

    /**
     * סקופ - הפניות קבועות (301)
     */
    public function scopePermanent($query)
    {
        return $query->where('status_code', 301);
    }

    /**
     * סקופ - הפניות זמניות (302)
     */
    public function scopeTemporary($query)
    {
        return $query->where('status_code', 302);
    }

    /**
     * חיפוש הפניה לפי slug ולוקאל
     */
    public static function findActiveRedirect(string $slug, string $locale): ?self
    {
        return static::active()
                    ->byLocale($locale)
                    ->byOldSlug($slug)
                    ->first();
    }

    /**
     * רישום הפעלת הפניה
     */
    public function recordHit(): void
    {
        $this->increment('hits_count');
    }

    /**
     * השבתת הפניה
     */
    public function deactivate(): void
    {
        $this->update(['is_active' => false]);
    }

    /**
     * הפעלת הפניה מחדש
     */
    public function reactivate(): void
    {
        $this->update(['is_active' => true]);
    }

    /**
     * הגדרת תאריך תפוגה
     */
    public function setExpiry(\DateTime $expiresAt): void
    {
        $this->update(['expires_at' => $expiresAt]);
    }

    /**
     * בדיקה אם ההפניה פגה
     */
    public function isExpired(): bool
    {
        return $this->expires_at && $this->expires_at->isPast();
    }

    /**
     * בדיקה אם ההפניה פעילה וולידית
     */
    public function isValidAndActive(): bool
    {
        return $this->is_active && !$this->isExpired();
    }

    /**
     * קבלת כל ההפניות לעמוד מסוים
     */
    public static function getPageRedirects(int $pageId): \Illuminate\Database\Eloquent\Collection
    {
        return static::where('page_id', $pageId)
                    ->orderBy('created_at', 'desc')
                    ->get();
    }

    /**
     * ניקוי הפניות פגות
     */
    public static function cleanupExpired(): int
    {
        return static::where('expires_at', '<', now())
                    ->update(['is_active' => false]);
    }

    /**
     * סטטיסטיקות הפניות
     */
    public static function getRedirectStats(): array
    {
        return [
            'total_redirects' => static::count(),
            'active_redirects' => static::active()->count(),
            'expired_redirects' => static::where('expires_at', '<', now())->count(),
            'permanent_redirects' => static::permanent()->count(),
            'temporary_redirects' => static::temporary()->count(),
            'total_hits' => static::sum('hits_count'),
            'most_used' => static::orderBy('hits_count', 'desc')->first()
        ];
    }
}