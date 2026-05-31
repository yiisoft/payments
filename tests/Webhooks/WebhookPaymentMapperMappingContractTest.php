<?php

declare(strict_types=1);

namespace Yiisoft\Payments\Tests\Webhooks;

use PHPUnit\Framework\TestCase;
use Yiisoft\Payments\Webhooks\WebhookPaymentMapperInterface;
use Yiisoft\Payments\Webhooks\WebhookEventType;
use Yiisoft\Payments\Webhooks\WebhookPayload;
use Yiisoft\Payments\Webhooks\WebhookProcessingResult;
use Yiisoft\Payments\Webhooks\WebhookProcessingStatus;
use Yiisoft\Payments\Webhooks\WebhookRawData;

final class WebhookPaymentMapperMappingContractTest extends TestCase
{
    public function testMappingContractReturnsProcessedResultForSupportedPaymentPayload(): void
    {
        $rawData = new WebhookRawData(
            rawBody: '{"type":"payment_intent.succeeded","data":{"object":{"status":"succeeded"}}}',
            headers: ['Stripe-Signature' => 't=123,v1=signature'],
            payload: [
                'type' => 'payment_intent.succeeded',
                'data' => ['object' => ['status' => 'succeeded']],
            ],
            providerEventType: 'payment_intent.succeeded',
        );
        $payload = new WebhookPayload(
            providerId: 'stripe',
            eventType: WebhookEventType::PaymentSucceeded,
            providerEventType: 'payment_intent.succeeded',
            data: [
                'type' => 'payment_intent.succeeded',
                'data' => ['object' => ['status' => 'succeeded']],
            ],
            paymentStatus: 'succeeded',
            rawData: $rawData,
        );

        $result = $this->createContractMapper()->mapPaymentWebhook($payload);

        $this->assertSame(WebhookProcessingStatus::Processed, $result->status);
        $this->assertSame(WebhookEventType::PaymentSucceeded, $result->eventType);
        $this->assertNull($result->reason);
        $this->assertSame($rawData, $result->rawData);
    }

    public function testMappingContractReturnsUnknownEventWhenPayloadHasNoNormalizedEventType(): void
    {
        $payload = new WebhookPayload(
            providerId: 'stripe',
            eventType: null,
            providerEventType: 'payment_intent.provider_future_event',
            data: ['type' => 'payment_intent.provider_future_event'],
        );

        $result = $this->createContractMapper()->mapPaymentWebhook($payload);

        $this->assertSame(WebhookProcessingStatus::UnknownEvent, $result->status);
        $this->assertNull($result->eventType);
        $this->assertNotNull($result->reason);
        $this->assertSame('unknown_event_type', $result->reason->code->value);
        $this->assertSame('payment_intent.provider_future_event', $result->reason->providerEventType);
    }

    public function testMappingContractCanReturnUnsupportedResultForRecognizedButUnsupportedPaymentEvent(): void
    {
        $rawData = new WebhookRawData(
            rawBody: '{"type":"charge.refunded"}',
            headers: ['Stripe-Signature' => 't=123,v1=signature'],
            payload: ['type' => 'charge.refunded'],
            providerEventType: 'charge.refunded',
        );
        $payload = new WebhookPayload(
            providerId: 'stripe',
            eventType: WebhookEventType::PaymentRefunded,
            providerEventType: 'charge.refunded',
            data: ['type' => 'charge.refunded'],
            rawData: $rawData,
        );

        $result = $this->createContractMapper()->mapPaymentWebhook($payload);

        $this->assertSame(WebhookProcessingStatus::UnsupportedEvent, $result->status);
        $this->assertSame(WebhookEventType::PaymentRefunded, $result->eventType);
        $this->assertNotNull($result->reason);
        $this->assertSame('unsupported_event_type', $result->reason->code->value);
        $this->assertSame('charge.refunded', $result->reason->providerEventType);
        $this->assertSame($rawData, $result->rawData);
    }

    public function testMappingContractExtractsPaymentStatusFromPayload(): void
    {
        $payload = new WebhookPayload(
            providerId: 'paypal',
            eventType: WebhookEventType::PaymentSucceeded,
            providerEventType: 'PAYMENT.CAPTURE.COMPLETED',
            data: ['resource' => ['status' => 'COMPLETED']],
            paymentStatus: 'COMPLETED',
        );

        $this->assertSame('COMPLETED', $this->createContractMapper()->extractPaymentStatus($payload));
    }

    public function testMappingContractAllowsMissingPaymentStatus(): void
    {
        $payload = new WebhookPayload(
            providerId: 'robokassa',
            eventType: WebhookEventType::PaymentSucceeded,
            providerEventType: 'result_url',
            data: ['InvId' => '123'],
        );

        $this->assertNull($this->createContractMapper()->extractPaymentStatus($payload));
    }

    private function createContractMapper(): WebhookPaymentMapperInterface
    {
        return new class implements WebhookPaymentMapperInterface {
            public function mapPaymentWebhook(WebhookPayload $payload): WebhookProcessingResult
            {
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
