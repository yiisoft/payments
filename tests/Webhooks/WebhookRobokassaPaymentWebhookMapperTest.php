<?php

declare(strict_types=1);

namespace Yiisoft\Payments\Tests\Webhooks;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Yiisoft\Payments\Webhooks\PaymentWebhookMapperInterface;
use Yiisoft\Payments\Webhooks\WebhookEventType;
use Yiisoft\Payments\Webhooks\WebhookPayload;
use Yiisoft\Payments\Webhooks\WebhookProcessingStatus;
use Yiisoft\Payments\Webhooks\WebhookRawData;
use Yiisoft\Payments\Webhooks\WebhookRobokassaCallbackFormat;
use Yiisoft\Payments\Webhooks\WebhookRobokassaPaymentWebhookMapper;

final class WebhookRobokassaPaymentWebhookMapperTest extends TestCase
{
    public function testImplementsPaymentWebhookMapperInterface(): void
    {
        $mapper = new WebhookRobokassaPaymentWebhookMapper();

        $this->assertInstanceOf(PaymentWebhookMapperInterface::class, $mapper);
    }

    public function testMapsSupportedRobokassaCallbackToProcessedResult(): void
    {
        $mapper = new WebhookRobokassaPaymentWebhookMapper();
        $rawData = new WebhookRawData(
            rawBody: '',
            headers: ['Content-Type' => 'application/x-www-form-urlencoded'],
            payload: [
                'OutSum' => '100.00',
                'InvId' => '123',
                'SignatureValue' => 'signature',
            ],
            providerEventType: WebhookRobokassaCallbackFormat::CALLBACK_TYPE,
            bodyParams: [
                'OutSum' => '100.00',
                'InvId' => '123',
                'SignatureValue' => 'signature',
            ],
        );
        $payload = new WebhookPayload(
            providerId: WebhookRobokassaCallbackFormat::PROVIDER_ID,
            eventType: WebhookEventType::PaymentSucceeded,
            providerEventType: WebhookRobokassaCallbackFormat::CALLBACK_TYPE,
            data: [
                'OutSum' => '100.00',
                'InvId' => '123',
                'SignatureValue' => 'signature',
            ],
            rawData: $rawData,
        );

        $result = $mapper->mapPaymentWebhook($payload);

        $this->assertSame(WebhookProcessingStatus::Processed, $result->status);
        $this->assertSame(WebhookEventType::PaymentSucceeded, $result->eventType);
        $this->assertNull($result->reason);
        $this->assertSame($rawData, $result->rawData);
    }

    public function testMapsSupportedRobokassaQueryCallbackToProcessedResultAndPreservesRawData(): void
    {
        $mapper = new WebhookRobokassaPaymentWebhookMapper();
        $rawData = new WebhookRawData(
            rawBody: '',
            headers: ['Content-Type' => 'application/x-www-form-urlencoded'],
            payload: [
                'OutSum' => '100.00',
                'InvId' => '123',
                'SignatureValue' => 'signature',
            ],
            providerEventType: WebhookRobokassaCallbackFormat::CALLBACK_TYPE,
            queryParams: [
                'OutSum' => '100.00',
                'InvId' => '123',
                'SignatureValue' => 'signature',
            ],
        );
        $payload = new WebhookPayload(
            providerId: WebhookRobokassaCallbackFormat::PROVIDER_ID,
            eventType: WebhookEventType::PaymentSucceeded,
            providerEventType: WebhookRobokassaCallbackFormat::CALLBACK_TYPE,
            data: [
                'OutSum' => '100.00',
                'InvId' => '123',
                'SignatureValue' => 'signature',
            ],
            rawData: $rawData,
        );

        $result = $mapper->mapPaymentWebhook($payload);

        $this->assertSame(WebhookProcessingStatus::Processed, $result->status);
        $this->assertSame(WebhookEventType::PaymentSucceeded, $result->eventType);
        $this->assertNull($result->reason);
        $this->assertSame($rawData, $result->rawData);
        $this->assertSame([
            'OutSum' => '100.00',
            'InvId' => '123',
            'SignatureValue' => 'signature',
        ], $result->rawData?->queryParams);
        $this->assertSame([], $result->rawData?->bodyParams);
    }

