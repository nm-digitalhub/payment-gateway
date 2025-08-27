<?php

namespace NMDigitalHub\PaymentGateway\DataObjects;

use Illuminate\Contracts\Support\Arrayable;
use JsonSerializable;

/**
 * אובייקט נתונים לחבילה גנרית מספק חיצוני
 */
readonly class PackageDTO implements Arrayable, JsonSerializable
{
    public function __construct(
        public string $externalId,
        public ?string $provider,
        public string $sku,
        public string $title,
        public ?string $shortDescription,
        public ?string $description,
        public string $baseCurrency,
        public float $basePriceDecimal,
        public string $status = 'active',
        public ?array $metadata = null,
        public ?array $categories = null,
        public ?array $features = null,
        public ?array $specifications = null,
        public ?string $imageUrl = null,
        public ?array $gallery = null,
        public ?int $stockQuantity = null,
        public ?bool $isDigital = null,
        public ?array $supportedLocales = null,
        public ?array $tags = null
    ) {}

    /**
     * יצירה מ-array
     */
    public static function fromArray(array $data): self
    {
        return new self(
            externalId: $data['external_id'] ?? $data['id'],
            provider: $data['provider'] ?? null,
            sku: $data['sku'] ?? $data['external_id'] ?? $data['id'],
            title: $data['title'] ?? $data['name'] ?? 'Unnamed Package',
            shortDescription: $data['short_description'] ?? $data['excerpt'] ?? null,
            description: $data['description'] ?? $data['body'] ?? null,
            baseCurrency: $data['base_currency'] ?? $data['currency'] ?? 'ILS',
            basePriceDecimal: (float) ($data['base_price_decimal'] ?? $data['price'] ?? 0),
            status: $data['status'] ?? 'active',
            metadata: $data['metadata'] ?? null,
            categories: $data['categories'] ?? null,
            features: $data['features'] ?? null,
            specifications: $data['specifications'] ?? $data['specs'] ?? null,
            imageUrl: $data['image_url'] ?? $data['image'] ?? null,
            gallery: $data['gallery'] ?? $data['images'] ?? null,
            stockQuantity: $data['stock_quantity'] ?? $data['stock'] ?? null,
            isDigital: $data['is_digital'] ?? null,
            supportedLocales: $data['supported_locales'] ?? ['he', 'en'],
            tags: $data['tags'] ?? null
        );
    }

    /**
     * יצירה מ-JSON
     */
    public static function fromJson(string $json): self
    {
        $data = json_decode($json, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \InvalidArgumentException('Invalid JSON: ' . json_last_error_msg());
        }
        
        return self::fromArray($data);
    }

    /**
     * המרה ל-array
     */
    public function toArray(): array
    {
        return [
            'external_id' => $this->externalId,
            'provider' => $this->provider,
            'sku' => $this->sku,
            'title' => $this->title,
            'short_description' => $this->shortDescription,
            'description' => $this->description,
            'base_currency' => $this->baseCurrency,
            'base_price_decimal' => $this->basePriceDecimal,
            'status' => $this->status,
            'metadata' => $this->metadata,
            'categories' => $this->categories,
            'features' => $this->features,
            'specifications' => $this->specifications,
            'image_url' => $this->imageUrl,
            'gallery' => $this->gallery,
            'stock_quantity' => $this->stockQuantity,
            'is_digital' => $this->isDigital,
            'supported_locales' => $this->supportedLocales,
            'tags' => $this->tags,
        ];
    }

    /**
     * סריאליזציה ל-JSON
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    /**
     * בדיקה אם החבילה זמינה
     */
    public function isAvailable(): bool
    {
        return $this->status === 'active' && 
               ($this->stockQuantity === null || $this->stockQuantity > 0);
    }

    /**
     * בדיקה אם החבילה תומכת בלוקאל
     */
    public function supportsLocale(string $locale): bool
    {
        return in_array($locale, $this->supportedLocales ?? ['he', 'en']);
    }

    /**
     * קבלת מחיר מעוצב
     */
    public function getFormattedPrice(string $locale = 'he-IL'): string
    {
        return new \NumberFormatter($locale, \NumberFormatter::CURRENCY)
            ->formatCurrency($this->basePriceDecimal, $this->baseCurrency);
    }

    /**
     * קבלת קטגוריות כמחרוזת
     */
    public function getCategoriesString(string $separator = ', '): string
    {
        return implode($separator, $this->categories ?? []);
    }

    /**
     * קבלת תגיות כמחרוזת
     */
    public function getTagsString(string $separator = ', '): string
    {
        return implode($separator, $this->tags ?? []);
    }

    /**
     * קבלת מידע SEO בסיסי
     */
    public function getSeoData(): array
    {
        return [
            'title' => $this->title,
            'description' => $this->shortDescription ?? $this->description,
            'keywords' => $this->getTagsString(),
            'image' => $this->imageUrl,
            'price' => $this->basePriceDecimal,
            'currency' => $this->baseCurrency,
            'availability' => $this->isAvailable() ? 'InStock' : 'OutOfStock'
        ];
    }

    /**
     * מיזוג עם חבילה אחרת (עדכון נתונים)
     */
    public function merge(PackageDTO $other): self
    {
        return new self(
            externalId: $other->externalId ?: $this->externalId,
            provider: $other->provider ?: $this->provider,
            sku: $other->sku ?: $this->sku,
            title: $other->title ?: $this->title,
            shortDescription: $other->shortDescription ?: $this->shortDescription,
            description: $other->description ?: $this->description,
            baseCurrency: $other->baseCurrency ?: $this->baseCurrency,
            basePriceDecimal: $other->basePriceDecimal ?: $this->basePriceDecimal,
            status: $other->status ?: $this->status,
            metadata: array_merge($this->metadata ?? [], $other->metadata ?? []),
            categories: $other->categories ?: $this->categories,
            features: $other->features ?: $this->features,
            specifications: array_merge($this->specifications ?? [], $other->specifications ?? []),
            imageUrl: $other->imageUrl ?: $this->imageUrl,
            gallery: $other->gallery ?: $this->gallery,
            stockQuantity: $other->stockQuantity ?: $this->stockQuantity,
            isDigital: $other->isDigital ?? $this->isDigital,
            supportedLocales: array_unique(array_merge($this->supportedLocales ?? [], $other->supportedLocales ?? [])),
            tags: array_unique(array_merge($this->tags ?? [], $other->tags ?? []))
        );
    }
}