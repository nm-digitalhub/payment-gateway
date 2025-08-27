<?php

namespace NMDigitalHub\PaymentGateway\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;
use Spatie\Sluggable\HasSlug;
use Spatie\Sluggable\SlugOptions;
use Spatie\Translatable\HasTranslations;

/**
 * PaymentPage Model
 * 
 * ניהול עמודי תשלום וקופות ציבוריים
 * 
 * @property int $id
 * @property string $title כותרת הדף
 * @property string $slug slug ייחודי לURL
 * @property string $description תיאור הדף
 * @property string $type סוג הדף (checkout, payment, success, failed)
 * @property string $status סטטוס (draft, published, archived)
 * @property array $content תוכן הדף
 * @property array $settings הגדרות הדף
 * @property array $seo_meta נתוני SEO
 * @property string $template תבנית עיצוב
 * @property string $language שפת הדף
 * @property bool $is_public האם זמין לציבור
 * @property bool $require_auth האם נדרש אימות
 * @property string|null $redirect_url הפניה לאחר פעולה
 * @property array|null $allowed_methods שיטות תשלום מותרות
 * @property array|null $custom_css CSS מותאם אישית
 * @property array|null $custom_js JavaScript מותאם אישית
 * @property int|null $parent_id דף אב
 * @property int $sort_order סדר הצגה
 * @property \Carbon\Carbon|null $published_at תאריך פרסום
 * @property \Carbon\Carbon|null $expires_at תאריך תפוגה
 * @property \Carbon\Carbon|null $created_at
 * @property \Carbon\Carbon|null $updated_at
 * @property \Carbon\Carbon|null $deleted_at
 */
class PaymentPage extends Model
{
    use HasFactory, SoftDeletes, HasSlug, HasTranslations;

    protected $table = 'payment_pages';

    protected $fillable = [
        'title',
        'slug',
        'description',
        'type',
        'status',
        'content',
        'settings',
        'seo_meta',
        'template',
        'language',
        'is_public',
        'require_auth',
        'redirect_url',
        'allowed_methods',
        'custom_css',
        'custom_js',
        'parent_id',
        'sort_order',
        'published_at',
        'expires_at',
    ];

    protected $casts = [
        'content' => 'array',
        'settings' => 'array',
        'seo_meta' => 'array',
        'is_public' => 'boolean',
        'require_auth' => 'boolean',
        'allowed_methods' => 'array',
        'custom_css' => 'array',
        'custom_js' => 'array',
        'parent_id' => 'integer',
        'sort_order' => 'integer',
        'published_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    protected $translatable = ['title', 'description', 'content'];

    protected $attributes = [
        'type' => 'checkout',
        'status' => 'draft',
        'template' => 'default',
        'language' => 'he',
        'is_public' => true,
        'require_auth' => false,
        'sort_order' => 0,
    ];

    // Constants for page types
    public const TYPE_CHECKOUT = 'checkout';
    public const TYPE_PAYMENT = 'payment';
    public const TYPE_SUCCESS = 'success';
    public const TYPE_FAILED = 'failed';
    public const TYPE_PENDING = 'pending';
    public const TYPE_LANDING = 'landing';
    public const TYPE_CUSTOM = 'custom';

    // Constants for status
    public const STATUS_DRAFT = 'draft';
    public const STATUS_PUBLISHED = 'published';
    public const STATUS_ARCHIVED = 'archived';

    /**
     * Get the options for generating the slug.
     */
    public function getSlugOptions(): SlugOptions
    {
        return SlugOptions::create()
            ->generateSlugsFrom('title')
            ->saveSlugsTo('slug')
            ->doNotGenerateSlugsOnUpdate()
            ->slugsShouldBeNoLongerThan(100);
    }

    /**
     * Relationships
     */
    
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('sort_order');
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(PaymentTransaction::class, 'page_id');
    }