    public function testMapsSupportedRobokassaCallbackToProcessedResultWithoutRawData(): void
    {
        $mapper = new WebhookRobokassaPaymentWebhookMapper();
        $payload = new WebhookPayload(
            providerId: WebhookRobokassaCallbackFormat::PROVIDER_ID,
            eventType: WebhookEventType::PaymentSucceeded,
            providerEventType: WebhookRobokassaCallbackFormat::CALLBACK_TYPE,
            data: [
                'OutSum' => '100.00',
                'InvId' => '123',
                'SignatureValue' => 'signature',
            ],
        );

        $result = $mapper->mapPaymentWebhook($payload);

        $this->assertSame(WebhookProcessingStatus::Processed, $result->status);
        $this->assertSame(WebhookEventType::PaymentSucceeded, $result->eventType);
        $this->assertNull($result->reason);
        $this->assertNull($result->rawData);
    }

    public function testReturnsUnknownEventForPayloadWithoutNormalizedEventTypeAndPreservesRawData(): void
    {
        $mapper = new WebhookRobokassaPaymentWebhookMapper();
        $rawData = new WebhookRawData(
            rawBody: '',
            headers: ['Content-Type' => 'application/x-www-form-urlencoded'],
            payload: ['OutSum' => '100.00'],
            providerEventType: 'unsupported_callback',
            bodyParams: ['OutSum' => '100.00'],
        );
        $payload = new WebhookPayload(
            providerId: WebhookRobokassaCallbackFormat::PROVIDER_ID,
            eventType: null,
            providerEventType: 'unsupported_callback',
            data: ['OutSum' => '100.00'],
            rawData: $rawData,
        );

        $result = $mapper->mapPaymentWebhook($payload);

        $this->assertSame(WebhookProcessingStatus::UnknownEvent, $result->status);
        $this->assertNull($result->eventType);
        $this->assertNotNull($result->reason);
        $this->assertSame('unknown_event_type', $result->reason->code->value);
        $this->assertSame('unsupported_callback', $result->reason->providerEventType);
        $this->assertSame($rawData, $result->rawData);
    }

    public function testMapsUnsupportedRobokassaCallbackToUnsupportedEvent(): void
    {
        $mapper = new WebhookRobokassaPaymentWebhookMapper();
        $rawData = new WebhookRawData(
            rawBody: '',
            headers: ['Content-Type' => 'application/x-www-form-urlencoded'],
            payload: [
                'OutSum' => '100.00',
                'InvId' => '123',
                'SignatureValue' => 'signature',
            ],
            providerEventType: WebhookRobokassaCallbackFormat::CALLBACK_TYPE,
        );
        $payload = new WebhookPayload(
            providerId: WebhookRobokassaCallbackFormat::PROVIDER_ID,
            eventType: WebhookEventType::PaymentRefunded,
            providerEventType: WebhookRobokassaCallbackFormat::CALLBACK_TYPE,
            data: [
                'OutSum' => '100.00',
                'InvId' => '123',
                'SignatureValue' => 'signature',
            ],
            rawData: $rawData,
        );

        $result = $mapper->mapPaymentWebhook($payload);

        $this->assertSame(WebhookProcessingStatus::UnsupportedEvent, $result->status);
        $this->assertSame(WebhookEventType::PaymentRefunded, $result->eventType);
        $this->assertNotNull($result->reason);
        $this->assertSame('unsupported_event_type', $result->reason->code->value);
        $this->assertSame(
            'Webhook event type is recognized but is not supported by the current webhook contract.',
            $result->reason->message,
        );
        $this->assertSame(WebhookRobokassaCallbackFormat::CALLBACK_TYPE, $result->reason->providerEventType);
        $this->assertSame($rawData, $result->rawData);
    }

    public function testMapsUnsupportedRobokassaCallbackWithoutRawDataToUnsupportedEvent(): void
    {
        $mapper = new WebhookRobokassaPaymentWebhookMapper();
        $payload = new WebhookPayload(
            providerId: WebhookRobokassaCallbackFormat::PROVIDER_ID,
            eventType: WebhookEventType::PaymentRefunded,
            providerEventType: WebhookRobokassaCallbackFormat::CALLBACK_TYPE,
            data: [
                'OutSum' => '100.00',
                'InvId' => '123',
                'SignatureValue' => 'signature',
            ],
        );

        $result = $mapper->mapPaymentWebhook($payload);

        $this->assertSame(WebhookProcessingStatus::UnsupportedEvent, $result->status);
        $this->assertSame(WebhookEventType::PaymentRefunded, $result->eventType);
        $this->assertNotNull($result->reason);
        $this->assertSame('unsupported_event_type', $result->reason->code->value);
        $this->assertSame(WebhookRobokassaCallbackFormat::CALLBACK_TYPE, $result->reason->providerEventType);
        $this->assertNull($result->rawData);
    }

