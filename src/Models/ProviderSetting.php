<?php

namespace NMDigitalHub\PaymentGateway\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Casts\Attribute;
use NMDigitalHub\PaymentGateway\Enums\PaymentProvider;

class ProviderSetting extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'provider_name',
        'provider_type', // 'payment' or 'service'
        'display_name',
        'description',
        'is_active',
        'is_test_mode',
        'configuration',
        'capabilities',
        'credentials',
        'webhook_url',
        'webhook_secret',
        'api_version',
        'priority',
        'rate_limit',
        'timeout',
        'retry_attempts',
        'last_health_check',
        'health_status',
        'health_message',
        'metadata',
        'team_id',
        'tenant_id',
        'environment',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'is_test_mode' => 'boolean',
        'configuration' => 'encrypted:array',
        'capabilities' => 'array',
        'credentials' => 'encrypted:array',
        'priority' => 'integer',
        'rate_limit' => 'integer',
        'timeout' => 'integer',
        'retry_attempts' => 'integer',
        'last_health_check' => 'datetime',
        'metadata' => 'array',
        'team_id' => 'integer',
        'tenant_id' => 'integer',
        'created_by' => 'integer',
        'updated_by' => 'integer',
    ];

    protected $hidden = [
        'credentials',
        'webhook_secret',
    ];

    protected $dates = [
        'last_health_check',
        'created_at',
        'updated_at',
        'deleted_at',
    ];

    /**
     * Get the user who created this setting
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(config('payment-gateway.user_model', 'App\Models\User'), 'created_by');
    }

    /**
     * Get the user who last updated this setting
     */
    public function updater(): BelongsTo
    {
        return $this->belongsTo(config('payment-gateway.user_model', 'App\Models\User'), 'updated_by');
    }

    /**
     * Scope: Active providers only
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope: By provider type (payment/service)
     */
    public function scopeByType($query, string $type)
    {
        return $query->where('provider_type', $type);
    }

    /**
     * Scope: Payment providers only
     */
    public function scopePaymentProviders($query)
    {
        return $query->where('provider_type', 'payment');
    }

    /**
     * Scope: Service providers only
     */
    public function scopeServiceProviders($query)
    {
        return $query->where('provider_type', 'service');
    }

    /**
     * Scope: Test mode providers
     */
    public function scopeTestMode($query, bool $testMode = true)
    {
        return $query->where('is_test_mode', $testMode);
    }

    /**
     * Scope: Production providers
     */
    public function scopeProduction($query)
    {
        return $query->where('is_test_mode', false);
    }

    /**
     * Scope: By environment
     */
    public function scopeByEnvironment($query, string $environment)
    {
        return $query->where('environment', $environment);
    }

    /**
     * Scope: Healthy providers
     */
    public function scopeHealthy($query)
    {
        return $query->where('health_status', 'healthy')
                    ->where('last_health_check', '>=', now()->subHours(2));
    }

    /**
     * Check if provider is healthy
     */
    public function isHealthy(): bool
    {
        return $this->health_status === 'healthy' && 
               $this->last_health_check && 
               $this->last_health_check->isAfter(now()->subHours(2));
    }

    /**
     * Check if provider supports a capability
     */
    public function supportsCapability(string $capability): bool
    {
        $capabilities = $this->capabilities ?? [];
        return in_array($capability, $capabilities) || 
               isset($capabilities[$capability]) && $capabilities[$capability] === true;
    }

    /**
     * Get capability value
     */
    public function getCapability(string $capability, $default = null)
    {
        $capabilities = $this->capabilities ?? [];
        return $capabilities[$capability] ?? $default;
    }

    /**
     * Get configuration value safely
     */
    public function getConfig(string $key, $default = null)
    {
        $config = $this->configuration ?? [];
        return $config[$key] ?? $default;
    }

    /**
     * Get credential value safely
     */
    public function getCredential(string $key, $default = null)
    {
        $credentials = $this->credentials ?? [];
        return $credentials[$key] ?? $default;
    }

    /**
     * Set configuration value
     */
    public function setConfig(string $key, $value): bool
    {
        $config = $this->configuration ?? [];
        $config[$key] = $value;
        return $this->update(['configuration' => $config]);
    }

    /**
     * Set credential value
     */
    public function setCredential(string $key, $value): bool
    {
        $credentials = $this->credentials ?? [];
        $credentials[$key] = $value;
        return $this->update(['credentials' => $credentials]);
    }

    /**
     * Update health status
     */
    public function updateHealthStatus(string $status, string $message = ''): bool
    {
        return $this->update([
            'health_status' => $status,
            'health_message' => $message,
            'last_health_check' => now(),
        ]);
    }

    /**
     * Mark as healthy
     */
    public function markAsHealthy(string $message = 'All systems operational'): bool
    {
        return $this->updateHealthStatus('healthy', $message);
    }

    /**
     * Mark as unhealthy
     */
    public function markAsUnhealthy(string $message): bool
    {
        return $this->updateHealthStatus('unhealthy', $message);
    }

    /**
     * Get provider class instance
     */
    public function getProviderInstance()
    {
        $className = $this->getConfig('provider_class');
        
        if (!$className || !class_exists($className)) {
            return null;
        }

        return app($className, ['settings' => $this]);
    }

    /**
     * Test provider connection
     */
    public function testConnection(): array
    {
        try {
            $provider = $this->getProviderInstance();
            
            if (!$provider) {
                return [
                    'success' => false,
                    'message' => 'Provider class not found or invalid',
                ];
            }

            if (method_exists($provider, 'testConnection')) {
                return $provider->testConnection();
            }

            return [
                'success' => true,
                'message' => 'Provider loaded successfully',
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage(),
                'error' => $e,
            ];
        }
    }

    /**
     * Get environment-specific settings
     */
    public function getEnvironmentConfig(): array
    {
        $environment = $this->environment ?: app()->environment();
        $config = $this->configuration ?? [];
        
        return $config[$environment] ?? $config;
    }

    /**
     * Get formatted display name
     */
    public function getDisplayNameAttribute(): string
    {
        if ($this->attributes['display_name']) {
            return $this->attributes['display_name'];
        }

        // Generate display name from provider name
        return ucwords(str_replace(['_', '-'], ' ', $this->provider_name));
    }

    /**
     * Get provider status badge
     */
    public function getStatusBadgeAttribute(): string
    {
        if (!$this->is_active) {
            return 'inactive';
        }

        if ($this->is_test_mode) {
            return 'test';
        }

        if ($this->isHealthy()) {
            return 'healthy';
        }

        return 'unhealthy';
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

    /**
     * Boot method to set default environment
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (!$model->environment) {
                $model->environment = app()->environment();
            }
            
            if (auth()->check()) {
                $model->created_by = auth()->id();
            }
        });

        static::updating(function ($model) {
            if (auth()->check()) {
                $model->updated_by = auth()->id();
            }
        });
    }
}