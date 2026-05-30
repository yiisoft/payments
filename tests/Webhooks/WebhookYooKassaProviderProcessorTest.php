<?php

declare(strict_types=1);

namespace Yiisoft\Payments\Tests\Webhooks;

use PHPUnit\Framework\TestCase;
use Yiisoft\Payments\Webhooks\WebhookEventType;
use Yiisoft\Payments\Webhooks\WebhookInput;
use Yiisoft\Payments\Webhooks\WebhookProcessingStatus;
use Yiisoft\Payments\Webhooks\WebhookProviderProcessorInterface;
use Yiisoft\Payments\Webhooks\WebhookYooKassaProviderProcessor;

final class WebhookYooKassaProviderProcessorTest extends TestCase
{
    public function testImplementsProviderProcessorInterface(): void
    {
        $processor = new WebhookYooKassaProviderProcessor();

        $this->assertInstanceOf(WebhookProviderProcessorInterface::class, $processor);
    }

    public function testReturnsYooKassaProviderId(): void
    {
        $processor = new WebhookYooKassaProviderProcessor();

        $this->assertSame('yookassa', $processor->getProviderId());
    }

    public function testProcessesSuccessfulYooKassaPaymentWebhook(): void
    {
        $processor = new WebhookYooKassaProviderProcessor();
        $input = new WebhookInput(
            rawBody: '{"type":"notification","event":"payment.succeeded","object":{"id":"payment-123","status":"succeeded"}}',
            headers: ['Content-Type' => 'application/json'],
            providerId: 'yookassa',
        );

        $result = $processor->process($input);

        $this->assertSame(WebhookProcessingStatus::Processed, $result->status);
        $this->assertSame(WebhookEventType::PaymentSucceeded, $result->eventType);
        $this->assertNull($result->reason);
        $this->assertNotNull($result->rawData);
        $this->assertSame($input->rawBody, $result->rawData->rawBody);
        $this->assertSame($input->headers, $result->rawData->headers);
        $this->assertSame('payment.succeeded', $result->rawData->providerEventType);
        $this->assertSame('succeeded', $result->rawData->payload['object']['status']);
    }

    public function testProcessesYooKassaWaitingForCapturePaymentWebhook(): void
    {
        $processor = new WebhookYooKassaProviderProcessor();
        $input = new WebhookInput(
            rawBody: '{"type":"notification","event":"payment.waiting_for_capture","object":{"status":"waiting_for_capture"}}',
            headers: ['Content-Type' => 'application/json'],
            providerId: 'yookassa',
        );

        $result = $processor->process($input);

        $this->assertSame(WebhookProcessingStatus::Processed, $result->status);
        $this->assertSame(WebhookEventType::PaymentRequiresCapture, $result->eventType);
        $this->assertNull($result->reason);
        $this->assertNotNull($result->rawData);
        $this->assertSame('payment.waiting_for_capture', $result->rawData->providerEventType);
    }

    public function testReturnsUnknownEventForUnknownYooKassaProviderEventType(): void
    {
        $processor = new WebhookYooKassaProviderProcessor();
        $input = new WebhookInput(
            rawBody: '{"type":"notification","event":"payment.future_event","object":{"status":"unknown"}}',
            headers: ['Content-Type' => 'application/json'],
            providerId: 'yookassa',
        );

        $result = $processor->process($input);

        $this->assertSame(WebhookProcessingStatus::UnknownEvent, $result->status);
        $this->assertNull($result->eventType);
        $this->assertNotNull($result->reason);
        $this->assertSame('unknown_event_type', $result->reason->code->value);
        $this->assertSame('payment.future_event', $result->reason->providerEventType);
    }

    public function testReturnsUnknownEventWhenProviderEventTypeCannotBeRecognized(): void
    {
        $processor = new WebhookYooKassaProviderProcessor();
        $input = new WebhookInput(
            rawBody: '{"type":"notification","object":{"status":"unknown"}}',
            headers: ['Content-Type' => 'application/json'],
            providerId: 'yookassa',
        );

        $result = $processor->process($input);

        $this->assertSame(WebhookProcessingStatus::UnknownEvent, $result->status);
        $this->assertNull($result->eventType);
        $this->assertNotNull($result->reason);
        $this->assertSame('unknown_event_type', $result->reason->code->value);
        $this->assertSame('', $result->reason->providerEventType);
    }
}
