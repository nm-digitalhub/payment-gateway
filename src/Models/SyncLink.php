<?php

namespace NMDigitalHub\PaymentGateway\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;

/**
 * מודל קישור סנכרון בין חבילה לעמוד
 */
class SyncLink extends Model
{
    use HasFactory;

    protected $fillable = [
        'package_id',
        'page_id',
        'relation',
        'metadata'
    ];

    protected $casts = [
        'metadata' => 'array'
    ];

    const RELATION_PRIMARY = 'primary';
    const RELATION_LANDING = 'landing';
    const RELATION_VARIANT = 'variant';
    const RELATION_TRANSLATION = 'translation';

    protected $attributes = [
        'relation' => self::RELATION_PRIMARY
    ];

    /**
     * קשר לחבילה
     */
    public function package(): BelongsTo
    {
        return $this->belongsTo(Package::class);
    }

    /**
     * קשר לעמוד
     */
    public function page(): BelongsTo
    {
        return $this->belongsTo(Page::class);
    }

    /**
     * סקופ - קישורים ראשיים
     */
    public function scopePrimary($query)
    {
        return $query->where('relation', self::RELATION_PRIMARY);
    }

    /**
     * סקופ - דפי נחיתה
     */
    public function scopeLanding($query)
    {
        return $query->where('relation', self::RELATION_LANDING);
    }

    /**
     * סקופ - וריאנטים
     */
    public function scopeVariants($query)
    {
        return $query->where('relation', self::RELATION_VARIANT);
    }

    /**
     * סקופ - תרגומים
     */
    public function scopeTranslations($query)
    {
        return $query->where('relation', self::RELATION_TRANSLATION);
    }

    /**
     * סקופ - לפי סוג קשר
     */
    public function scopeByRelation($query, string $relation)
    {
        return $query->where('relation', $relation);
    }

    /**
     * בדיקה אם הקישור ראשי
     */
    public function isPrimary(): bool
    {
        return $this->relation === self::RELATION_PRIMARY;
    }

    /**
     * בדיקה אם דף נחיתה
     */
    public function isLanding(): bool
    {
        return $this->relation === self::RELATION_LANDING;
    }

    /**
     * בדיקה אם וריאנט
     */
    public function isVariant(): bool
    {
        return $this->relation === self::RELATION_VARIANT;
    }

    /**
     * בדיקה אם תרגום
     */
    public function isTranslation(): bool
    {
        return $this->relation === self::RELATION_TRANSLATION;
    }

    /**
     * קבלת כל סוגי הקשרים הזמינים
     */
    public static function getAvailableRelations(): array
    {
        return [
            self::RELATION_PRIMARY => 'עמוד ראשי',
            self::RELATION_LANDING => 'דף נחיתה',
            self::RELATION_VARIANT => 'וריאנט',
            self::RELATION_TRANSLATION => 'תרגום'
        ];
    }

    /**
     * קבלת תיאור סוג הקשר
     */
    public function getRelationDescription(): string
    {
        return self::getAvailableRelations()[$this->relation] ?? 'לא ידוע';
    }

    /**
     * יצירת קישור ראשי
     */
    public static function createPrimary(int $packageId, int $pageId, array $metadata = []): self
    {
        return static::create([
            'package_id' => $packageId,
            'page_id' => $pageId,
            'relation' => self::RELATION_PRIMARY,
            'metadata' => $metadata
        ]);
    }

    /**
     * יצירת דף נחיתה
     */
    public static function createLanding(int $packageId, int $pageId, array $metadata = []): self
    {
        return static::create([
            'package_id' => $packageId,
            'page_id' => $pageId,
            'relation' => self::RELATION_LANDING,
            'metadata' => $metadata
        ]);
    }

    /**
     * יצירת וריאנט
     */
    public static function createVariant(int $packageId, int $pageId, array $metadata = []): self
    {
        return static::create([
            'package_id' => $packageId,
            'page_id' => $pageId,
            'relation' => self::RELATION_VARIANT,
            'metadata' => $metadata
        ]);
    }

    /**
     * יצירת תרגום
     */
    public static function createTranslation(int $packageId, int $pageId, array $metadata = []): self
    {
        return static::create([
            'package_id' => $packageId,
            'page_id' => $pageId,
            'relation' => self::RELATION_TRANSLATION,
            'metadata' => $metadata
        ]);
    }
}