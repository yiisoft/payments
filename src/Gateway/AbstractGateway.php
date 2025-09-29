<?php
declare(strict_types=1);

namespace Yiisoft\Payments\Gateway;

use Yiisoft\Payments\Model\PaymentIntent;

/**
 * AbstractGateway offers a base type for concrete gateways.
 */
abstract class AbstractGateway implements PaymentGatewayInterface
{
    /** @inheritDoc */
    abstract public function createPaymentIntent(PaymentIntent $intent): PaymentIntent;

    /** @inheritDoc */
    abstract public function getApprovalUrl(string $orderId): ?string;

    /** @inheritDoc */
    abstract public function confirmPaymentIntent(string $orderId, array $paymentSource): PaymentIntent;

    /** @inheritDoc */
    abstract public function capturePaymentIntent(string $orderId): PaymentIntent;

    /** @inheritDoc */
    abstract public function createRefund(string $captureId, ?float $amount = null, ?string $currency = null): bool;

    /** @inheritDoc */
    abstract public function retrievePaymentIntent(string $orderId): PaymentIntent;
}
