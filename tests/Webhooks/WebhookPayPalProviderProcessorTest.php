<?php

declare(strict_types=1);

namespace Yiisoft\Payments\Tests\Webhooks;

use PHPUnit\Framework\TestCase;
use Yiisoft\Payments\Webhooks\WebhookEventType;
use Yiisoft\Payments\Webhooks\WebhookInput;
use Yiisoft\Payments\Webhooks\WebhookPayPalProviderProcessor;
use Yiisoft\Payments\Webhooks\WebhookProcessingStatus;
use Yiisoft\Payments\Webhooks\WebhookProviderProcessorInterface;

final class WebhookPayPalProviderProcessorTest extends TestCase
{
    public function testImplementsProviderProcessorInterface(): void
    {
        $processor = new WebhookPayPalProviderProcessor();

        $this->assertInstanceOf(WebhookProviderProcessorInterface::class, $processor);
    }

    public function testReturnsPayPalProviderId(): void
    {
        $processor = new WebhookPayPalProviderProcessor();

        $this->assertSame('paypal', $processor->getProviderId());
    }

    public function testProcessesSuccessfulPayPalPaymentWebhook(): void
    {
        $processor = new WebhookPayPalProviderProcessor();
        $input = new WebhookInput(
            rawBody: '{"id":"WH-123","event_type":"PAYMENT.CAPTURE.COMPLETED","resource":{"id":"CAPTURE-123","status":"COMPLETED"}}',
            headers: ['PayPal-Transmission-Id' => 'transmission-123'],
            providerId: 'paypal',
        );

        $result = $processor->process($input);

        $this->assertSame(WebhookProcessingStatus::Processed, $result->status);
        $this->assertSame(WebhookEventType::PaymentSucceeded, $result->eventType);
        $this->assertNull($result->reason);
        $this->assertNotNull($result->rawData);
        $this->assertSame($input->rawBody, $result->rawData->rawBody);
        $this->assertSame($input->headers, $result->rawData->headers);
        $this->assertSame('PAYMENT.CAPTURE.COMPLETED', $result->rawData->providerEventType);
        $this->assertSame('COMPLETED', $result->rawData->payload['resource']['status']);
    }

    public function testProcessesPendingPayPalPaymentWebhook(): void
    {
        $processor = new WebhookPayPalProviderProcessor();
        $input = new WebhookInput(
            rawBody: '{"id":"WH-123","event_type":"PAYMENT.CAPTURE.PENDING","resource":{"status":"PENDING"}}',
            headers: ['PayPal-Transmission-Id' => 'transmission-123'],
            providerId: 'paypal',
        );

        $result = $processor->process($input);

        $this->assertSame(WebhookProcessingStatus::Processed, $result->status);
        $this->assertSame(WebhookEventType::PaymentProcessing, $result->eventType);
        $this->assertNull($result->reason);
        $this->assertNotNull($result->rawData);
        $this->assertSame('PAYMENT.CAPTURE.PENDING', $result->rawData->providerEventType);
        $this->assertSame('PENDING', $result->paymentStatus);
    }

    public function testReturnsUnknownEventForUnknownPayPalProviderEventType(): void
    {
        $processor = new WebhookPayPalProviderProcessor();
        $input = new WebhookInput(
            rawBody: '{"id":"WH-123","event_type":"PAYMENT.CAPTURE.FUTURE_EVENT","resource":{"status":"UNKNOWN"}}',
            headers: ['PayPal-Transmission-Id' => 'transmission-123'],
            providerId: 'paypal',
        );

        $result = $processor->process($input);

        $this->assertSame(WebhookProcessingStatus::UnknownEvent, $result->status);
        $this->assertNull($result->eventType);
        $this->assertNotNull($result->reason);
        $this->assertSame('unknown_event_type', $result->reason->code->value);
        $this->assertSame('PAYMENT.CAPTURE.FUTURE_EVENT', $result->reason->providerEventType);
    }

    public function testReturnsUnknownEventWhenProviderEventTypeCannotBeRecognized(): void
    {
        $processor = new WebhookPayPalProviderProcessor();
        $input = new WebhookInput(
            rawBody: '{"id":"WH-123","resource":{"status":"UNKNOWN"}}',
            headers: ['PayPal-Transmission-Id' => 'transmission-123'],
            providerId: 'paypal',
        );

        $result = $processor->process($input);

        $this->assertSame(WebhookProcessingStatus::UnknownEvent, $result->status);
        $this->assertNull($result->eventType);
        $this->assertNotNull($result->reason);
        $this->assertSame('unknown_event_type', $result->reason->code->value);
        $this->assertNull($result->reason->providerEventType);
    }
}
