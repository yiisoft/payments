<?php

declare(strict_types=1);

namespace Yiisoft\Payments\Webhooks;

/**
 * Normalized support status for webhook capability declarations.
 */
enum WebhookSupportStatus: string
{
    case Supported = 'supported';
    case PartiallySupported = 'partially_supported';
    case Unsupported = 'unsupported';
}
