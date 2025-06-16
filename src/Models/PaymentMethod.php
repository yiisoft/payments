<?php

declare(strict_types=1);

namespace Yiisoft\Payments\Models;

use Yiisoft\Payments\Enums\PaymentMethodType;

/**
 * Represents a payment method in the payment gateway system.
 */
readonly class PaymentMethod
{
    /**
     * @param string|null $id The unique identifier for the payment method.
     * @param PaymentMethodType|null $type The type of the payment method (e.g., 'card', 'paypal', 'sepa_debit').
     * @param array|null $details Additional details specific to the payment method.
     * @param string|null $customerId The ID of the customer this payment method belongs to.
     * @param array|null $billingDetails Billing information associated with the payment method.
     * @param array|null $metadata Additional metadata for the payment method.
     */
    public function __construct(
        public ?string $id = null,
        public ?PaymentMethodType $type = null,
        public ?array $details = null,
        public ?string $customerId = null,
        public ?array $billingDetails = null,
        public ?array $metadata = null,
    ) {
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type->value,
            'details' => $this->details,
            'customer_id' => $this->customerId,
            'billing_details' => $this->billingDetails,
            'metadata' => $this->metadata,
        ];
    }

    /**
     * Creates a new PaymentMethod instance from an array of data.
     *
     * @param array<string, mixed> $data The payment method data.
     * @return self A new PaymentMethod instance.
     */
    public static function fromArray(array $data): self
    {
        return new self(
            id: $data['id'] ?? null,
            type: PaymentMethodType::tryFrom($data['type']),
            details: $data['details'] ?? null,
            customerId: $data['customer_id'] ?? $data['customerId'] ?? null,
            billingDetails: $data['billing_details'] ?? $data['billingDetails'] ?? null,
            metadata: $data['metadata'] ?? null,
        );
    }
}
