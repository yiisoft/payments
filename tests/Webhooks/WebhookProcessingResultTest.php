<?php

declare(strict_types=1);

namespace Yiisoft\Payments\Tests\Webhooks;

use PHPUnit\Framework\TestCase;
use Yiisoft\Payments\Webhooks\WebhookEventType;
use Yiisoft\Payments\Webhooks\WebhookProcessingResult;
use Yiisoft\Payments\Webhooks\WebhookProcessingStatus;
use Yiisoft\Payments\Webhooks\WebhookRawData;

final class WebhookProcessingResultTest extends TestCase
{
    public function testUnknownProviderEventTypeHasUnknownEventResult(): void
    {
        $result = WebhookProcessingResult::unknownEvent('payment_intent.partially_refunded');

        $this->assertSame(WebhookProcessingStatus::UnknownEvent, $result->status);
        $this->assertNull($result->eventType);
        $this->assertNotNull($result->reason);
        $this->assertSame('unknown_event_type', $result->reason->code->value);
        $this->assertSame(
            'Provider event type is not recognized by the webhook event mapping.',
            $result->reason->message,
        );
        $this->assertSame('payment_intent.partially_refunded', $result->reason->providerEventType);
    }

    public function testValidUnknownProviderEventTypeDoesNotThrowException(): void
    {
        $result = WebhookProcessingResult::unknownEvent('provider.event.not_in_mapping');

        $this->assertSame(WebhookProcessingStatus::UnknownEvent, $result->status);
        $this->assertNull($result->eventType);
        $this->assertNotNull($result->reason);
        $this->assertSame('provider.event.not_in_mapping', $result->reason->providerEventType);
    }

    public function testUnknownProviderEventTypeKeepsRawProviderValueUnchanged(): void
    {
        $result = WebhookProcessingResult::unknownEvent('PAYMENT.CAPTURE.PENDING');

        $this->assertNotNull($result->reason);
        $this->assertSame('PAYMENT.CAPTURE.PENDING', $result->reason->providerEventType);
    }

    public function testUnknownProviderEventTypeDoesNotCreateNormalizedEventType(): void
    {
        $result = WebhookProcessingResult::unknownEvent('invoice.payment_succeeded.extra');

        $this->assertSame(WebhookProcessingStatus::UnknownEvent, $result->status);
        $this->assertNull($result->eventType);
        $this->assertNotNull($result->reason);
        $this->assertSame('unknown_event_type', $result->reason->code->value);
    }

    public function testUnknownProviderEventTypeResultCanBeCreatedForDifferentProviderFormats(): void
    {
        $providerEventTypes = [
            'payment_intent.requires_action.unmapped',
            'PAYMENT.CAPTURE.REVERSED.UNMAPPED',
            'payment.waiting_for_capture.unmapped',
            'robokassa.operation.unmapped',
        ];

        foreach ($providerEventTypes as $providerEventType) {
            $result = WebhookProcessingResult::unknownEvent($providerEventType);

            $this->assertSame(WebhookProcessingStatus::UnknownEvent, $result->status);
            $this->assertNull($result->eventType);
            $this->assertNotNull($result->reason);
            $this->assertSame($providerEventType, $result->reason->providerEventType);
        }
    }

    public function testKnownUnsupportedEventTypeHasUnsupportedEventResult(): void
    {
        $result = WebhookProcessingResult::unsupportedEvent(WebhookEventType::PaymentRefunded);

        $this->assertSame(WebhookProcessingStatus::UnsupportedEvent, $result->status);
        $this->assertSame(WebhookEventType::PaymentRefunded, $result->eventType);
        $this->assertNotNull($result->reason);
        $this->assertSame('unsupported_event_type', $result->reason->code->value);
        $this->assertSame(
            'Webhook event type is recognized but is not supported by the current webhook contract.',
            $result->reason->message,
        );
        $this->assertNull($result->reason->providerEventType);
    }


    public function testKnownUnsupportedEventTypeCanKeepRawDataForFallbackDebug(): void
    {
        $rawData = new WebhookRawData(
            rawBody: '{"type":"charge.refunded","data":{"object":{"id":"ch_123"}}}',
            headers: ['Stripe-Signature' => 't=123,v1=signature'],
            payload: [
                'type' => 'charge.refunded',
                'data' => ['object' => ['id' => 'ch_123']],
            ],
            providerEventType: 'charge.refunded',
        );

        $result = WebhookProcessingResult::unsupportedEvent(
            WebhookEventType::PaymentRefunded,
            'charge.refunded',
            $rawData,
        );

        $this->assertSame(WebhookProcessingStatus::UnsupportedEvent, $result->status);
        $this->assertSame(WebhookEventType::PaymentRefunded, $result->eventType);
        $this->assertSame($rawData, $result->rawData);
        $this->assertSame('{"type":"charge.refunded","data":{"object":{"id":"ch_123"}}}', $result->rawData->rawBody);
        $this->assertSame(['Stripe-Signature' => 't=123,v1=signature'], $result->rawData->headers);
        $this->assertSame('charge.refunded', $result->rawData->providerEventType);
    }

    public function testKnownUnsupportedEventTypeCanKeepProviderEventType(): void
    {
        $result = WebhookProcessingResult::unsupportedEvent(
            WebhookEventType::PaymentRefunded,
            'charge.refunded',
        );

        $this->assertSame(WebhookProcessingStatus::UnsupportedEvent, $result->status);
        $this->assertSame(WebhookEventType::PaymentRefunded, $result->eventType);
        $this->assertNotNull($result->reason);
        $this->assertSame('charge.refunded', $result->reason->providerEventType);
    }

}
