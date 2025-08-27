<?php

namespace NMDigitalHub\PaymentGateway\Services;

use NMDigitalHub\PaymentGateway\Contracts\PricingPolicyInterface;
use NMDigitalHub\PaymentGateway\DataObjects\PaymentRequest;

/**
 * שירות מדיניות תמחור פשוט
 * מטפל במעמ ועיגולים
 */
class SimplePricingPolicyService implements PricingPolicyInterface
{
    public function applyPolicy(PaymentRequest $request): PaymentRequest
    {
        $amount = $request->getAmount();
        $currency = $request->getCurrency();
        
        // חישוב מעמ אם נדרש
        if ($this->shouldApplyTax($currency)) {
            $taxRate = $this->getTaxRate($currency);
            $taxAmount = $amount * $taxRate;
            $totalAmount = $amount + $taxAmount;
            
            $request->setTaxAmount($taxAmount);
            $request->setAmount($this->roundAmount($totalAmount, $currency));
        } else {
            $request->setAmount($this->roundAmount($amount, $currency));
        }
        
        return $request;
    }
    
    public function calculateTax(float $amount, string $currency): float
    {
        if (!$this->shouldApplyTax($currency)) {
            return 0.0;
        }
        
        $taxRate = $this->getTaxRate($currency);
        return $this->roundAmount($amount * $taxRate, $currency);
    }
    
    public function getTaxRate(string $currency): float
    {
        return match($currency) {
            'ILS' => 0.17,  // מעמ 17% בישראל
            'USD', 'EUR' => 0.0,  // ללא מעמ במטבעות זרים
            default => 0.0
        };
    }
    
    public function shouldApplyTax(string $currency): bool
    {
        return $currency === 'ILS';
    }
    
    public function roundAmount(float $amount, string $currency): float
    {
        return match($currency) {
            'ILS' => round($amount, 2), // עיגול לעגורות
            'USD', 'EUR' => round($amount, 2), // עיגול לסנטים
            default => round($amount, 2)
        };
    }
    
    public function formatAmount(float $amount, string $currency): string
    {
        return match($currency) {
            'ILS' => '₪' . number_format($amount, 2),
            'USD' => '$' . number_format($amount, 2),
            'EUR' => '€' . number_format($amount, 2),
            default => $currency . ' ' . number_format($amount, 2)
        };
    }
    
    public function convertCurrency(float $amount, string $fromCurrency, string $toCurrency): float
    {
        if ($fromCurrency === $toCurrency) {
            return $amount;
        }
        
        // שימוש בשירות המטבע החדש
        $currencyService = app(\NMDigitalHub\PaymentGateway\Contracts\CurrencyExchangeInterface::class);
        
        try {
            return $currencyService->convert($amount, $fromCurrency, $toCurrency);
        } catch (\Exception $e) {
            \Log::warning('Currency conversion failed, using fallback', [
                'from' => $fromCurrency,
                'to' => $toCurrency,
                'amount' => $amount,
                'error' => $e->getMessage()
            ]);
            
            // שערי המרה בסיסיים (fallback)
            $rates = [
                'USD' => ['ILS' => 3.7, 'EUR' => 0.85],
                'EUR' => ['ILS' => 4.3, 'USD' => 1.18],
                'ILS' => ['USD' => 0.27, 'EUR' => 0.23],
            ];
            
            if (isset($rates[$fromCurrency][$toCurrency])) {
                return $this->roundAmount($amount * $rates[$fromCurrency][$toCurrency], $toCurrency);
            }
            
            throw new \InvalidArgumentException("לא ניתן להמיר מ{$fromCurrency} ל{$toCurrency}");
        }
    }
}
