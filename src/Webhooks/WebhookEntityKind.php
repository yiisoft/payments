<?php

declare(strict_types=1);

namespace Yiisoft\Payments\Webhooks;

/**
 * Normalized webhook entity kinds supported by the library-level webhook contracts.
 */
enum WebhookEntityKind: string
{
    case Payment = 'payment';
}
