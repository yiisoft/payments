<?php

declare(strict_types=1);

namespace Yiisoft\Payments\Model;

/**
 * High-level payment provider type.
 */
enum PaymentMethodType: string
{
    case PAYPAL = 'paypal';
    case ROBOKASSA = 'robokassa';
    case STRIPE = 'stripe';
}
