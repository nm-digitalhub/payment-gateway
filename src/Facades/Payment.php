<?php

namespace NMDigitalHub\PaymentGateway\Facades;

use Illuminate\Support\Facades\Facade;
use NMDigitalHub\PaymentGateway\PaymentGatewayManager;
use NMDigitalHub\PaymentGateway\DataObjects\PaymentRequest;

/**
 * Payment Gateway Facade
 * 
 * @method static \NMDigitalHub\PaymentGateway\Contracts\PaymentProviderInterface payment(string $provider = null)
 * @method static \NMDigitalHub\PaymentGateway\Contracts\ServiceProviderInterface service(string $provider)
 * @method static \NMDigitalHub\PaymentGateway\DataObjects\PaymentSessionData createPayment(array $parameters, string $provider = null)
 * @method static \NMDigitalHub\PaymentGateway\DataObjects\PaymentSessionData processPaymentRequest(\NMDigitalHub\PaymentGateway\DataObjects\PaymentRequest $request, string $provider = null)
 * @method static \NMDigitalHub\PaymentGateway\DataObjects\TransactionData verifyPayment(string $reference, string $provider = null)
 * @method static \NMDigitalHub\PaymentGateway\DataObjects\TransactionData refundPayment(string $transactionId, float $amount = null, string $provider = null)
 * @method static array getProducts(string $serviceProvider, array $filters = [])
 * @method static array createServiceOrder(string $serviceProvider, array $orderData)
 * @method static array getServiceOrderStatus(string $serviceProvider, string $orderId)
 * @method static \NMDigitalHub\PaymentGateway\Models\PaymentPage|null getPaymentPage(string $slug)
 * @method static \NMDigitalHub\PaymentGateway\Models\PaymentPage createPaymentPage(array $data)
 * @method static \Illuminate\Support\Collection getPaymentPagesByType(string $type)
 * @method static \NMDigitalHub\PaymentGateway\Models\PaymentTransaction|null getTransaction(string $transactionId)
 * @method static \Illuminate\Support\Collection getTransactionsByCustomer(string $customerEmail)
 * @method static \Illuminate\Support\Collection getTransactionsByStatus(string $status, int $limit = 100)
 * @method static \NMDigitalHub\PaymentGateway\DataObjects\TransactionData|null handleWebhook(string $provider, array $payload, string $signature = '')
 * @method static \Illuminate\Support\Collection getAvailablePaymentProviders()
 * @method static \Illuminate\Support\Collection getAvailableServiceProviders()
 * @method static array getPaymentStats(array $filters = [])
 * @method static array getProviderStats()
 * @method static array syncAllProviders()
 * @method static array healthCheck()
 * @method static void setConfig(array $config)
 * @method static mixed getConfig(string $key = null)
 * 
 * @see \NMDigitalHub\PaymentGateway\PaymentGatewayManager
 */
class Payment extends Facade
{
    /**
     * Get the registered name of the component.
     */
    protected static function getFacadeAccessor(): string
    {
        return PaymentGatewayManager::class;
    }
    
    /**
     * יצירת בקשת תשלום חדשה עם Fluent Builder
     */
    public static function request(): PaymentRequest
    {
        return PaymentRequest::make();
    }
    
    /**
     * דרך קצרה ליצירת תשלום מהיר
     */
    public static function quick(float $amount, string $email, string $description = null): PaymentRequest
    {
        return PaymentRequest::make()
            ->amount($amount)
            ->customerEmail($email)
            ->description($description ?? 'תשלום');
    }
    
    /**
     * יצירת תשלום למודל ספציפי
     */
    public static function for(\Illuminate\Database\Eloquent\Model $model): PaymentRequest
    {
        return PaymentRequest::make()->model($model);
    }
}