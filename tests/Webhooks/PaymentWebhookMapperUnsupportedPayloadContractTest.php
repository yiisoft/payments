<?php

declare(strict_types=1);

namespace Yiisoft\Payments\Tests\Webhooks;

use PHPUnit\Framework\TestCase;
use Yiisoft\Payments\Webhooks\PaymentWebhookMapperInterface;
use Yiisoft\Payments\Webhooks\WebhookEventType;
use Yiisoft\Payments\Webhooks\WebhookPayload;
use Yiisoft\Payments\Webhooks\WebhookProcessingResult;
use Yiisoft\Payments\Webhooks\WebhookProcessingStatus;
use Yiisoft\Payments\Webhooks\WebhookRawData;
use Yiisoft\Payments\Webhooks\WebhookReason;
use Yiisoft\Payments\Webhooks\WebhookReasonCode;

final class PaymentWebhookMapperUnsupportedPayloadContractTest extends TestCase
{
    public function testMappingContractReturnsUnsupportedEventForRecognizedButUnsupportedPaymentPayload(): void
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
        $payload = new WebhookPayload(
            providerId: 'stripe',
            eventType: WebhookEventType::PaymentRefunded,
            providerEventType: 'charge.refunded',
            data: [
                'type' => 'charge.refunded',
                'data' => ['object' => ['id' => 'ch_123']],
            ],
            rawData: $rawData,
        );

        $result = $this->createContractMapper()->mapPaymentWebhook($payload);

        $this->assertSame(WebhookProcessingStatus::UnsupportedEvent, $result->status);
        $this->assertSame(WebhookEventType::PaymentRefunded, $result->eventType);
        $this->assertNotNull($result->reason);
        $this->assertSame('unsupported_event_type', $result->reason->code->value);
        $this->assertSame(
            'Webhook event type is recognized but is not supported by the current webhook contract.',
            $result->reason->message,
        );
        $this->assertSame('charge.refunded', $result->reason->providerEventType);
        $this->assertSame($rawData, $result->rawData);
    }

    public function testMappingContractReturnsUnsupportedEventForRecognizedNonPaymentPayload(): void
    {
        $rawData = new WebhookRawData(
            rawBody: '{"type":"customer.created","data":{"object":{"id":"cus_123"}}}',
            headers: ['Stripe-Signature' => 't=123,v1=signature'],
            payload: [
                'type' => 'customer.created',
                'data' => ['object' => ['id' => 'cus_123']],
            ],
            providerEventType: 'customer.created',
        );
        $payload = new WebhookPayload(
            providerId: 'stripe',
            eventType: null,
            providerEventType: 'customer.created',
            data: [
                'type' => 'customer.created',
                'data' => ['object' => ['id' => 'cus_123']],
            ],
            rawData: $rawData,
        );

        $result = $this->createContractMapper(nonPaymentProviderEvents: ['customer.created'])->mapPaymentWebhook($payload);

        $this->assertSame(WebhookProcessingStatus::UnsupportedEvent, $result->status);
        $this->assertNull($result->eventType);
        $this->assertNotNull($result->reason);
        $this->assertSame('unsupported_event_type', $result->reason->code->value);
        $this->assertSame(
            'Provider event type is recognized but is outside the R1 payment webhook scope.',
            $result->reason->message,
        );
        $this->assertSame('customer.created', $result->reason->providerEventType);
        $this->assertSame($rawData, $result->rawData);
    }

    public function testMappingContractReturnsUnknownEventForUnrecognizedProviderPayload(): void
    {
        $payload = new WebhookPayload(
            providerId: 'stripe',
            eventType: null,
            providerEventType: 'provider.future_event',
            data: ['type' => 'provider.future_event'],
        );

        $result = $this->createContractMapper(nonPaymentProviderEvents: ['customer.created'])->mapPaymentWebhook($payload);

        $this->assertSame(WebhookProcessingStatus::UnknownEvent, $result->status);
        $this->assertNull($result->eventType);
        $this->assertNotNull($result->reason);
        $this->assertSame('unknown_event_type', $result->reason->code->value);
        $this->assertSame('provider.future_event', $result->reason->providerEventType);
    }

    /**
     * @param list<string> $nonPaymentProviderEvents
     */
    private function createContractMapper(array $nonPaymentProviderEvents = []): PaymentWebhookMapperInterface
    {
        return new class ($nonPaymentProviderEvents) implements PaymentWebhookMapperInterface {
            /**
             * @param list<string> $nonPaymentProviderEvents
             */
            public function __construct(
                private readonly array $nonPaymentProviderEvents,
            ) {
            }

            public function mapPaymentWebhook(WebhookPayload $payload): WebhookProcessingResult
            {
                if ($payload->providerEventType !== null && in_array($payload->providerEventType, $this->nonPaymentProviderEvents, true)) {
                    return new WebhookProcessingResult(
                        status: WebhookProcessingStatus::UnsupportedEvent,
                        reason: new WebhookReason(
                            code: new WebhookReasonCode('unsupported_event_type'),
                            message: 'Provider event type is recognized but is outside the R1 payment webhook scope.',
                            providerEventType: $payload->providerEventType,
                        ),
                        rawData: $payload->rawData,
                    );
                }

                if ($payload->eventType === null) {
                    return WebhookProcessingResult::unknownEvent($payload->providerEventType ?? '');
                }

                if ($payload->eventType === WebhookEventType::PaymentRefunded) {
                    return WebhookProcessingResult::unsupportedEvent(
                        $payload->eventType,
                        $payload->providerEventType,
                        $payload->rawData,
                    );
                }

                return new WebhookProcessingResult(
                    status: WebhookProcessingStatus::Processed,
                    eventType: $payload->eventType,
                    rawData: $payload->rawData,
                );
            }

            public function extractPaymentStatus(WebhookPayload $payload): ?string
            {
                return $payload->paymentStatus;
            }
        };
    }
}
