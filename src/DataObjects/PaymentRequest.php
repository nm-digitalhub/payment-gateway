<?php

namespace NMDigitalHub\PaymentGateway\DataObjects;

use Illuminate\Database\Eloquent\Model;
use Laravel\SerializableClosure\SerializableClosure;

class PaymentRequest
{
    protected ?Model $model = null;
    protected ?string $currency = null;
    protected ?float $amount = null;
    protected ?string $customerName = null;
    protected ?string $customerEmail = null;
    protected ?string $customerPhone = null;
    protected array $billingAddress = [];
    protected array $shippingAddress = [];
    protected ?string $successUrl = null;
    protected ?string $cancelUrl = null;
    protected ?string $webhookUrl = null;
    protected array $metadata = [];
    protected ?string $description = null;
    protected ?string $reference = null;
    protected ?SerializableClosure $onSuccess = null;
    protected ?SerializableClosure $onFailed = null;
    protected bool $savePaymentMethod = false;
    protected ?string $provider = null;

    public static function make(): self
    {
        return new self();
    }

    public function model(Model $model): self
    {
        $this->model = $model;
        return $this;
    }

    public function currency(string $currency): self
    {
        $this->currency = $currency;
        return $this;
    }

    public function amount(float $amount): self
    {
        $this->amount = $amount;
        return $this;
    }

    public function customer(string $name, string $email, ?string $phone = null): self
    {
        $this->customerName = $name;
        $this->customerEmail = $email;
        $this->customerPhone = $phone;
        return $this;
    }

    public function customerName(string $name): self
    {
        $this->customerName = $name;
        return $this;
    }

    public function customerEmail(string $email): self
    {
        $this->customerEmail = $email;
        return $this;
    }

    public function customerPhone(string $phone): self
    {
        $this->customerPhone = $phone;
        return $this;
    }

    public function billingAddress(array $address): self
    {
        $this->billingAddress = $address;
        return $this;
    }

    public function shippingAddress(array $address): self
    {
        $this->shippingAddress = $address;
        return $this;
    }

    public function successUrl(string $url): self
    {
        $this->successUrl = $url;
        return $this;
    }

    public function cancelUrl(string $url): self
    {
        $this->cancelUrl = $url;
        return $this;
    }

    public function webhookUrl(string $url): self
    {
        $this->webhookUrl = $url;
        return $this;
    }

    public function metadata(array $metadata): self
    {
        $this->metadata = array_merge($this->metadata, $metadata);
        return $this;
    }

    public function description(string $description): self
    {
        $this->description = $description;
        return $this;
    }

    public function reference(string $reference): self
    {
        $this->reference = $reference;
        return $this;
    }

    public function onSuccess(\Closure $callback): self
    {
        $this->onSuccess = new SerializableClosure($callback);
        return $this;
    }

    public function onFailed(\Closure $callback): self
    {
        $this->onFailed = new SerializableClosure($callback);
        return $this;
    }

    public function savePaymentMethod(bool $save = true): self
    {
        $this->savePaymentMethod = $save;
        return $this;
    }

    public function provider(string $provider): self
    {
        $this->provider = $provider;
        return $this;
    }

    public function toArray(): array
    {
        return [
            'model' => $this->model,
            'currency' => $this->currency ?? 'ILS',
            'amount' => $this->amount,
            'customer_name' => $this->customerName,
            'customer_email' => $this->customerEmail,
            'customer_phone' => $this->customerPhone,
            'email' => $this->customerEmail, // Alias for compatibility
            'phone' => $this->customerPhone, // Alias for compatibility
            'billing_address' => $this->billingAddress,
            'shipping_address' => $this->shippingAddress,
            'success_url' => $this->successUrl,
            'failed_url' => $this->cancelUrl,
            'webhook_url' => $this->webhookUrl,
            'metadata' => array_merge($this->metadata, [
                'model_type' => $this->model ? get_class($this->model) : null,
                'model_id' => $this->model?->getKey(),
            ]),
            'description' => $this->description,
            'product_name' => $this->description, // Alias for compatibility
            'reference' => $this->reference,
            'save_token' => $this->savePaymentMethod,
            'save_payment_method' => $this->savePaymentMethod,
            'provider' => $this->provider,
        ];
    }

    // Getters
    public function getModel(): ?Model
    {
        return $this->model;
    }

    public function getCurrency(): ?string
    {
        return $this->currency;
    }

    public function getAmount(): ?float
    {
        return $this->amount;
    }

    public function getCustomerName(): ?string
    {
        return $this->customerName;
    }

    public function getCustomerEmail(): ?string
    {
        return $this->customerEmail;
    }

    public function getCustomerPhone(): ?string
    {
        return $this->customerPhone;
    }

    public function getBillingAddress(): array
    {
        return $this->billingAddress;
    }

    public function getShippingAddress(): array
    {
        return $this->shippingAddress;
    }

    public function getSuccessUrl(): ?string
    {
        return $this->successUrl;
    }

    public function getCancelUrl(): ?string
    {
        return $this->cancelUrl;
    }

    public function getWebhookUrl(): ?string
    {
        return $this->webhookUrl;
    }

    public function getMetadata(): array
    {
        return $this->metadata;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function getReference(): ?string
    {
        return $this->reference;
    }

    public function getOnSuccess(): ?SerializableClosure
    {
        return $this->onSuccess;
    }

    public function getOnFailed(): ?SerializableClosure
    {
        return $this->onFailed;
    }

    public function shouldSavePaymentMethod(): bool
    {
        return $this->savePaymentMethod;
    }

    public function getProvider(): ?string
    {
        return $this->provider;
    }

    public function validate(): array
    {
        $errors = [];

        if (!$this->amount || $this->amount <= 0) {
            $errors[] = 'Amount is required and must be greater than 0';
        }

        if (!$this->customerEmail) {
            $errors[] = 'Customer email is required';
        }

        if ($this->customerEmail && !filter_var($this->customerEmail, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Customer email must be a valid email address';
        }

        if (!$this->currency) {
            $errors[] = 'Currency is required';
        }

        return $errors;
    }

    public function isValid(): bool
    {
        return empty($this->validate());
    }
}