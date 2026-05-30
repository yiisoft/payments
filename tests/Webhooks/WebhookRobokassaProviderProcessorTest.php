<?php

declare(strict_types=1);

namespace Yiisoft\Payments\Tests\Webhooks;

use PHPUnit\Framework\TestCase;
use Yiisoft\Payments\Webhooks\WebhookEventType;
use Yiisoft\Payments\Webhooks\WebhookInput;
use Yiisoft\Payments\Webhooks\WebhookProcessingStatus;
use Yiisoft\Payments\Webhooks\WebhookProviderProcessorInterface;
use Yiisoft\Payments\Webhooks\WebhookRobokassaCallbackFormat;
use Yiisoft\Payments\Webhooks\WebhookRobokassaProviderProcessor;

final class WebhookRobokassaProviderProcessorTest extends TestCase
{
    public function testImplementsProviderProcessorInterface(): void
    {
        $processor = new WebhookRobokassaProviderProcessor();

        $this->assertInstanceOf(WebhookProviderProcessorInterface::class, $processor);
    }

    public function testReturnsRobokassaProviderId(): void
    {
        $processor = new WebhookRobokassaProviderProcessor();

        $this->assertSame(WebhookRobokassaCallbackFormat::PROVIDER_ID, $processor->getProviderId());
    }

    public function testProcessesSuccessfulRobokassaQueryCallback(): void
    {
        $processor = new WebhookRobokassaProviderProcessor();
        $input = new WebhookInput(
            rawBody: '',
            headers: ['Content-Type' => 'application/x-www-form-urlencoded'],
            queryParams: [
                'OutSum' => '100.00',
                'InvId' => '123',
                'SignatureValue' => 'signature',
                'Shp_orderId' => 'order-123',
            ],
            providerId: WebhookRobokassaCallbackFormat::PROVIDER_ID,
        );

        $result = $processor->process($input);

        $this->assertSame(WebhookProcessingStatus::Processed, $result->status);
        $this->assertSame(WebhookEventType::PaymentSucceeded, $result->eventType);
        $this->assertNull($result->reason);
        $this->assertNotNull($result->rawData);
        $this->assertSame('', $result->rawData->rawBody);
        $this->assertSame($input->headers, $result->rawData->headers);
        $this->assertSame(WebhookRobokassaCallbackFormat::CALLBACK_TYPE, $result->rawData->providerEventType);
        $this->assertSame($input->queryParams, $result->rawData->queryParams);
        $this->assertSame([], $result->rawData->bodyParams);
        $this->assertSame($input->queryParams, $result->rawData->payload);
    }

    public function testProcessesSuccessfulRobokassaBodyCallback(): void
    {
        $processor = new WebhookRobokassaProviderProcessor();
        $input = new WebhookInput(
            rawBody: 'OutSum=100.00&InvId=123&SignatureValue=signature',
            headers: ['Content-Type' => 'application/x-www-form-urlencoded'],
            bodyParams: [
                'OutSum' => '100.00',
                'InvId' => '123',
                'SignatureValue' => 'signature',
            ],
            providerId: WebhookRobokassaCallbackFormat::PROVIDER_ID,
        );

        $result = $processor->process($input);

        $this->assertSame(WebhookProcessingStatus::Processed, $result->status);
        $this->assertSame(WebhookEventType::PaymentSucceeded, $result->eventType);
        $this->assertNull($result->reason);
        $this->assertNotNull($result->rawData);
        $this->assertSame($input->rawBody, $result->rawData->rawBody);
        $this->assertSame([], $result->rawData->queryParams);
        $this->assertSame($input->bodyParams, $result->rawData->bodyParams);
        $this->assertSame($input->bodyParams, $result->rawData->payload);
    }

    public function testReturnsUnknownEventForMissingRequiredRobokassaCallbackField(): void
    {
        $processor = new WebhookRobokassaProviderProcessor();
        $input = new WebhookInput(
            rawBody: '',
            queryParams: [
                'OutSum' => '100.00',
                'SignatureValue' => 'signature',
            ],
            providerId: WebhookRobokassaCallbackFormat::PROVIDER_ID,
        );

        $result = $processor->process($input);

        $this->assertSame(WebhookProcessingStatus::UnknownEvent, $result->status);
        $this->assertNull($result->eventType);
        $this->assertNotNull($result->reason);
        $this->assertSame('unknown_event_type', $result->reason->code->value);
        $this->assertSame('', $result->reason->providerEventType);
        $this->assertNotNull($result->rawData);
        $this->assertSame($input->queryParams, $result->rawData->queryParams);
    }

    public function testReturnsUnknownEventForConflictingRobokassaCallbackVariants(): void
    {
        $processor = new WebhookRobokassaProviderProcessor();
        $input = new WebhookInput(
            rawBody: 'OutSum=200.00&InvId=123&SignatureValue=signature',
            queryParams: [
                'OutSum' => '100.00',
                'InvId' => '123',
                'SignatureValue' => 'signature',
            ],
            bodyParams: [
                'OutSum' => '200.00',
                'InvId' => '123',
                'SignatureValue' => 'signature',
            ],
            providerId: WebhookRobokassaCallbackFormat::PROVIDER_ID,
        );

        $result = $processor->process($input);

        $this->assertSame(WebhookProcessingStatus::UnknownEvent, $result->status);
        $this->assertNull($result->eventType);
        $this->assertNotNull($result->reason);
        $this->assertSame('unknown_event_type', $result->reason->code->value);
        $this->assertSame('', $result->reason->providerEventType);
        $this->assertNotNull($result->rawData);
        $this->assertSame($input->queryParams, $result->rawData->queryParams);
        $this->assertSame($input->bodyParams, $result->rawData->bodyParams);
    }
}
