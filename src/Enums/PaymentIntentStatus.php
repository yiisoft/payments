<?php

declare(strict_types=1);

namespace Yiisoft\Payments\Enums;

enum PaymentIntentStatus: string
{
    case RequiresPaymentMethod = 'requires_payment_method';
    case RequiresConfirmation = 'requires_confirmation';
    case RequiresAction = 'requires_action';
    case Processing = 'processing';
    case RequiresCapture = 'requires_capture';
    case Canceled = 'canceled';
    case Succeeded = 'succeeded';
}
