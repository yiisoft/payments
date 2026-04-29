<?php

declare(strict_types=1);

namespace Yiisoft\Payments\Tests\Webhooks;

use LogicException;
use PHPUnit\Framework\TestCase;
use Yiisoft\Payments\Tests\Webhooks\Support\FailedWebhookProviderValidator;
use Yiisoft\Payments\Webhooks\WebhookInput;
use Yiisoft\Payments\Webhooks\WebhookProcessingResult;
use Yiisoft\Payments\Webhooks\WebhookProcessingStatus;
use Yiisoft\Payments\Webhooks\WebhookProcessor;
use Yiisoft\Payments\Webhooks\WebhookProviderProcessorInterface;
use Yiisoft\Payments\Webhooks\WebhookProviderProcessorRegistry;
use Yiisoft\Payments\Webhooks\WebhookProviderValidatorRegistry;

final class WebhookFailFastBehaviorTest extends TestCase
{
    public function testValidationFailureDoesNotStartProviderParsing(): void
    {
        $providerProcessor = new class implements WebhookProviderProcessorInterface {
            public int $processCalls = 0;

            public function getProviderId(): string
            {
                return 'stripe';
            }

            public function process(WebhookInput $input): WebhookProcessingResult
            {
                $this->processCalls++;

                throw new LogicException('Provider parsing must not be started after validation failure.');
            }
        };
        $processor = new WebhookProcessor(
            new WebhookProviderProcessorRegistry($providerProcessor),
            new WebhookProviderValidatorRegistry(new FailedWebhookProviderValidator('stripe')),
        );
        $input = new WebhookInput(
            rawBody: '{"type":"payment_intent.succeeded"}',
            headers: ['Stripe-Signature' => 'invalid-signature'],
            providerId: 'stripe',
        );

        $context = $processor->process($input);

        $this->assertSame(WebhookProcessingStatus::ValidationFailed, $context->status);
        $this->assertNull($context->eventType);
        $this->assertNotNull($context->validationFailureReason);
        $this->assertSame('test_validation_failed', $context->validationFailureReason->code->value);
        $this->assertSame(0, $providerProcessor->processCalls);
        $this->assertNotNull($context->rawData);
        $this->assertNull($context->rawData->payload);
        $this->assertNull($context->rawData->providerEventType);
    }

    public function testValidationFailureReturnsPredictableContextResult(): void
    {
        $providerProcessor = new class implements WebhookProviderProcessorInterface {
            public int $processCalls = 0;

            public function getProviderId(): string
            {
                return 'stripe';
            }

            public function process(WebhookInput $input): WebhookProcessingResult
            {
                $this->processCalls++;

                throw new LogicException('Provider processor must not be called after validation failure.');
            }
        };
        $input = new WebhookInput(
            rawBody: '{"type":"payment_intent.succeeded"}',
            headers: ['Stripe-Signature' => 'invalid-signature'],
            providerId: 'stripe',
            queryParams: ['source' => 'query'],
            bodyParams: ['source' => 'body'],
        );
        $processor = new WebhookProcessor(
            new WebhookProviderProcessorRegistry($providerProcessor),
            new WebhookProviderValidatorRegistry(new FailedWebhookProviderValidator('stripe')),
        );

        $context = $processor->process($input);

        $this->assertSame('stripe', $context->providerId);
        $this->assertSame(WebhookProcessingStatus::ValidationFailed, $context->status);
        $this->assertSame($input, $context->rawInput);
        $this->assertNull($context->eventType);
        $this->assertNull($context->unsupportedEventReason);
        $this->assertNull($context->unknownEventReason);
        $this->assertNotNull($context->validationFailureReason);
        $this->assertSame('test_validation_failed', $context->validationFailureReason->code->value);
        $this->assertSame('Test webhook validation failed.', $context->validationFailureReason->message);
        $this->assertNull($context->validationFailureReason->providerEventType);
        $this->assertNotNull($context->rawData);
        $this->assertSame('{"type":"payment_intent.succeeded"}', $context->rawData->rawBody);
        $this->assertSame(['Stripe-Signature' => 'invalid-signature'], $context->rawData->getHeaders());
        $this->assertSame(['source' => 'query'], $context->rawData->getQueryParams());
        $this->assertSame(['source' => 'body'], $context->rawData->getBodyParams());
        $this->assertNull($context->rawData->getPayload());
        $this->assertNull($context->rawData->getProviderEventType());
        $this->assertSame(0, $providerProcessor->processCalls);
    }

