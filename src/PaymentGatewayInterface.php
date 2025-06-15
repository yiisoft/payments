<?php

declare(strict_types=1);

namespace Yiisoft\Payments;

use Yiisoft\Payments\Models\Customer;
use Yiisoft\Payments\Models\PaymentIntent;
use Yiisoft\Payments\Models\PaymentMethod;

/**
 * Payment Gateway Interface
 * 
 * This interface defines the standard contract that all payment gateway implementations must follow.
 * It provides a consistent API for processing payments, managing customers, and handling payment methods
 * across different payment service providers.
 *
 * Implementations of this interface should handle the communication with specific payment gateways
 * (like Stripe, PayPal, etc.) while providing a unified interface to the application.
 *
 * @package Yiisoft\\Payments\\Core
 */
interface PaymentGatewayInterface
{
    /**
     * Creates a new customer in the payment gateway
     */
    public function createCustomer(Customer $customer): Customer;

    /**
     * Retrieves an existing customer from the payment gateway
     */
    public function retrieveCustomer(string $customerId): Customer;

    /**
     * Updates an existing customer in the payment gateway
     */
    public function updateCustomer(Customer $customer): Customer;

    /**
     * Deletes a customer from the payment gateway
     */
    public function deleteCustomer(string $customerId): void;

    /**
     * Creates a payment method
     */
    public function createPaymentMethod(PaymentMethod $paymentMethod): PaymentMethod;

    /**
     * Attaches a payment method to a customer
     */
    public function attachPaymentMethod(
        string $paymentMethodId, 
        string $customerId
    ): PaymentMethod;

    /**
     * Creates a payment intent
     */
    public function createPaymentIntent(PaymentIntent $intent): PaymentIntent;

    /**
     * Confirms a payment intent
     */
    public function confirmPaymentIntent(string $intentId, array $params = []): PaymentIntent;

    /**
     * Captures a payment intent
     */
    public function capturePaymentIntent(string $intentId, array $params = []): PaymentIntent;

    /**
     * Cancels a payment intent
     */
    public function cancelPaymentIntent(string $intentId, array $params = []): PaymentIntent;

    /**
     * Refunds a payment
     */
    public function createRefund(string $paymentIntentId, array $params = []): array;

    /**
     * Retrieves a payment intent
     */
    public function retrievePaymentIntent(string $intentId): PaymentIntent;
}
