<?php

declare(strict_types=1);

namespace Yiisoft\Payments\Models;



/**
 * Represents a payment method in the payment gateway system.
 */
readonly class PaymentMethod
{
    /** @deprecated Use PaymentMethodType::CARD instead */
    public const TYPE_CARD = PaymentMethodType::CARD;
    /** @deprecated Use PaymentMethodType::PAYPAL instead */
    public const TYPE_PAYPAL = PaymentMethodType::PAYPAL;
    /** @deprecated Use PaymentMethodType::SEPA_DEBIT instead */
    public const TYPE_SEPA_DEBIT = PaymentMethodType::SEPA_DEBIT;

    /**
     * @param string|null $id The unique identifier for the payment method.
     * @param string|null $type The type of the payment method (e.g., PaymentMethodType::CARD, PaymentMethodType::PAYPAL, PaymentMethodType::SEPA_DEBIT).
     * @param array|null $details Additional details specific to the payment method.
     * @param string|null $customerId The ID of the customer this payment method belongs to.
     * @param array|null $billingDetails Billing information associated with the payment method.
     * @param array|null $metadata Additional metadata for the payment method.
     */
    public function __construct(
        public ?string $id = null,
        public ?string $type = null,
        public ?array $details = null,
        public ?string $customerId = null,
        public ?array $billingDetails = null,
        public ?array $metadata = null,
    ) {
        if ($type !== null && !PaymentMethodType::isValid($type)) {
            throw new \InvalidArgumentException(sprintf(
                'Invalid payment method type "%s". Must be one of: %s',
                $type,
                implode(', ', array_values(PaymentMethodType::all()))
            ));
        }
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
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
            type: $data['type'] ?? null,
            details: $data['details'] ?? null,
            customerId: $data['customer_id'] ?? $data['customerId'] ?? null,
            billingDetails: $data['billing_details'] ?? $data['billingDetails'] ?? null,
            metadata: $data['metadata'] ?? null,
        );
    }
}
