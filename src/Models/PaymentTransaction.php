<?php

namespace NMDigitalHub\PaymentGateway\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use NMDigitalHub\PaymentGateway\Enums\PaymentStatus;
use NMDigitalHub\PaymentGateway\Enums\PaymentProvider;

class PaymentTransaction extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'transaction_id',
        'external_transaction_id',
        'provider',
        'status',
        'amount',
        'currency',
        'fee',
        'net_amount',
        'customer_name',
        'customer_email',
        'customer_phone',
        'billing_address',
        'card_last_four',
        'card_brand',
        'card_expires_at',
        'gateway_response',
        'three_ds_data',
        'webhook_data',
        'metadata',
        'processed_at',
        'failed_at',
        'refunded_at',
        'refund_amount',
        'refund_reason',
        'notes',
        'user_id',
        'payment_page_id',
        'order_type',
        'order_id',
        'ip_address',
        'user_agent',
        'risk_score',
        'fraud_flags',
        'settlement_date',
        'settlement_currency',
        'settlement_amount',
        'exchange_rate',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'fee' => 'decimal:2',
        'net_amount' => 'decimal:2',
        'refund_amount' => 'decimal:2',
        'settlement_amount' => 'decimal:2',
        'exchange_rate' => 'decimal:4',
        'risk_score' => 'integer',
        'billing_address' => 'array',
        'gateway_response' => 'array',
        'three_ds_data' => 'array',
        'webhook_data' => 'array',
        'metadata' => 'array',
        'fraud_flags' => 'array',
        'processed_at' => 'datetime',
        'failed_at' => 'datetime',
        'refunded_at' => 'datetime',
        'card_expires_at' => 'date',
        'settlement_date' => 'date',
        'status' => PaymentStatus::class,
        'provider' => PaymentProvider::class,
    ];

    protected $dates = [
        'processed_at',
        'failed_at', 
        'refunded_at',
        'card_expires_at',
        'settlement_date',
        'created_at',
        'updated_at',
        'deleted_at',
    ];

    /**
     * Get the owning orderable model (Order, Subscription, etc.)
     */
    public function orderable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Get the user who made the payment
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(config('payment-gateway.user_model', 'App\Models\User'));
    }

    /**
     * Get the payment page used for this transaction
     */
    public function paymentPage(): BelongsTo
    {
        return $this->belongsTo(PaymentPage::class);
    }

    /**
     * Scope: Successful transactions
     */
    public function scopeSuccessful($query)
    {
        return $query->where('status', PaymentStatus::COMPLETED);
    }

    /**
     * Scope: Failed transactions
     */
    public function scopeFailed($query)
    {
        return $query->where('status', PaymentStatus::FAILED);
    }

    /**
     * Scope: Pending transactions
     */
    public function scopePending($query)
    {
        return $query->where('status', PaymentStatus::PENDING);
    }

    /**
     * Scope: Refunded transactions
     */
    public function scopeRefunded($query)
    {
        return $query->whereNotNull('refunded_at');
    }

    /**
     * Scope: By provider
     */
    public function scopeByProvider($query, string $provider)
    {
        return $query->where('provider', $provider);
    }

    /**
     * Scope: By date range
     */
    public function scopeByDateRange($query, $from, $to)
    {
        return $query->whereBetween('created_at', [$from, $to]);
    }

    /**
     * Check if transaction is successful
     */
    public function isSuccessful(): bool
    {
        return $this->status === PaymentStatus::COMPLETED;
    }

    /**
     * Check if transaction is failed
     */
    public function isFailed(): bool
    {
        return $this->status === PaymentStatus::FAILED;
    }

    /**
     * Check if transaction is pending
     */
    public function isPending(): bool
    {
        return $this->status === PaymentStatus::PENDING;
    }

    /**
     * Check if transaction is refunded
     */
    public function isRefunded(): bool
    {
        return !is_null($this->refunded_at);
    }

    /**
     * Check if partial refund
     */
    public function isPartiallyRefunded(): bool
    {
        return $this->isRefunded() && $this->refund_amount < $this->amount;
    }

    /**
     * Get refund percentage
     */
    public function getRefundPercentageAttribute(): float
    {
        if (!$this->isRefunded() || $this->amount == 0) {
            return 0;
        }
        
        return ($this->refund_amount / $this->amount) * 100;
    }

    /**
     * Get net profit after fees
     */
    public function getNetProfitAttribute(): float
    {
        return $this->amount - ($this->fee ?? 0);
    }

    /**
     * Get formatted amount with currency
     */
    public function getFormattedAmountAttribute(): string
    {
        return number_format($this->amount, 2) . ' ' . strtoupper($this->currency);
    }

    /**
     * Get card info safely (masked)
     */
    public function getCardInfoAttribute(): ?string
    {
        if ($this->card_last_four && $this->card_brand) {
            return $this->card_brand . ' ****' . $this->card_last_four;
        }
        
        return null;
    }

    /**
     * Get risk level based on score
     */
    public function getRiskLevelAttribute(): string
    {
        $score = $this->risk_score ?? 0;
        
        if ($score >= 80) return 'high';
        if ($score >= 50) return 'medium';
        return 'low';
    }

    /**
     * Mark transaction as processed
     */
    public function markAsProcessed(array $gatewayResponse = []): bool
    {
        return $this->update([
            'status' => PaymentStatus::COMPLETED,
            'processed_at' => now(),
            'gateway_response' => $gatewayResponse,
        ]);
    }

    /**
     * Mark transaction as failed
     */
    public function markAsFailed(string $reason = '', array $gatewayResponse = []): bool
    {
        return $this->update([
            'status' => PaymentStatus::FAILED,
            'failed_at' => now(),
            'notes' => $reason,
            'gateway_response' => $gatewayResponse,
        ]);
    }

    /**
     * Process refund
     */
    public function processRefund(float $amount, string $reason = ''): bool
    {
        if ($amount > $this->amount) {
            throw new \InvalidArgumentException('Refund amount cannot exceed original amount');
        }

        return $this->update([
            'refunded_at' => now(),
            'refund_amount' => $amount,
            'refund_reason' => $reason,
        ]);
    }

    /**
     * Add metadata
     */
    public function addMetadata(string $key, $value): bool
    {
        $metadata = $this->metadata ?? [];
        $metadata[$key] = $value;
        
        return $this->update(['metadata' => $metadata]);
    }

    /**
     * Get metadata value
     */
    public function getMetadata(string $key, $default = null)
    {
        return ($this->metadata ?? [])[$key] ?? $default;
    }
}