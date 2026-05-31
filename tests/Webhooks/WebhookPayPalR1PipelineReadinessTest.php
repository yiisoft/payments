<?php

declare(strict_types=1);

namespace Yiisoft\Payments\Tests\Webhooks;

use PHPUnit\Framework\TestCase;
use Yiisoft\Payments\Webhooks\WebhookEventType;
use Yiisoft\Payments\Webhooks\WebhookInput;
use Yiisoft\Payments\Webhooks\WebhookPayPalProviderProcessor;
use Yiisoft\Payments\Webhooks\WebhookPayPalSignatureVerifierInterface;
use Yiisoft\Payments\Webhooks\WebhookPayPalValidator;
use Yiisoft\Payments\Webhooks\WebhookProcessingStatus;
use Yiisoft\Payments\Webhooks\WebhookProcessor;
use Yiisoft\Payments\Webhooks\WebhookProviderProcessorRegistry;
use Yiisoft\Payments\Webhooks\WebhookProviderValidatorRegistry;
use Yiisoft\Payments\Webhooks\WebhookValidationResult;

final class WebhookPayPalR1PipelineReadinessTest extends TestCase
{
    public function testPayPalR1PaymentWebhookPipelineIsReady(): void
    {
        $rawBody = '{"id":"WH-EVENT-PAYPAL-R1-READY","event_type":"PAYMENT.CAPTURE.COMPLETED","resource":{"id":"CAPTURE-R1-READY","status":"COMPLETED","amount":{"currency_code":"USD","value":"10.00"}}}';
        $headers = [
            'PayPal-Transmission-Id' => 'transmission-r1-ready',
            'PayPal-Transmission-Time' => '2026-04-29T10:00:00Z',
            'PayPal-Cert-Url' => 'https://api-m.paypal.com/certs/r1-ready.pem',
            'PayPal-Auth-Algo' => 'SHA256withRSA',
            'PayPal-Transmission-Sig' => 'signature-r1-ready',
            'Content-Type' => 'application/json',
        ];
        $input = new WebhookInput(
            rawBody: $rawBody,
            headers: $headers,
            queryParams: [
                'endpoint' => 'payments',
            ],
            bodyParams: [
                'ignored' => 'json-body-is-authoritative',
            ],
            providerId: 'paypal',
        );
        $signatureVerifier = new class implements WebhookPayPalSignatureVerifierInterface {
            public ?WebhookInput $input = null;
            public ?string $webhookId = null;

            public function verify(WebhookInput $input, string $webhookId): WebhookValidationResult
            {
                $this->input = $input;
                $this->webhookId = $webhookId;

                return WebhookValidationResult::success();
            }
        };
        $processor = new WebhookProcessor(
            new WebhookProviderProcessorRegistry(new WebhookPayPalProviderProcessor()),
            new WebhookProviderValidatorRegistry(new WebhookPayPalValidator(
                signatureVerifier: $signatureVerifier,
                webhookId: 'WH-CONFIGURED-R1-READY',
            )),
        );

        $context = $processor->process($input);

        $this->assertSame($input, $signatureVerifier->input);
        $this->assertSame('WH-CONFIGURED-R1-READY', $signatureVerifier->webhookId);
        $this->assertSame('paypal', $context->providerId);
        $this->assertSame(WebhookProcessingStatus::Processed, $context->status);
        $this->assertSame(WebhookEventType::PaymentSucceeded, $context->eventType);
        $this->assertSame('COMPLETED', $context->paymentStatus);
        $this->assertNull($context->validationFailureReason);
        $this->assertNull($context->unsupportedEventReason);
        $this->assertNull($context->unknownEventReason);
        $this->assertSame($input, $context->rawInput);
        $this->assertNotNull($context->rawData);
        $this->assertSame($rawBody, $context->rawData->rawBody);
        $this->assertSame($input->headers, $context->rawData->headers);
        $this->assertSame($input->queryParams, $context->rawData->queryParams);
        $this->assertSame($input->bodyParams, $context->rawData->bodyParams);
        $this->assertSame('PAYMENT.CAPTURE.COMPLETED', $context->rawData->providerEventType);
        $this->assertSame('WH-EVENT-PAYPAL-R1-READY', $context->rawData->payload['id'] ?? null);
        $this->assertSame('PAYMENT.CAPTURE.COMPLETED', $context->rawData->payload['event_type'] ?? null);
        $this->assertSame('COMPLETED', $context->rawData->payload['resource']['status'] ?? null);
    }
}
