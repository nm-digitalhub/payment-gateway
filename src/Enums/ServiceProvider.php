<?php

namespace NMDigitalHub\PaymentGateway\Enums;

use NMDigitalHub\PaymentGateway\Contracts\ServiceProviderInterface;
use NMDigitalHub\PaymentGateway\Providers\Services\MayaMobileProvider;
use NMDigitalHub\PaymentGateway\Providers\Services\ResellerClubProvider;

enum ServiceProvider: string
{
    case MAYA_MOBILE = 'maya_mobile';
    case RESELLERCLUB = 'resellerclub';

    public function getDisplayName(): string
    {
        return match ($this) {
            self::MAYA_MOBILE => 'Maya Mobile',
            self::RESELLERCLUB => 'ResellerClub',
        };
    }

    public function getHebrewName(): string
    {
        return match ($this) {
            self::MAYA_MOBILE => 'מאיה מובייל',
            self::RESELLERCLUB => 'ריסלר קלאב',
        };
    }

    public function getServiceTypes(): array
    {
        return match ($this) {
            self::MAYA_MOBILE => ['esim', 'connectivity'],
            self::RESELLERCLUB => ['domains', 'hosting', 'ssl'],
        };
    }

    public function getCountries(): array
    {
        return match ($this) {
            self::MAYA_MOBILE => ['IL', 'US', 'EU', 'GLOBAL'],
            self::RESELLERCLUB => ['IN', 'US', 'EU'],
        };
    }

    public function createProvider(): ServiceProviderInterface
    {
        return match ($this) {
            self::MAYA_MOBILE => new MayaMobileProvider(),
            self::RESELLERCLUB => new ResellerClubProvider(),
        };
    }

    public function supportsService(string $serviceType): bool
    {
        return in_array($serviceType, $this->getServiceTypes());
    }

    public function isAvailableInCountry(string $countryCode): bool
    {
        $countries = $this->getCountries();
        return in_array('GLOBAL', $countries) || in_array(strtoupper($countryCode), $countries);
    }

    public static function getProvidersForService(string $serviceType): array
    {
        return array_filter(
            self::cases(),
            fn($provider) => $provider->supportsService($serviceType)
        );
    }

    public static function getBestProviderForService(string $serviceType, string $countryCode = 'IL'): ?self
    {
        $available = array_filter(
            self::getProvidersForService($serviceType),
            fn($provider) => $provider->isAvailableInCountry($countryCode)
        );

        if (empty($available)) {
            return null;
        }

        // עדיפויות לפי סוג שירות
        return match ($serviceType) {
            'esim', 'connectivity' => self::MAYA_MOBILE,
            'domains', 'hosting', 'vps', 'ssl' => self::RESELLERCLUB,
            default => array_values($available)[0] ?? null
        };
    }

    public function getRequiredCredentials(): array
    {
        return match ($this) {
            self::MAYA_MOBILE => ['api_key', 'api_secret', 'base_url'],
            self::RESELLERCLUB => ['api_key', 'api_secret', 'base_url'],
        };
    }
}