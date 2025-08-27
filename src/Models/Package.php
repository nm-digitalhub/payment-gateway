<?php

namespace NMDigitalHub\PaymentGateway\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;

/**
 * מודל חבילה גנרית
 */
class Package extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'external_id',
        'provider',
        'sku',
        'title',
        'short_description',
        'description',
        'base_currency',
        'base_price_decimal',
        'status',
        'metadata',
        'categories',
        'features',
        'specifications',
        'image_url',
        'gallery',
        'stock_quantity',
        'is_digital',
        'supported_locales',
        'tags'
    ];

    protected $casts = [
        'base_price_decimal' => 'decimal:2',
        'metadata' => 'array',
        'categories' => 'array',
        'features' => 'array',
        'specifications' => 'array',
        'gallery' => 'array',
        'supported_locales' => 'array',
        'tags' => 'array',
        'is_digital' => 'boolean',
        'stock_quantity' => 'integer'
    ];

    protected $attributes = [
        'status' => 'active',
        'base_currency' => 'ILS',
        'supported_locales' => '["he", "en"]',
        'is_digital' => true
    ];

    /**
     * קשר לעמודים
     */
    public function pages(): HasMany
    {
        return $this->hasMany(Page::class);
    }

    /**
     * קשר לקישורי סנכרון
     */
    public function syncLinks(): HasMany
    {
        return $this->hasMany(SyncLink::class);
    }

    /**
     * עמוד ראשי לכל לוקאל
     */
    public function primaryPages(): HasManyThrough
    {
        return $this->hasManyThrough(
            Page::class,
            SyncLink::class,
            'package_id',
            'id',
            'id',
            'page_id'
        )->where('sync_links.relation', 'primary');
    }

    /**
     * סקופ - חבילות פעילות
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    /**
     * סקופ - חבילות לפי ספק
     */
    public function scopeByProvider($query, string $provider)
    {
        return $query->where('provider', $provider);
    }

    /**
     * סקופ - חבילות זמינות במלאי
     */
    public function scopeInStock($query)
    {
        return $query->where(function ($q) {
            $q->whereNull('stock_quantity')->orWhere('stock_quantity', '>', 0);
        });
    }

    /**
     * סקופ - חבילות דיגיטליות
     */
    public function scopeDigital($query)
    {
        return $query->where('is_digital', true);
    }

    /**
     * בדיקה אם החבילה זמינה
     */
    public function isAvailable(): bool
    {
        return $this->status === 'active' && 
               ($this->stock_quantity === null || $this->stock_quantity > 0);
    }

    /**
     * בדיקה אם החבילה תומכת בלוקאל
     */
    public function supportsLocale(string $locale): bool
    {
        return in_array($locale, $this->supported_locales ?? []);
    }

    /**
     * קבלת מחיר מעוצב
     */
    public function getFormattedPriceAttribute(): string
    {
        return new \NumberFormatter('he-IL', \NumberFormatter::CURRENCY)
            ->formatCurrency($this->base_price_decimal, $this->base_currency);
    }

    /**
     * קבלת קטגוריות כמחרוזת
     */
    public function getCategoriesStringAttribute(): string
    {
        return implode(', ', $this->categories ?? []);
    }

    /**
     * קבלת תגיות כמחרוזת
     */
    public function getTagsStringAttribute(): string
    {
        return implode(', ', $this->tags ?? []);
    }

    /**
     * בדיקה אם החבילה חדשה (נוצרה ב-7 הימים האחרונים)
     */
    public function getIsNewAttribute(): bool
    {
        return $this->created_at?->isAfter(now()->subDays(7)) ?? false;
    }

    /**
     * בדיקה אם יש עדכון אחרון (עודכנה ב-24 השעות האחרונות)
     */
    public function getIsRecentlyUpdatedAttribute(): bool
    {
        return $this->updated_at?->isAfter(now()->subDay()) ?? false;
    }

    /**
     * קבלת עמוד ראשי ללוקאל מסוים
     */
    public function getPrimaryPageForLocale(string $locale): ?Page
    {
        return $this->pages()
            ->where('locale', $locale)
            ->whereHas('syncLinks', function ($query) {
                $query->where('relation', 'primary');
            })
            ->first();
    }

    /**
     * קבלת כל העמודים ללוקאל מסוים
     */
    public function getPagesForLocale(string $locale): \Illuminate\Database\Eloquent\Collection
    {
        return $this->pages()->where('locale', $locale)->get();
    }

    /**
     * קבלת נתוני SEO
     */
    public function getSeoData(): array
    {
        return [
            'title' => $this->title,
            'description' => $this->short_description ?? $this->description,
            'keywords' => $this->tags_string,
            'image' => $this->image_url,
            'price' => $this->base_price_decimal,
            'currency' => $this->base_currency,
            'availability' => $this->isAvailable() ? 'InStock' : 'OutOfStock',
            'category' => $this->categories_string
        ];
    }

    /**
     * עדכון מלאי
     */
    public function updateStock(int $quantity): void
    {
        if ($this->stock_quantity !== null) {
            $this->update(['stock_quantity' => max(0, $this->stock_quantity - $quantity)]);
        }
    }

    /**
     * הוספה למלאי
     */
    public function addStock(int $quantity): void
    {
        if ($this->stock_quantity !== null) {
            $this->update(['stock_quantity' => $this->stock_quantity + $quantity]);
        }
    }
}