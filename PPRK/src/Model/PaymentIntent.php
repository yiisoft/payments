<?php

declare(strict_types=1);

namespace Yiisoft\Payments\Model;

use DateTimeImmutable;

/**
 * Represents a payment intent, such as an order or invoice to be paid.
 */
final class PaymentIntent
{
    /**
     * @param string $id Internal ID of the payment intent.
     * @param Customer $customer Customer who is paying.
     * @param float $amount Total amount to charge.
     * @param string $currency ISO 4217 currency code (USD, EUR, RUB, etc.).
     * @param string|null $description Description shown to the payer.
     * @param PaymentMethod|null $method Payment method configuration.
     * @param string $status Application-level status (created, paid, refunded, etc.).
     * @param DateTimeImmutable $createdAt Creation timestamp.
     * @param DateTimeImmutable|null $updatedAt Last update timestamp.
     * @param array<string,mixed> $metadata Extra data such as provider IDs.
     */
    public function __construct(
        public string $id,
        public Customer $customer,
        public float $amount,
        public string $currency,
        public ?string $description = null,
        public ?PaymentMethod $method = null,
        public string $status = 'created',
        public DateTimeImmutable $createdAt = new DateTimeImmutable(),
        public ?DateTimeImmutable $updatedAt = null,
        public array $metadata = [],
    ) {
    }
}
