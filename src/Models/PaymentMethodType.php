<?php

declare(strict_types=1);

namespace Yiisoft\Payments\Models;

/**
 * Contains constants representing different types of payment methods.
 */
final class PaymentMethodType
{
    /**
     * Credit or debit card payment method.
     */
    public const CARD = 'card';

    /**
     * PayPal payment method.
     */
    public const PAYPAL = 'paypal';

    /**
     * SEPA Direct Debit payment method.
     */
    public const SEPA_DEBIT = 'sepa_debit';

    /**
     * Get all available payment method types.
     *
     * @return array<string, string> Array of payment method types where key is the constant name and value is the type.
     */
    public static function all(): array
    {
        return [
            'CARD' => self::CARD,
            'PAYPAL' => self::PAYPAL,
            'SEPA_DEBIT' => self::SEPA_DEBIT,
        ];
    }

    /**
     * Check if the given type is a valid payment method type.
     *
     * @param string $type The type to check.
     * @return bool Whether the type is valid.
     */
    public static function isValid(string $type): bool
    {
        return in_array($type, self::all(), true);
    }
}
