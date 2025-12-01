<?php

declare(strict_types=1);

namespace Yiisoft\Payments\Model;

/**
 * Describes a payment method used with a gateway.
 * Example: providerType=PAYPAL, methodCode="paypal_wallet" or "card".
 */
final class PaymentMethod
{
    /**
     * @param PaymentMethodType $providerType Provider such as PayPal or Robokassa.
     * @param string $methodCode Logical code of payment method (card, wallet, etc.).
     * @param array<string,mixed> $details Optional extra data (token, card mask, etc.).
     */
    public function __construct(
        public readonly PaymentMethodType $providerType,
        public readonly string $methodCode,
        public array $details = [],
    ) {
    }
}
