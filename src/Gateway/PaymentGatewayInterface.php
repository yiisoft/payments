<?php
declare(strict_types=1);

namespace Yiisoft\Payments\Gateway;

use Yiisoft\Payments\Model\PaymentIntent;

/**
 * PaymentGatewayInterface defines a provider-agnostic contract
 * for the Orders v2 lifecycle operations.
 */
interface PaymentGatewayInterface
{
    /**
     * Create a new order (payment intent).
     *
     * @param PaymentIntent $intent The payment intent to create.
     * @return PaymentIntent The updated intent with server response fields.
     */
    public function createPaymentIntent(PaymentIntent $intent): PaymentIntent;

    /**
     * Retrieve the approval URL for buyer redirection, if applicable.
     *
     * @param string $orderId The order ID to inspect.
     * @return string|null The approval URL or null if not found.
     */
    public function getApprovalUrl(string $orderId): ?string;

    /**
     * Confirm a payment source for the order (e.g., card) on the server.
     * Requires the account/features to support server-side confirmation.
     *
     * @param string               $orderId        The order ID to confirm.
     * @param array<string,mixed>  $paymentSource  The payment_source payload (e.g., ['card'=>[...]]).
     * @return PaymentIntent The response with updated status.
     */
    public function confirmPaymentIntent(string $orderId, array $paymentSource): PaymentIntent;

    /**
     * Capture funds for an approved order.
     *
     * @param string $orderId The order ID to capture.
     * @return PaymentIntent The response with capture status.
     */
    public function capturePaymentIntent(string $orderId): PaymentIntent;

    /**
     * Create a refund for an existing capture.
     *
     * @param string      $captureId The capture ID to refund.
     * @param float|null  $amount    Optional refund amount; if null, refund full remaining.
     * @param string|null $currency  ISO currency code; defaults to original if omitted.
     * @return bool True if refund created successfully.
     */
    public function createRefund(string $captureId, ?float $amount = null, ?string $currency = null): bool;

    /**
     * Retrieve order details by ID.
     *
     * @param string $orderId The order ID.
     * @return PaymentIntent The hydrated intent with server fields.
     */
    public function retrievePaymentIntent(string $orderId): PaymentIntent;
}