    #[DataProvider('unsupportedR1PaymentOutcomeProvider')]
    public function testReturnsUnsupportedForNonSuccessR1PaymentOutcomes(WebhookEventType $eventType): void
    {
        $mapper = new WebhookRobokassaPaymentWebhookMapper();
        $payload = new WebhookPayload(
            providerId: WebhookRobokassaCallbackFormat::PROVIDER_ID,
            eventType: $eventType,
            providerEventType: WebhookRobokassaCallbackFormat::CALLBACK_TYPE,
            data: [
                'OutSum' => '100.00',
                'InvId' => '123',
                'SignatureValue' => 'signature',
            ],
        );

        $result = $mapper->mapPaymentWebhook($payload);

        $this->assertSame(WebhookProcessingStatus::UnsupportedEvent, $result->status);
        $this->assertSame($eventType, $result->eventType);
        $this->assertNotNull($result->reason);
        $this->assertSame('unsupported_event_type', $result->reason->code->value);
        $this->assertSame(WebhookRobokassaCallbackFormat::CALLBACK_TYPE, $result->reason->providerEventType);
        $this->assertNull($result->paymentStatus);
    }

    /**
     * @return iterable<string, array{WebhookEventType}>
     */
    public static function unsupportedR1PaymentOutcomeProvider(): iterable
    {
        yield 'created' => [WebhookEventType::PaymentCreated];
        yield 'processing' => [WebhookEventType::PaymentProcessing];
        yield 'requires action' => [WebhookEventType::PaymentRequiresAction];
        yield 'requires capture' => [WebhookEventType::PaymentRequiresCapture];
        yield 'failed' => [WebhookEventType::PaymentFailed];
        yield 'canceled' => [WebhookEventType::PaymentCanceled];
    }

    public function testMapsAmbiguousRobokassaCallbackToUnknownEvent(): void
    {
        $mapper = new WebhookRobokassaPaymentWebhookMapper();
        $payload = new WebhookPayload(
            providerId: WebhookRobokassaCallbackFormat::PROVIDER_ID,
            eventType: null,
            providerEventType: 'ambiguous_callback',
            data: [
                'OutSum' => '100.00',
                'InvId' => '123',
                'SignatureValue' => 'signature',
            ],
        );

        $result = $mapper->mapPaymentWebhook($payload);

        $this->assertSame(WebhookProcessingStatus::UnknownEvent, $result->status);
        $this->assertNull($result->eventType);
        $this->assertNotNull($result->reason);
        $this->assertSame('unknown_event_type', $result->reason->code->value);
        $this->assertSame(
            'Provider event type is not recognized by the webhook event mapping.',
            $result->reason->message,
        );
        $this->assertSame('ambiguous_callback', $result->reason->providerEventType);
        $this->assertNull($result->rawData);
    }

    public function testMapsAmbiguousRobokassaCallbackWithoutProviderEventTypeToUnknownEventAndPreservesRawData(): void
    {
        $mapper = new WebhookRobokassaPaymentWebhookMapper();
        $rawData = new WebhookRawData(
            rawBody: '',
            headers: ['Content-Type' => 'application/x-www-form-urlencoded'],
            payload: [
                'OutSum' => '100.00',
                'InvId' => '123',
                'SignatureValue' => 'signature',
            ],
            providerEventType: null,
            bodyParams: [
                'OutSum' => '100.00',
                'InvId' => '123',
                'SignatureValue' => 'signature',
            ],
        );
        $payload = new WebhookPayload(
            providerId: WebhookRobokassaCallbackFormat::PROVIDER_ID,
            eventType: null,
            providerEventType: null,
            data: [
                'OutSum' => '100.00',
                'InvId' => '123',
                'SignatureValue' => 'signature',
            ],
            rawData: $rawData,
        );

        $result = $mapper->mapPaymentWebhook($payload);

        $this->assertSame(WebhookProcessingStatus::UnknownEvent, $result->status);
        $this->assertNull($result->eventType);
        $this->assertNotNull($result->reason);
        $this->assertSame('unknown_event_type', $result->reason->code->value);
        $this->assertSame('', $result->reason->providerEventType);
        $this->assertSame($rawData, $result->rawData);
    }

    public function testDefinesSupportedRobokassaPaymentStatusSignalForR1(): void
    {
        $this->assertSame(
            WebhookRobokassaCallbackFormat::CALLBACK_TYPE,
            WebhookRobokassaCallbackFormat::PAYMENT_SUCCEEDED_STATUS_SIGNAL,
        );
        $this->assertSame('result_url', WebhookRobokassaCallbackFormat::PAYMENT_SUCCEEDED_STATUS_SIGNAL);
    }

