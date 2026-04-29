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

    public function testWebhookInputIsPassedToSelectedProcessorWithoutRawDataLoss(): void
    {
        $selectedProcessor = new SuccessfulWebhookProviderProcessor(providerId: 'stripe');
        $processor = new WebhookProcessor(new WebhookProviderProcessorRegistry($selectedProcessor));
        $input = new WebhookInput(
            rawBody: '{"id":"evt_123","data":{"object":{"id":"pi_123"}}}',
            headers: [
                'Stripe-Signature' => 't=1700000000,v1=signature',
                'X-Forwarded-For' => ['203.0.113.10', '198.51.100.20'],
            ],
            queryParams: [
                'endpoint' => 'payments',
                'debug' => '1',
            ],
            bodyParams: [
                'id' => 'evt_123',
                'data' => [
                    'object' => [
                        'id' => 'pi_123',
                    ],
                ],
            ],
            providerId: 'stripe',
        );

        $result = $processor->process($input);

        $this->assertSame(WebhookProcessingStatus::Processed, $result->status);
        $this->assertSame(1, $selectedProcessor->processCalls);
        $this->assertSame($input, $selectedProcessor->processedInput);
        $this->assertNotNull($selectedProcessor->processedInput);
        $this->assertSame($input->rawBody, $selectedProcessor->processedInput->rawBody);
        $this->assertSame($input->headers, $selectedProcessor->processedInput->headers);
        $this->assertSame($input->queryParams, $selectedProcessor->processedInput->queryParams);
        $this->assertSame($input->bodyParams, $selectedProcessor->processedInput->bodyParams);
        $this->assertSame($input->providerId, $selectedProcessor->processedInput->providerId);
        $this->assertNotNull($result->rawData);
        $this->assertSame($input->rawBody, $result->rawData->rawBody);
        $this->assertSame($input->headers, $result->rawData->headers);
    }
}