    public function testValidationFailureDoesNotStartProviderEventRecognition(): void
    {
        $providerProcessor = new class implements WebhookProviderProcessorInterface {
            public int $processCalls = 0;
            public int $eventRecognitionCalls = 0;

            public function getProviderId(): string
            {
                return 'stripe';
            }

            public function process(WebhookInput $input): WebhookProcessingResult
            {
                $this->processCalls++;

                return $this->recognizeEvent($input);
            }

            private function recognizeEvent(WebhookInput $input): WebhookProcessingResult
            {
                $this->eventRecognitionCalls++;

                throw new LogicException('Provider event recognition must not be started after validation failure.');
            }
        };
        $processor = new WebhookProcessor(
            new WebhookProviderProcessorRegistry($providerProcessor),
            new WebhookProviderValidatorRegistry(new FailedWebhookProviderValidator('stripe')),
        );
        $input = new WebhookInput(
            rawBody: '{"type":"payment_intent.succeeded"}',
            headers: ['Stripe-Signature' => 'invalid-signature'],
            providerId: 'stripe',
        );

        $context = $processor->process($input);

        $this->assertSame(WebhookProcessingStatus::ValidationFailed, $context->status);
        $this->assertNull($context->eventType);
        $this->assertNotNull($context->validationFailureReason);
        $this->assertSame('test_validation_failed', $context->validationFailureReason->code->value);
        $this->assertSame(0, $providerProcessor->processCalls);
        $this->assertSame(0, $providerProcessor->eventRecognitionCalls);
        $this->assertNotNull($context->rawData);
        $this->assertNull($context->rawData->payload);
        $this->assertNull($context->rawData->providerEventType);
    }

    public function testValidationFailurePreservesRawRequestData(): void
    {
        $providerProcessor = new class implements WebhookProviderProcessorInterface {
            public int $processCalls = 0;

            public function getProviderId(): string
            {
                return 'robokassa';
            }

            public function process(WebhookInput $input): WebhookProcessingResult
            {
                $this->processCalls++;

                throw new LogicException('Provider processor must not be called after validation failure.');
            }
        };
        $input = new WebhookInput(
            rawBody: 'OutSum=10.00&InvId=42&SignatureValue=invalid',
            headers: [
                'Content-Type' => 'application/x-www-form-urlencoded',
                'X-Debug-Trace' => ['first', 'second'],
            ],
            queryParams: [
                'OutSum' => '10.00',
                'InvId' => '42',
                'SignatureValue' => 'invalid',
                'Shp_order' => 'A-100',
            ],
            bodyParams: [
                'form_only' => 'preserved',
                'nested' => ['value' => 'kept'],
            ],
            providerId: 'robokassa',
        );
        $processor = new WebhookProcessor(
            new WebhookProviderProcessorRegistry($providerProcessor),
            new WebhookProviderValidatorRegistry(new FailedWebhookProviderValidator('robokassa')),
        );

        $context = $processor->process($input);

        $this->assertSame(WebhookProcessingStatus::ValidationFailed, $context->status);
        $this->assertSame(0, $providerProcessor->processCalls);
        $this->assertNotNull($context->rawData);
        $this->assertSame('OutSum=10.00&InvId=42&SignatureValue=invalid', $context->rawData->rawBody);
        $this->assertSame([
            'Content-Type' => 'application/x-www-form-urlencoded',
            'X-Debug-Trace' => ['first', 'second'],
        ], $context->rawData->getHeaders());
        $this->assertSame([
            'OutSum' => '10.00',
            'InvId' => '42',
            'SignatureValue' => 'invalid',
            'Shp_order' => 'A-100',
        ], $context->rawData->getQueryParams());
        $this->assertSame([
            'form_only' => 'preserved',
            'nested' => ['value' => 'kept'],
        ], $context->rawData->getBodyParams());
        $this->assertNull($context->rawData->getPayload());
        $this->assertNull($context->rawData->getProviderEventType());
    }
}
