<?php

declare(strict_types=1);

namespace Yiisoft\Payments\Webhooks;

/**
 * Normalized webhook event types supported by the library-level webhook contracts.
 */
enum WebhookEventType: string
{
    case PaymentCreated = 'payment.created';
    case PaymentProcessing = 'payment.processing';
    case PaymentRequiresAction = 'payment.requires_action';
    case PaymentRequiresCapture = 'payment.requires_capture';
    case PaymentSucceeded = 'payment.succeeded';
    case PaymentFailed = 'payment.failed';
    case PaymentCanceled = 'payment.canceled';
    case PaymentRefunded = 'payment.refunded';
}
