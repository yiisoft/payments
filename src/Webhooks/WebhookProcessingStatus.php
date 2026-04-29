<?php

declare(strict_types=1);

namespace Yiisoft\Payments\Webhooks;

/**
 * Normalized processing status for webhook handling results.
 */
enum WebhookProcessingStatus: string
{
    /**
     * The webhook request was validated, recognized, parsed, and mapped successfully.
     */
    case Processed = 'processed';
}
