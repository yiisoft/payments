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

    /**
     * The webhook request failed provider-specific validation before event processing.
     */
    case ValidationFailed = 'validation_failed';

    /**
     * The webhook request was valid, but its event type was not recognized.
     */
    case UnknownEvent = 'unknown_event';

    /**
     * The webhook request was valid and recognized, but the event type is not supported by this gateway.
     */
    case UnsupportedEvent = 'unsupported_event';
}