    public function testSupportedRobokassaStatusSignalIsNormalizedAsPaymentSucceededEvent(): void
    {
        $payload = new WebhookPayload(
            providerId: WebhookRobokassaCallbackFormat::PROVIDER_ID,
            eventType: WebhookEventType::PaymentSucceeded,
            providerEventType: WebhookRobokassaCallbackFormat::PAYMENT_SUCCEEDED_STATUS_SIGNAL,
            data: [
                'OutSum' => '100.00',
                'InvId' => '123',
                'SignatureValue' => 'signature',
            ],
        );

        $this->assertSame(WebhookEventType::PaymentSucceeded, $payload->eventType);
        $this->assertSame(WebhookRobokassaCallbackFormat::PAYMENT_SUCCEEDED_STATUS_SIGNAL, $payload->providerEventType);
        $this->assertNull($payload->paymentStatus);
    }

    public function testExtractsRobokassaPaymentStatusFromSupportedResultUrlSignal(): void
    {
        $mapper = new WebhookRobokassaPaymentWebhookMapper();
        $payload = new WebhookPayload(
            providerId: WebhookRobokassaCallbackFormat::PROVIDER_ID,
            eventType: WebhookEventType::PaymentSucceeded,
            providerEventType: WebhookRobokassaCallbackFormat::PAYMENT_SUCCEEDED_STATUS_SIGNAL,
            data: ['OutSum' => '100.00'],
        );

        $this->assertSame(
            WebhookRobokassaCallbackFormat::PAYMENT_SUCCEEDED_STATUS_SIGNAL,
            $mapper->extractPaymentStatus($payload),
        );
    }

    public function testExplicitRobokassaPayloadPaymentStatusHasPriority(): void
    {
        $mapper = new WebhookRobokassaPaymentWebhookMapper();
        $payload = new WebhookPayload(
            providerId: WebhookRobokassaCallbackFormat::PROVIDER_ID,
            eventType: WebhookEventType::PaymentSucceeded,
            providerEventType: WebhookRobokassaCallbackFormat::PAYMENT_SUCCEEDED_STATUS_SIGNAL,
            data: ['OutSum' => '100.00'],
            paymentStatus: 'paid',
        );

        $this->assertSame('paid', $mapper->extractPaymentStatus($payload));
    }

    public function testReturnsNullForRobokassaPayloadWithoutSupportedStatusSignal(): void
    {
        $mapper = new WebhookRobokassaPaymentWebhookMapper();
        $payload = new WebhookPayload(
            providerId: WebhookRobokassaCallbackFormat::PROVIDER_ID,
            eventType: null,
            providerEventType: 'unsupported_callback',
            data: ['OutSum' => '100.00'],
        );

        $this->assertNull($mapper->extractPaymentStatus($payload));
    }

    public function testReturnsNullForRobokassaMissingStatusSignal(): void
    {
        $mapper = new WebhookRobokassaPaymentWebhookMapper();
        $payload = new WebhookPayload(
            providerId: WebhookRobokassaCallbackFormat::PROVIDER_ID,
            eventType: WebhookEventType::PaymentSucceeded,
            providerEventType: null,
            data: ['OutSum' => '100.00'],
        );

        $this->assertNull($mapper->extractPaymentStatus($payload));
    }

    public function testReturnsNullForRobokassaAmbiguousStatusSignal(): void
    {
        $mapper = new WebhookRobokassaPaymentWebhookMapper();
        $payload = new WebhookPayload(
            providerId: WebhookRobokassaCallbackFormat::PROVIDER_ID,
            eventType: null,
            providerEventType: 'ambiguous_callback',
            data: ['OutSum' => '100.00'],
        );

        $this->assertNull($mapper->extractPaymentStatus($payload));
    }

    public function testReturnsNullForRobokassaUnsupportedStatusSignal(): void
    {
        $mapper = new WebhookRobokassaPaymentWebhookMapper();
        $payload = new WebhookPayload(
            providerId: WebhookRobokassaCallbackFormat::PROVIDER_ID,
            eventType: WebhookEventType::PaymentRefunded,
            providerEventType: WebhookRobokassaCallbackFormat::PAYMENT_SUCCEEDED_STATUS_SIGNAL,
            data: ['OutSum' => '100.00'],
        );

        $this->assertNull($mapper->extractPaymentStatus($payload));
    }
}
