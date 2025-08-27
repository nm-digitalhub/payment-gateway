<?php

namespace NMDigitalHub\PaymentGateway\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Carbon\Carbon;

/**
 * מודל עמוד ציבורי
 */
class Page extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'package_id',
        'locale',
        'title',
        'slug',
        'excerpt',
        'body',
        'seo_title',
        'seo_description',
        'seo_keywords',
        'is_published',
        'published_at',
        'metadata',
        'featured_image',
        'gallery'
    ];

    protected $casts = [
        'is_published' => 'boolean',
        'published_at' => 'datetime',
        'metadata' => 'array',
        'gallery' => 'array'
    ];

    protected $attributes = [
        'locale' => 'he',
        'is_published' => false
    ];

    /**
     * קשר לחבילה
     */
    public function package(): BelongsTo
    {
        return $this->belongsTo(Package::class);
    }

    /**
     * קשר לקישורי סנכרון
     */
    public function syncLinks(): HasMany
    {
        return $this->hasMany(SyncLink::class);
    }

    /**
     * קשר ל-redirects
     */
    public function redirects(): HasMany
    {
        return $this->hasMany(PageRedirect::class);
    }

    /**
     * סקופ - עמודים מפורסמים
     */
    public function scopePublished($query)
    {
        return $query->where('is_published', true)
                    ->where(function ($q) {
                        $q->whereNull('published_at')
                          ->orWhere('published_at', '<=', now());
                    });
    }

    /**
     * סקופ - עמודים לפי לוקאל
     */
    public function scopeByLocale($query, string $locale)
    {
        return $query->where('locale', $locale);
    }

    /**
     * סקופ - עמודים עם slug
     */
    public function scopeBySlug($query, string $slug)
    {
        return $query->where('slug', $slug);
    }

    /**
     * סקופ - חיפוש בתוכן
     */
    public function scopeSearch($query, string $term)
    {
        return $query->where(function ($q) use ($term) {
            $q->where('title', 'like', "%{$term}%")
              ->orWhere('excerpt', 'like', "%{$term}%")
              ->orWhere('body', 'like', "%{$term}%")
              ->orWhere('seo_keywords', 'like', "%{$term}%");
        });
    }

    /**
     * יצירת slug ייחודי
     */
    public function generateSlug(): string
    {
        $baseSlug = $this->createSlugFromTitle($this->title, $this->locale);
        
        $counter = 1;
        $slug = $baseSlug;
        
        while ($this->slugExists($slug)) {
            $slug = $baseSlug . '-' . $counter;
            $counter++;
        }
        
        return $slug;
    }

    /**
     * יצירת slug בסיסי מכותרת
     */
    protected function createSlugFromTitle(string $title, string $locale): string
    {
        // טיפול בעברית
        if ($locale === 'he') {
            $slug = $this->hebrewSlugify($title);
        } else {
            $slug = \Illuminate\Support\Str::slug($title);
        }
        
        return strtolower($slug);
    }

    /**
     * יצירת slug מעברית
     */
    protected function hebrewSlugify(string $text): string
    {
        // מפת תמלול עברית לאנגלית
        $hebrewMap = [
            'א' => 'a', 'ב' => 'b', 'ג' => 'g', 'ד' => 'd', 'ה' => 'h', 'ו' => 'v',
            'ז' => 'z', 'ח' => 'ch', 'ט' => 't', 'י' => 'y', 'כ' => 'k', 'ל' => 'l',
            'מ' => 'm', 'ן' => 'n', 'נ' => 'n', 'ס' => 's', 'ע' => 'a', 'פ' => 'p',
            'ף' => 'f', 'צ' => 'tz', 'ץ' => 'tz', 'ק' => 'k', 'ר' => 'r', 'ש' => 'sh',
            'ת' => 't', 'ך' => 'ch', 'ם' => 'm'
        ];
        
        // תמלול אותיות עבריות
        $transliterated = strtr($text, $hebrewMap);
        
        // ניקוי וטיפול בתווים מיוחדים
        $transliterated = preg_replace('/[^\w\s-]/', '', $transliterated);
        $transliterated = preg_replace('/[-\s]+/', '-', $transliterated);
        
        return trim($transliterated, '-');
    }

    /**
     * בדיקה אם slug קיים
     */
    protected function slugExists(string $slug): bool
    {
        return static::where('slug', $slug)
                    ->where('locale', $this->locale)
                    ->where('id', '!=', $this->id ?? 0)
                    ->exists();
    }

    /**
     * פרסום עמוד
     */
    public function publish(): void
    {
        $this->update([
            'is_published' => true,
            'published_at' => $this->published_at ?: now()
        ]);
    }

    /**
     * ביטול פרסום עמוד
     */
    public function unpublish(): void
    {
        $this->update([
            'is_published' => false
        ]);
    }

    /**
     * בדיקה אם העמוד מפורסם ופעיל
     */
    public function isPublishedAndActive(): bool
    {
        return $this->is_published && 
               ($this->published_at === null || $this->published_at->isPast());
    }

    /**
     * קבלת URL מלא של העמוד
     */
    public function getUrlAttribute(): string
    {
        $locale = $this->locale === config('app.locale') ? '' : $this->locale . '/';
        return url($locale . 'p/' . $this->slug);
    }

    /**
     * קבלת כותרת SEO (כותרת או seo_title)
     */
    public function getSeoTitleAttribute(): string
    {
        return $this->attributes['seo_title'] ?: $this->title;
    }

    /**
     * קבלת תיאור SEO (excerpt או seo_description)
     */
    public function getSeoDescriptionAttribute(): string
    {
        return $this->attributes['seo_description'] ?: ($this->excerpt ?: '');
    }

    /**
     * קבלת מלל מקוצר
     */
    public function getExcerptAttribute(): string
    {
        if ($this->attributes['excerpt']) {
            return $this->attributes['excerpt'];
        }
        
        if ($this->body) {
            return \Illuminate\Support\Str::limit(strip_tags($this->body), 160);
        }
        
        return '';
    }

    /**
     * קבלת זמן קריאה משוער (בדקות)
     */
    public function getReadingTimeAttribute(): int
    {
        $wordCount = str_word_count(strip_tags($this->body ?? ''));
        return max(1, ceil($wordCount / 200)); // 200 מילים לדקה
    }

    /**
     * עדכון slug עם יצירת redirect
     */
    public function updateSlug(string $newSlug): void
    {
        $oldSlug = $this->slug;
        
        if ($oldSlug !== $newSlug && $this->is_published) {
            // יצירת redirect
            PageRedirect::create([
                'old_slug' => $oldSlug,
                'new_slug' => $newSlug,
                'locale' => $this->locale,
                'page_id' => $this->id,
                'status_code' => 301
            ]);
        }
        
        $this->update(['slug' => $newSlug]);
    }

    /**
     * קבלת נתוני JSON-LD לSEO
     */
    public function getJsonLdData(): array
    {
        $data = [
            '@context' => 'https://schema.org',
            '@type' => 'Product',
            'name' => $this->title,
            'description' => $this->seo_description,
            'url' => $this->url
        ];

        if ($this->package) {
            $data['offers'] = [
                '@type' => 'Offer',
                'price' => $this->package->base_price_decimal,
                'priceCurrency' => $this->package->base_currency,
                'availability' => $this->package->isAvailable() ? 
                    'https://schema.org/InStock' : 'https://schema.org/OutOfStock'
            ];

            if ($this->package->image_url) {
                $data['image'] = $this->package->image_url;
            }
        }

        return $data;
    }
}