<?php

if (! function_exists('payment_gateway')) {
    /**
     * Get the Payment Gateway Manager instance
     */
    function payment_gateway(): \NMDigitalHub\PaymentGateway\PaymentGatewayManager
    {
        return app(\NMDigitalHub\PaymentGateway\PaymentGatewayManager::class);
    }
}

if (! function_exists('format_currency_ils')) {
    /**
     * Format amount in ILS currency with Hebrew formatting
     */
    function format_currency_ils(float $amount, bool $showSymbol = true): string
    {
        $formatted = number_format($amount, 2, '.', ',');
        
        if ($showSymbol) {
            return "₪{$formatted}";
        }
        
        return $formatted;
    }
}

if (! function_exists('payment_status_hebrew')) {
    /**
     * Get Hebrew translation for payment status
     */
    function payment_status_hebrew(string $status): string
    {
        return match($status) {
            'pending' => 'ממתין לתשלום',
            'processing' => 'מעבד תשלום',
            'success' => 'הושלם בהצלחה',
            'failed' => 'נכשל',
            'cancelled' => 'בוטל',
            'refunded' => 'הוחזר',
            'partial_refund' => 'הוחזר חלקית',
            default => 'סטטוס לא ידוע'
        };
    }
}

if (! function_exists('provider_display_name')) {
    /**
     * Get Hebrew display name for payment provider
     */
    function provider_display_name(string $provider): string
    {
        return match($provider) {
            'cardcom' => 'CardCom',
            'maya_mobile' => 'Maya Mobile',
            'resellerclub' => 'ResellerClub',
            default => ucfirst($provider)
        };
    }
}

if (! function_exists('generate_payment_reference')) {
    /**
     * Generate a unique payment reference
     */
    function generate_payment_reference(string $prefix = 'PAY'): string
    {
        return strtoupper($prefix) . '-' . \Illuminate\Support\Str::ulid()->toString();
    }
}

if (! function_exists('is_valid_israeli_id')) {
    /**
     * Validate Israeli ID number
     */
    function is_valid_israeli_id(string $id): bool
    {
        $id = preg_replace('/\D/', '', $id);
        
        if (strlen($id) !== 9) {
            return false;
        }
        
        $sum = 0;
        for ($i = 0; $i < 9; $i++) {
            $digit = (int)$id[$i];
            $weight = ($i % 2) + 1;
            $product = $digit * $weight;
            
            if ($product > 9) {
                $product = (int)($product / 10) + ($product % 10);
            }
            
            $sum += $product;
        }
        
        return ($sum % 10) === 0;
    }
}

if (! function_exists('format_israeli_phone')) {
    /**
     * Format Israeli phone number
     */
    function format_israeli_phone(string $phone): string
    {
        $phone = preg_replace('/\D/', '', $phone);
        
        // Remove leading zeros or country code
        $phone = ltrim($phone, '0');
        if (str_starts_with($phone, '972')) {
            $phone = substr($phone, 3);
        }
        
        // Format based on length
        if (strlen($phone) === 9) {
            return preg_replace('/(\d{2})(\d{3})(\d{4})/', '$1-$2-$3', $phone);
        }
        
        return $phone;
    }
}

if (! function_exists('payment_gateway_config')) {
    /**
     * Get payment gateway configuration value
     */
    function payment_gateway_config(string $key, $default = null)
    {
        return config("payment-gateway.{$key}", $default);
    }
}

if (! function_exists('is_payment_gateway_enabled')) {
    /**
     * Check if payment gateway is enabled
     */
    function is_payment_gateway_enabled(): bool
    {
        return payment_gateway_config('enabled', true);
    }
}

if (! function_exists('get_active_payment_providers')) {
    /**
     * Get list of active payment providers
     */
    function get_active_payment_providers(): array
    {
        try {
            return payment_gateway()->getAvailableProviders()->toArray();
        } catch (\Exception $e) {
            return [];
        }
    }
}