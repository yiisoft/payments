<?php

declare(strict_types=1);

namespace Yiisoft\Payments\Tests\Webhooks;

use PHPUnit\Framework\TestCase;
use Yiisoft\Payments\Webhooks\WebhookEventType;
use Yiisoft\Payments\Webhooks\WebhookInput;
use Yiisoft\Payments\Webhooks\WebhookProcessingStatus;
use Yiisoft\Payments\Webhooks\WebhookProcessor;
use Yiisoft\Payments\Webhooks\WebhookProviderProcessorRegistry;
use Yiisoft\Payments\Webhooks\WebhookProviderValidatorRegistry;
use Yiisoft\Payments\Webhooks\WebhookYooKassaProviderProcessor;
use Yiisoft\Payments\Webhooks\WebhookYooKassaValidator;

final class WebhookYooKassaR1PipelineReadinessTest extends TestCase
{
    public function testYooKassaR1PaymentWebhookPipelineIsReady(): void
    {
        $shopId = '123456';
        $secretKey = 'yookassa_r1_ready_secret';
        $rawBody = '{"type":"notification","event":"payment.succeeded","object":{"id":"payment-r1-ready","status":"succeeded","amount":{"value":"10.00","currency":"RUB"}}}';
        $input = new WebhookInput(
            rawBody: $rawBody,
            headers: [
                'Authorization' => 'Basic ' . base64_encode($shopId . ':' . $secretKey),
                'Content-Type' => 'application/json',
            ],
            queryParams: [
                'endpoint' => 'payments',
            ],
            bodyParams: [
                'ignored' => 'json-body-is-authoritative',
            ],
            providerId: 'yookassa',
        );
        $processor = new WebhookProcessor(
            new WebhookProviderProcessorRegistry(new WebhookYooKassaProviderProcessor()),
            new WebhookProviderValidatorRegistry(new WebhookYooKassaValidator(
                shopId: $shopId,
                secretKey: $secretKey,
            )),
        );

        $context = $processor->process($input);

        $this->assertSame('yookassa', $context->providerId);
        $this->assertSame(WebhookProcessingStatus::Processed, $context->status);
        $this->assertSame(WebhookEventType::PaymentSucceeded, $context->eventType);
        $this->assertSame('succeeded', $context->paymentStatus);
        $this->assertNull($context->validationFailureReason);
        $this->assertNull($context->unsupportedEventReason);
        $this->assertNull($context->unknownEventReason);
        $this->assertSame($input, $context->rawInput);
        $this->assertNotNull($context->rawData);
        $this->assertSame($rawBody, $context->rawData->rawBody);
        $this->assertSame($input->headers, $context->rawData->headers);
        $this->assertSame($input->queryParams, $context->rawData->queryParams);
        $this->assertSame($input->bodyParams, $context->rawData->bodyParams);
        $this->assertSame('payment.succeeded', $context->rawData->providerEventType);
        $this->assertSame('notification', $context->rawData->payload['type'] ?? null);
        $this->assertSame('payment.succeeded', $context->rawData->payload['event'] ?? null);
        $this->assertSame('payment-r1-ready', $context->rawData->payload['object']['id'] ?? null);
        $this->assertSame('succeeded', $context->rawData->payload['object']['status'] ?? null);
    }
}
