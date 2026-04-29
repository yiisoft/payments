<?php

declare(strict_types=1);

namespace Yiisoft\Payments\Tests\Webhooks;

use PHPUnit\Framework\TestCase;
use Yiisoft\Payments\Webhooks\WebhookEntityKind;
use Yiisoft\Payments\Webhooks\WebhookEventType;
use Yiisoft\Payments\Webhooks\WebhookProcessingStatus;
use Yiisoft\Payments\Webhooks\WebhookSupportStatus;

final class WebhookEnumTest extends TestCase
{
    public function testWebhookEventTypeValues(): void
    {
        $this->assertSame('payment.created', WebhookEventType::PaymentCreated->value);
        $this->assertSame('payment.processing', WebhookEventType::PaymentProcessing->value);
        $this->assertSame('payment.requires_action', WebhookEventType::PaymentRequiresAction->value);
        $this->assertSame('payment.requires_capture', WebhookEventType::PaymentRequiresCapture->value);
        $this->assertSame('payment.succeeded', WebhookEventType::PaymentSucceeded->value);
        $this->assertSame('payment.failed', WebhookEventType::PaymentFailed->value);
        $this->assertSame('payment.canceled', WebhookEventType::PaymentCanceled->value);
        $this->assertSame('payment.refunded', WebhookEventType::PaymentRefunded->value);
    }

    public function testWebhookEntityKindValues(): void
    {
        $this->assertSame('payment', WebhookEntityKind::Payment->value);
    }

    public function testWebhookProcessingStatusValues(): void
    {
        $this->assertSame('processed', WebhookProcessingStatus::Processed->value);
        $this->assertSame('validation_failed', WebhookProcessingStatus::ValidationFailed->value);
    }

    public function testWebhookSupportStatusValues(): void
    {
        $this->assertSame('supported', WebhookSupportStatus::Supported->value);
        $this->assertSame('partially_supported', WebhookSupportStatus::PartiallySupported->value);
        $this->assertSame('unsupported', WebhookSupportStatus::Unsupported->value);
    }
}
