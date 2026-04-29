<?php

declare(strict_types=1);

namespace Yiisoft\Payments\Tests\Webhooks;

use PHPUnit\Framework\TestCase;
use Yiisoft\Payments\Tests\Webhooks\Support\SuccessfulWebhookProviderProcessor;
use Yiisoft\Payments\Webhooks\WebhookEventType;
use Yiisoft\Payments\Webhooks\WebhookInput;
use Yiisoft\Payments\Webhooks\WebhookProcessingStatus;
use Yiisoft\Payments\Webhooks\WebhookProcessor;
use Yiisoft\Payments\Webhooks\WebhookProviderProcessorRegistry;

final class WebhookSuccessfulUnifiedProcessingFlowTest extends TestCase
{
    public function testProcessorIsSelectedByInputProviderId(): void
    {
        $stripeProcessor = new SuccessfulWebhookProviderProcessor(
            providerId: 'stripe',
            eventType: WebhookEventType::PaymentSucceeded,
            providerEventType: 'payment_intent.succeeded',
            payload: ['type' => 'payment_intent.succeeded'],
        );
        $paypalProcessor = new SuccessfulWebhookProviderProcessor(
            providerId: 'paypal',
            eventType: WebhookEventType::PaymentRefunded,
            providerEventType: 'PAYMENT.CAPTURE.REFUNDED',
            payload: ['event_type' => 'PAYMENT.CAPTURE.REFUNDED'],
        );
        $robokassaProcessor = new SuccessfulWebhookProviderProcessor(
            providerId: 'robokassa',
            eventType: WebhookEventType::PaymentFailed,
            providerEventType: 'ResultURL',
            payload: ['InvId' => '100500'],
        );
        $processor = new WebhookProcessor(new WebhookProviderProcessorRegistry(
            $stripeProcessor,
            $paypalProcessor,
            $robokassaProcessor,
        ));
        $input = new WebhookInput(
            rawBody: '{"event_type":"PAYMENT.CAPTURE.REFUNDED"}',
            headers: ['PayPal-Transmission-Id' => 'transmission-id'],
            providerId: 'paypal',
        );

        $result = $processor->process($input);

        $this->assertSame(WebhookProcessingStatus::Processed, $result->status);
        $this->assertSame(WebhookEventType::PaymentRefunded, $result->eventType);
        $this->assertNotNull($result->rawData);
        $this->assertSame('{"event_type":"PAYMENT.CAPTURE.REFUNDED"}', $result->rawData->rawBody);
        $this->assertSame(['PayPal-Transmission-Id' => 'transmission-id'], $result->rawData->headers);
        $this->assertSame(['event_type' => 'PAYMENT.CAPTURE.REFUNDED'], $result->rawData->payload);
        $this->assertSame('PAYMENT.CAPTURE.REFUNDED', $result->rawData->providerEventType);

        $this->assertSame(0, $stripeProcessor->processCalls);
        $this->assertNull($stripeProcessor->processedInput);
        $this->assertSame(1, $paypalProcessor->processCalls);
        $this->assertSame($input, $paypalProcessor->processedInput);
        $this->assertSame(0, $robokassaProcessor->processCalls);
        $this->assertNull($robokassaProcessor->processedInput);
    }
}
