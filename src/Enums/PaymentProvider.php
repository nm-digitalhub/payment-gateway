<?php

namespace NMDigitalHub\PaymentGateway\Enums;

use NMDigitalHub\PaymentGateway\Contracts\PaymentProviderInterface;
use NMDigitalHub\PaymentGateway\Providers\CardComProvider;

enum PaymentProvider: string
{
    case CARDCOM = 'cardcom';

    public function getDisplayName(): string
    {
        return match ($this) {
            self::CARDCOM => 'CardCom',
        };
    }

    public function getHebrewName(): string
    {
        return match ($this) {
            self::CARDCOM => 'קארדקום',
        };
    }

    public function getCountries(): array
    {
        return match ($this) {
            self::CARDCOM => ['IL'],
        };
    }

    public function getSupportedCurrencies(): array
    {
        return match ($this) {
            self::CARDCOM => ['ILS', 'USD', 'EUR'],
        };
    }

    public function createProvider(): PaymentProviderInterface
    {
        return match ($this) {
            self::CARDCOM => new CardComProvider(),
        };
    }

    public function isAvailableForCountry(string $countryCode): bool
    {
        return in_array(strtoupper($countryCode), $this->getCountries());
    }

    public function supportsCurrency(string $currency): bool
    {
        return in_array(strtoupper($currency), $this->getSupportedCurrencies());
    }

    public static function getAvailableForCountry(string $countryCode): array
    {
        return array_filter(
            self::cases(),
            fn($provider) => $provider->isAvailableForCountry($countryCode)
        );
    }

    public static function getBestForCountry(string $countryCode): ?self
    {
        $available = self::getAvailableForCountry($countryCode);
        
        if (empty($available)) {
            return null;
        }

        // עדיפות לישראל
        if (strtoupper($countryCode) === 'IL') {
            return self::CARDCOM;
        }

        // ברירת מחדל
        return $available[0] ?? null;
    }
}