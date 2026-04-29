<?php

declare(strict_types=1);

namespace Yiisoft\Payments\Tests\Webhooks;

use PHPUnit\Framework\TestCase;
use Yiisoft\Payments\Webhooks\WebhookCapabilities;
use Yiisoft\Payments\Webhooks\WebhookCapability;
use Yiisoft\Payments\Webhooks\WebhookEntityKind;
use Yiisoft\Payments\Webhooks\WebhookEventType;
use Yiisoft\Payments\Webhooks\WebhookProcessingStatus;
use Yiisoft\Payments\Webhooks\WebhookRawData;
use Yiisoft\Payments\Webhooks\WebhookSupportStatus;

final class WebhookCapabilitiesTest extends TestCase
{
    public function testWebhookCapabilityStoresNormalizedDeclaration(): void
    {
        $capability = new WebhookCapability(
            eventType: WebhookEventType::PaymentSucceeded,
            entityKind: WebhookEntityKind::Payment,
            supportStatus: WebhookSupportStatus::Supported,
        );

        $this->assertSame(WebhookEventType::PaymentSucceeded, $capability->eventType);
        $this->assertSame(WebhookEntityKind::Payment, $capability->entityKind);
        $this->assertSame(WebhookSupportStatus::Supported, $capability->supportStatus);
    }

    public function testWebhookCapabilitiesReturnsDeclaredCapabilities(): void
    {
        $first = new WebhookCapability(
            eventType: WebhookEventType::PaymentSucceeded,
            entityKind: WebhookEntityKind::Payment,
            supportStatus: WebhookSupportStatus::Supported,
        );
        $second = new WebhookCapability(
            eventType: WebhookEventType::PaymentRefunded,
            entityKind: WebhookEntityKind::Payment,
            supportStatus: WebhookSupportStatus::PartiallySupported,
        );

        $capabilities = new WebhookCapabilities($first, $second);

        $this->assertCount(2, $capabilities);
        $this->assertSame([$first, $second], $capabilities->all());
        $this->assertSame([$first, $second], iterator_to_array($capabilities));
    }

    public function testUnsupportedCapabilityIsDeclaredExplicitly(): void
    {
        $capability = new WebhookCapability(
            eventType: WebhookEventType::PaymentRefunded,
            entityKind: WebhookEntityKind::Payment,
            supportStatus: WebhookSupportStatus::Unsupported,
        );

        $capabilities = new WebhookCapabilities($capability);

        $this->assertSame([$capability], $capabilities->all());
        $this->assertSame(WebhookSupportStatus::Unsupported, $capabilities->all()[0]->supportStatus);
    }

    public function testNotDeclaredCapabilityIsRepresentedByAbsence(): void
    {
        $capabilities = new WebhookCapabilities(new WebhookCapability(
            eventType: WebhookEventType::PaymentSucceeded,
            entityKind: WebhookEntityKind::Payment,
            supportStatus: WebhookSupportStatus::Supported,
        ));

        $this->assertFalse($this->hasCapability(
            $capabilities,
            WebhookEventType::PaymentRefunded,
            WebhookEntityKind::Payment,
        ));
    }

    public function testUnsupportedCapabilityCreatesUnsupportedProcessingResult(): void
    {
        $capabilities = new WebhookCapabilities(new WebhookCapability(
            eventType: WebhookEventType::PaymentRefunded,
            entityKind: WebhookEntityKind::Payment,
            supportStatus: WebhookSupportStatus::Unsupported,
        ));

        $result = $capabilities->unsupportedResultFor(
            WebhookEventType::PaymentRefunded,
            WebhookEntityKind::Payment,
            'charge.refunded',
        );

        $this->assertNotNull($result);
        $this->assertSame(WebhookProcessingStatus::UnsupportedEvent, $result->status);
        $this->assertSame(WebhookEventType::PaymentRefunded, $result->eventType);
        $this->assertNotNull($result->reason);
        $this->assertSame('unsupported_event_type', $result->reason->code->value);
        $this->assertSame('charge.refunded', $result->reason->providerEventType);
    }


    public function testUnsupportedCapabilityPassesRawDataToUnsupportedProcessingResult(): void
    {
        $capabilities = new WebhookCapabilities(new WebhookCapability(
            eventType: WebhookEventType::PaymentRefunded,
            entityKind: WebhookEntityKind::Payment,
            supportStatus: WebhookSupportStatus::Unsupported,
        ));
        $rawData = new WebhookRawData(
            rawBody: '{"type":"charge.refunded"}',
            payload: ['type' => 'charge.refunded'],
            providerEventType: 'charge.refunded',
        );

        $result = $capabilities->unsupportedResultFor(
            WebhookEventType::PaymentRefunded,
            WebhookEntityKind::Payment,
            'charge.refunded',
            $rawData,
        );

        $this->assertNotNull($result);
        $this->assertSame(WebhookProcessingStatus::UnsupportedEvent, $result->status);
        $this->assertSame($rawData, $result->rawData);
    }

    public function testSupportedCapabilityDoesNotCreateUnsupportedProcessingResult(): void
    {
        $capabilities = new WebhookCapabilities(new WebhookCapability(
            eventType: WebhookEventType::PaymentSucceeded,
            entityKind: WebhookEntityKind::Payment,
            supportStatus: WebhookSupportStatus::Supported,
        ));

        $this->assertNull($capabilities->unsupportedResultFor(
            WebhookEventType::PaymentSucceeded,
            WebhookEntityKind::Payment,
            'payment_intent.succeeded',
        ));
    }

    public function testMissingCapabilityDoesNotCreateUnsupportedProcessingResult(): void
    {
        $capabilities = new WebhookCapabilities(new WebhookCapability(
            eventType: WebhookEventType::PaymentSucceeded,
            entityKind: WebhookEntityKind::Payment,
            supportStatus: WebhookSupportStatus::Supported,
        ));

        $this->assertNull($capabilities->unsupportedResultFor(
            WebhookEventType::PaymentRefunded,
            WebhookEntityKind::Payment,
            'charge.refunded',
        ));
    }

    public function testWebhookCapabilitiesDoesNotExposeInternalStorage(): void
    {
        $capability = new WebhookCapability(
            eventType: WebhookEventType::PaymentFailed,
            entityKind: WebhookEntityKind::Payment,
            supportStatus: WebhookSupportStatus::Unsupported,
        );

        $capabilities = new WebhookCapabilities($capability);
        $declaredCapabilities = $capabilities->all();
        $declaredCapabilities[] = new WebhookCapability(
            eventType: WebhookEventType::PaymentCanceled,
            entityKind: WebhookEntityKind::Payment,
            supportStatus: WebhookSupportStatus::Unsupported,
        );

        $this->assertCount(1, $capabilities);
        $this->assertSame([$capability], $capabilities->all());
    }

    private function hasCapability(
        WebhookCapabilities $capabilities,
        WebhookEventType $eventType,
        WebhookEntityKind $entityKind,
    ): bool {
        foreach ($capabilities as $capability) {
            if ($capability->eventType === $eventType && $capability->entityKind === $entityKind) {
                return true;
            }
        }

        return false;
    }
}
