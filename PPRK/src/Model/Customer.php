<?php

declare(strict_types=1);

namespace Yiisoft\Payments\Model;

/**
 * Represents a customer paying for an order.
 */
final class Customer
{
    public function __construct(
        public readonly string $id,
        public string $email,
        public ?string $phone = null,
        public ?string $name = null,
    ) {
    }
}
