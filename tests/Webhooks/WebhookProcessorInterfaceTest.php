<?php

declare(strict_types=1);

namespace Yiisoft\Payments\Tests\Webhooks;

use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use Yiisoft\Payments\Webhooks\WebhookContext;
use Yiisoft\Payments\Webhooks\WebhookEventType;
use Yiisoft\Payments\Webhooks\WebhookInput;
use Yiisoft\Payments\Webhooks\WebhookProcessingStatus;
use Yiisoft\Payments\Webhooks\WebhookProcessorInterface;
use Yiisoft\Payments\Webhooks\WebhookRawData;
use Yiisoft\Payments\Webhooks\WebhookReason;
use Yiisoft\Payments\Webhooks\WebhookReasonCode;

final class WebhookProcessorInterfaceTest extends TestCase
{
    public function testProcessAcceptsWebhookInput(): void
    {
        $method = new ReflectionMethod(WebhookProcessorInterface::class, 'process');
        $parameters = $method->getParameters();

        $this->assertCount(1, $parameters);
        $this->assertSame('input', $parameters[0]->getName());
        $this->assertFalse($parameters[0]->allowsNull());
        $this->assertSame(WebhookInput::class, $parameters[0]->getType()?->getName());
    }

    public function testProcessReturnTypeIsFixedToWebhookContext(): void
    {
        $method = new ReflectionMethod(WebhookProcessorInterface::class, 'process');
        $returnType = $method->getReturnType();

        $this->assertNotNull($returnType);
        $this->assertFalse($returnType->allowsNull());
        $this->assertSame(WebhookContext::class, $returnType->getName());
    }

    public function testProcessCanReturnProcessedContext(): void
    {
        $processor = new class implements WebhookProcessorInterface {
            public function process(WebhookInput $input): WebhookContext
            {
                return new WebhookContext(
                    providerId: $input->providerId,
                    eventType: WebhookEventType::PaymentSucceeded,
                    status: WebhookProcessingStatus::Processed,
                    rawInput: $input,
                );
            }
        };

        $input = new WebhookInput(rawBody: '{"id":"evt_processed"}', providerId: 'stripe');
        $context = $processor->process($input);

        $this->assertSame('stripe', $context->providerId);
        $this->assertSame(WebhookProcessingStatus::Processed, $context->status);
        $this->assertSame(WebhookEventType::PaymentSucceeded, $context->eventType);
        $this->assertNull($context->validationFailureReason);
        $this->assertSame($input, $context->rawInput);
    }

    public function testValidationFailureIsReturnedAsContext(): void
    {
        $reason = new WebhookReason(
            code: new WebhookReasonCode('validation_failed'),
            message: 'Webhook request failed provider-specific validation.',
        );
        $processor = new class ($reason) implements WebhookProcessorInterface {
            public function __construct(
                private readonly WebhookReason $reason,
            ) {
            }

            public function process(WebhookInput $input): WebhookContext
            {
                return new WebhookContext(
                    status: WebhookProcessingStatus::ValidationFailed,
                    validationFailureReason: $this->reason,
                    rawInput: $input,
                );
            }
        };

        $input = new WebhookInput(rawBody: '{"id":"evt_invalid"}');
        $context = $processor->process($input);

        $this->assertSame(WebhookProcessingStatus::ValidationFailed, $context->status);
        $this->assertNull($context->eventType);
        $this->assertSame($reason, $context->validationFailureReason);
        $this->assertSame('validation_failed', $context->validationFailureReason->code->value);
        $this->assertSame($input, $context->rawInput);
    }

    public function testProcessCanReturnUnknownEventContext(): void
    {
        $reason = new WebhookReason(
            code: new WebhookReasonCode('unknown_event_type'),
            message: 'Provider event type is not recognized by the webhook event mapping.',
            providerEventType: 'provider.event.not_in_mapping',
        );
        $processor = new class ($reason) implements WebhookProcessorInterface {
            public function __construct(
                private readonly WebhookReason $reason,
            ) {
            }

            public function process(WebhookInput $input): WebhookContext
            {
                return new WebhookContext(
                    status: WebhookProcessingStatus::UnknownEvent,
                    unknownEventReason: $this->reason,
                    rawInput: $input,
                );
            }
        };

        $input = new WebhookInput(rawBody: '{"type":"provider.event.not_in_mapping"}');
        $context = $processor->process($input);

        $this->assertSame(WebhookProcessingStatus::UnknownEvent, $context->status);
        $this->assertNull($context->eventType);
        $this->assertSame($reason, $context->unknownEventReason);
        $this->assertSame('unknown_event_type', $context->unknownEventReason->code->value);
        $this->assertSame('provider.event.not_in_mapping', $context->unknownEventReason->providerEventType);
        $this->assertSame($input, $context->rawInput);
    }

    public function testProcessCanReturnUnsupportedEventContextWithRawData(): void
    {
        $rawData = new WebhookRawData(
            rawBody: '{"type":"charge.refunded"}',
            headers: ['Stripe-Signature' => 't=123,v1=signature'],
            payload: ['type' => 'charge.refunded'],
            providerEventType: 'charge.refunded',
        );
        $reason = new WebhookReason(
            code: new WebhookReasonCode('unsupported_event_type'),
            message: 'Webhook event type is recognized but is not supported by the current webhook contract.',
            providerEventType: 'charge.refunded',
        );

        $processor = new class ($rawData, $reason) implements WebhookProcessorInterface {
            public function __construct(
                private readonly WebhookRawData $rawData,
                private readonly WebhookReason $reason,
            ) {
            }

            public function process(WebhookInput $input): WebhookContext
            {
                return new WebhookContext(
                    eventType: WebhookEventType::PaymentRefunded,
                    status: WebhookProcessingStatus::UnsupportedEvent,
                    unsupportedEventReason: $this->reason,
                    rawInput: $input,
                    rawData: $this->rawData,
                );
            }
        };

        $input = new WebhookInput(rawBody: '{"type":"charge.refunded"}');
        $context = $processor->process($input);

        $this->assertSame(WebhookProcessingStatus::UnsupportedEvent, $context->status);
        $this->assertSame(WebhookEventType::PaymentRefunded, $context->eventType);
        $this->assertSame($reason, $context->unsupportedEventReason);
        $this->assertSame('unsupported_event_type', $context->unsupportedEventReason->code->value);
        $this->assertSame('charge.refunded', $context->unsupportedEventReason->providerEventType);
        $this->assertSame($input, $context->rawInput);
        $this->assertSame($rawData, $context->rawData);
    }
}
