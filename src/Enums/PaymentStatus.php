<?php

namespace NMDigitalHub\PaymentGateway\Enums;

enum PaymentStatus: string
{
    case PENDING = 'pending';
    case PROCESSING = 'processing';
    case SUCCESS = 'success';
    case FAILED = 'failed';
    case CANCELLED = 'cancelled';
    case EXPIRED = 'expired';
    case REFUNDED = 'refunded';
    case PARTIALLY_REFUNDED = 'partially_refunded';

    public function getDisplayName(): string
    {
        return match ($this) {
            self::PENDING => 'Pending',
            self::PROCESSING => 'Processing',
            self::SUCCESS => 'Success',
            self::FAILED => 'Failed',
            self::CANCELLED => 'Cancelled',
            self::EXPIRED => 'Expired',
            self::REFUNDED => 'Refunded',
            self::PARTIALLY_REFUNDED => 'Partially Refunded',
        };
    }

    public function getHebrewName(): string
    {
        return match ($this) {
            self::PENDING => 'ממתין',
            self::PROCESSING => 'בעיבוד',
            self::SUCCESS => 'הצלחה',
            self::FAILED => 'נכשל',
            self::CANCELLED => 'בוטל',
            self::EXPIRED => 'פג תוקף',
            self::REFUNDED => 'זוכה',
            self::PARTIALLY_REFUNDED => 'זוכה חלקית',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::SUCCESS => 'success',
            self::PROCESSING, self::PENDING => 'warning',
            self::FAILED, self::CANCELLED, self::EXPIRED => 'danger',
            self::REFUNDED, self::PARTIALLY_REFUNDED => 'info',
        };
    }

    public function isCompleted(): bool
    {
        return in_array($this, [self::SUCCESS, self::FAILED, self::CANCELLED, self::EXPIRED, self::REFUNDED]);
    }

    public function isSuccessful(): bool
    {
        return $this === self::SUCCESS;
    }

    public function canBeRefunded(): bool
    {
        return $this === self::SUCCESS;
    }

    public function canBeCancelled(): bool
    {
        return in_array($this, [self::PENDING, self::PROCESSING]);
    }
}