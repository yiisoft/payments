<?php

declare(strict_types=1);

namespace Yiisoft\Payments\Models;

/**
 * Represents a customer in the payment gateway system.
 */
readonly class Customer
{
    /**
     * @param string|null $id The customer's unique identifier in the payment gateway.
     * @param string|null $email The customer's email address.
     * @param string|null $name The customer's full name.
     * @param string|null $phone The customer's phone number.
     * @param array|null $address The customer's billing/shipping address.
     * @param array|null $metadata Additional metadata about the customer.
     * @param string|null $description A description of the customer.
     */
    public function __construct(
        public ?string $id = null,
        public ?string $email = null,
        public ?string $name = null,
        public ?string $phone = null,
        public ?array $address = null,
        public ?array $metadata = null,
        public ?string $description = null,
    ) {
    }

    /**
     * Converts the customer object to an array.
     *
     * @return array<string, mixed> The customer data as an array.
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'email' => $this->email,
            'name' => $this->name,
            'phone' => $this->phone,
            'address' => $this->address,
            'metadata' => $this->metadata,
            'description' => $this->description,
        ];
    }

    /**
     * Creates a new Customer instance from an array of data.
     *
     * @param array<string, mixed> $data The customer data.
     * @return self A new Customer instance.
     */
    public static function fromArray(array $data): self
    {
        return new self(
            $data['id'] ?? null,
            $data['email'] ?? null,
            $data['name'] ?? null,
            $data['phone'] ?? null,
            $data['address'] ?? null,
            $data['metadata'] ?? null,
            $data['description'] ?? null,
        );
    }
}