    /**
     * Scopes
     */
    
    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_PUBLISHED)
            ->where('is_public', true)
            ->where(function ($q) {
                $q->whereNull('published_at')
                  ->orWhere('published_at', '<=', now());
            })
            ->where(function ($q) {
                $q->whereNull('expires_at')
                  ->orWhere('expires_at', '>', now());
            });
    }

    public function scopeDraft(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_DRAFT);
    }

    public function scopeByType(Builder $query, string $type): Builder
    {
        return $query->where('type', $type);
    }

    public function scopeByLanguage(Builder $query, string $language): Builder
    {
        return $query->where('language', $language);
    }

    public function scopePublic(Builder $query): Builder
    {
        return $query->where('is_public', true);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order', 'asc')
            ->orderBy('created_at', 'desc');
    }

    /**
     * Helper Methods
     */
    
    public function isPublished(): bool
    {
        return $this->status === self::STATUS_PUBLISHED 
            && $this->is_public 
            && (!$this->published_at || $this->published_at->isPast())
            && (!$this->expires_at || $this->expires_at->isFuture());
    }

    public function isDraft(): bool
    {
        return $this->status === self::STATUS_DRAFT;
    }

    public function isExpired(): bool
    {
        return $this->expires_at && $this->expires_at->isPast();
    }

    public function requiresAuth(): bool
    {
        return $this->require_auth;
    }

    public function getUrl(): string
    {
        return route('payment.page', ['slug' => $this->slug]);
    }

    public function getFullUrl(): string
    {
        return url($this->getUrl());
    }

    /**
     * Content Management
     */
    
    public function getContentBlock(string $key, $default = null)
    {
        return data_get($this->content, $key, $default);
    }

    public function setContentBlock(string $key, $value): void
    {
        $content = $this->content ?? [];
        data_set($content, $key, $value);
        $this->content = $content;
    }

    public function getSetting(string $key, $default = null)
    {
        return data_get($this->settings, $key, $default);
    }

    public function setSetting(string $key, $value): void
    {
        $settings = $this->settings ?? [];
        data_set($settings, $key, $value);
        $this->settings = $settings;
    }

    /**
     * SEO Methods
     */
    
    public function getSeoTitle(): string
    {
        return $this->seo_meta['title'] ?? $this->title;
    }

    public function getSeoDescription(): string
    {
        return $this->seo_meta['description'] ?? Str::limit($this->description, 160);
    }

    public function getSeoKeywords(): string
    {
        return $this->seo_meta['keywords'] ?? '';
    }

    public function getSeoImage(): ?string
    {
        return $this->seo_meta['image'] ?? null;
    }

    /**
     * Template Methods
     */
    
    public function getTemplatePath(): string
    {
        return "payment-gateway::pages.{$this->template}";
    }

    public function getTemplateVariables(): array
    {
        return array_merge(
            $this->content ?? [],
            $this->settings ?? [],
            [
                'page' => $this,
                'page_title' => $this->title,
                'page_description' => $this->description,
                'page_type' => $this->type,
                'custom_css' => $this->getCustomCss(),
                'custom_js' => $this->getCustomJs(),
            ]
        );
    }

    public function getCustomCss(): string
    {
        if (!$this->custom_css || !is_array($this->custom_css)) {
            return '';
        }

        return implode("\n", array_filter($this->custom_css));
    }

    public function getCustomJs(): string
    {
        if (!$this->custom_js || !is_array($this->custom_js)) {
            return '';
        }

        return implode("\n", array_filter($this->custom_js));
    }

    /**
     * Payment Methods
     */
    
    public function getAllowedPaymentMethods(): array
    {
        return $this->allowed_methods ?? ['cardcom', 'stripe', 'paypal'];
    }

    public function supportsPaymentMethod(string $method): bool
    {
        return in_array($method, $this->getAllowedPaymentMethods());
    }

    /**
     * Static Methods
     */
    
    public static function getPageTypes(): array
    {
        return [
            self::TYPE_CHECKOUT => 'עמוד קופה',
            self::TYPE_PAYMENT => 'עמוד תשלום',
            self::TYPE_SUCCESS => 'עמוד הצלחה',
            self::TYPE_FAILED => 'עמוד כשלון',
            self::TYPE_PENDING => 'עמוד המתנה',
            self::TYPE_LANDING => 'עמוד נחיתה',
            self::TYPE_CUSTOM => 'עמוד מותאם אישית',
        ];
    }

    public static function getPageStatuses(): array
    {
        return [
            self::STATUS_DRAFT => 'טיוטה',
            self::STATUS_PUBLISHED => 'פורסם',
            self::STATUS_ARCHIVED => 'בארכיון',
        ];
    }

    public static function getAvailableTemplates(): array
    {
        return [
            'default' => 'ברירת מחדל',
            'modern' => 'מודרני',
            'minimal' => 'מינימליסטי',
            'corporate' => 'תאגידי',
            'mobile-first' => 'מובייל-ראשון',
        ];
    }

    /**
     * Route key name for URLs
     */
    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    /**
     * Get available languages
     */
    public static function getAvailableLanguages(): array
    {
        return [
            'he' => 'עברית',
            'en' => 'English',
            'ar' => 'العربية',
            'fr' => 'Français',
            'es' => 'Español',
        ];
    }

    /**
     * Generate default content based on page type
     */
    public function generateDefaultContent(): array
    {
        return match ($this->type) {
            self::TYPE_CHECKOUT => [
                'heading' => 'השלמת הזמנה',
                'description' => 'אנא מלאו את הפרטים להשלמת ההזמנה',
                'form_fields' => ['name', 'email', 'phone', 'payment_method'],
                'terms_text' => 'בלחיצה על "שלח" אני מאשר/ת את התנאים וההגבלות',
            ],
            self::TYPE_SUCCESS => [
                'heading' => 'תודה על ההזמנה!',
                'message' => 'ההזמנה שלכם עובדה בהצלחה. פרטי ההזמנה נשלחו למייל.',
                'next_steps' => 'נציגינו יצרו איתכם קשר בהקדם.',
            ],
            self::TYPE_FAILED => [
                'heading' => 'אירעה שגיאה',
                'message' => 'מתנצלים, אירעה שגיאה בעיבוד ההזמנה.',
                'support_text' => 'אנא צרו קשר עם השירות לקוחות.',
            ],
            default => ['heading' => $this->title, 'content' => ''],
        };
    }

    /**
     * Auto-generate content on creation if empty
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($page) {
            if (empty($page->content)) {
                $page->content = $page->generateDefaultContent();
            }
        });
    }
}