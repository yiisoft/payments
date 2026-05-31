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
use Yiisoft\Payments\Webhooks\WebhookStripeProviderProcessor;
use Yiisoft\Payments\Webhooks\WebhookStripeValidator;

final class WebhookStripeR1PipelineReadinessTest extends TestCase
{
    public function testStripeR1PaymentWebhookPipelineIsReady(): void
    {
        $signingSecret = 'whsec_r1_stripe_test_secret';
        $timestamp = '1700000000';
        $rawBody = '{"id":"evt_stripe_r1_ready","type":"payment_intent.succeeded","data":{"object":{"id":"pi_r1_ready","status":"succeeded","amount":1000,"currency":"usd"}}}';
        $signature = hash_hmac('sha256', $timestamp . '.' . $rawBody, $signingSecret);
        $input = new WebhookInput(
            rawBody: $rawBody,
            headers: [
                'Stripe-Signature' => 't=' . $timestamp . ',v1=' . $signature,
                'Content-Type' => 'application/json',
            ],
            queryParams: [
                'endpoint' => 'payments',
            ],
            bodyParams: [
                'ignored' => 'json-body-is-authoritative',
            ],
            providerId: 'stripe',
        );
        $processor = new WebhookProcessor(
            new WebhookProviderProcessorRegistry(new WebhookStripeProviderProcessor()),
            new WebhookProviderValidatorRegistry(new WebhookStripeValidator(
                signingSecret: $signingSecret,
                currentTimestamp: (int) $timestamp,
            )),
        );

        $context = $processor->process($input);

        $this->assertSame('stripe', $context->providerId);
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
        $this->assertSame('payment_intent.succeeded', $context->rawData->providerEventType);
        $this->assertSame('evt_stripe_r1_ready', $context->rawData->payload['id'] ?? null);
        $this->assertSame('payment_intent.succeeded', $context->rawData->payload['type'] ?? null);
        $this->assertSame(
            'succeeded',
            $context->rawData->payload['data']['object']['status'] ?? null,
        );
    }
}
