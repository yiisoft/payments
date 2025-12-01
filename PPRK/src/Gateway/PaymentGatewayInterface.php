<?php

declare(strict_types=1);

namespace Yiisoft\Payments\Gateway;

use Yiisoft\Payments\Model\PaymentIntent;

/**
 * Common interface for payment gateways.
 */
interface PaymentGatewayInterface
{
    /**
     * Create a payment on the provider side and return a redirect URL if needed.
     *
     * @return array{
     *   success:bool,
     *   status:string,
     *   redirect_url?:string,
     *   raw:array<string,mixed>
     * }
     */
    public function createPayment(PaymentIntent $intent): array;

    /**
     * Capture a payment after user approval.
     *
     * For PayPal this usually means capturing an order ID.
     * For Robokassa this usually means validating callback data.
     *
     * @param PaymentIntent $intent Local intent.
     * @param array<string,mixed> $providerData Data received from the provider (query/body).
     * @return array{
     *   success:bool,
     *   status:string,
     *   raw:array<string,mixed>
     * }
     */
    public function capture(PaymentIntent $intent, array $providerData = []): array;

    /**
     * Refund an already captured payment.
     *
     * @param float $amount Amount to refund.
     * @param string|null $currency Currency code; if null, intent currency is used.
     * @return array{
     *   success:bool,
     *   status:string,
     *   raw:array<string,mixed>
     * }
     */
    public function refund(PaymentIntent $intent, float $amount, ?string $currency = null): array;
}
