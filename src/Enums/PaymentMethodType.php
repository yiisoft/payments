<?php

declare(strict_types=1);

namespace Yiisoft\Payments\Enums;

enum PaymentMethodType: string
{
    case Card = 'card';
    case PayPal = 'paypal';
    case SepaDebit = 'sepa_debit';
}
