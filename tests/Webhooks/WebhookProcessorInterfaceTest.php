<?php

declare(strict_types=1);

namespace Yiisoft\Payments\Tests\Webhooks;

use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use Yiisoft\Payments\Webhooks\WebhookEventType;
use Yiisoft\Payments\Webhooks\WebhookInput;
use Yiisoft\Payments\Webhooks\WebhookProcessingResult;
use Yiisoft\Payments\Webhooks\WebhookProcessingStatus;
use Yiisoft\Payments\Webhooks\WebhookProcessorInterface;
use Yiisoft\Payments\Webhooks\WebhookRawData;

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

    public function testProcessReturnTypeIsFixedToWebhookProcessingResult(): void
    {
        $method = new ReflectionMethod(WebhookProcessorInterface::class, 'process');
        $returnType = $method->getReturnType();

        $this->assertNotNull($returnType);
        $this->assertFalse($returnType->allowsNull());
        $this->assertSame(WebhookProcessingResult::class, $returnType->getName());
    }

    public function testProcessCanReturnProcessedResult(): void
    {
        $processor = new class implements WebhookProcessorInterface {
            public function process(WebhookInput $input): WebhookProcessingResult
            {
                return new WebhookProcessingResult(
                    status: WebhookProcessingStatus::Processed,
                    eventType: WebhookEventType::PaymentSucceeded,
                );
            }
        };

        $result = $processor->process(new WebhookInput(rawBody: '{"id":"evt_processed"}'));

        $this->assertSame(WebhookProcessingStatus::Processed, $result->status);
        $this->assertSame(WebhookEventType::PaymentSucceeded, $result->eventType);
        $this->assertNull($result->reason);
    }

    public function testValidationFailureIsReturnedAsProcessingResult(): void
    {
        $processor = new class implements WebhookProcessorInterface {
            public function process(WebhookInput $input): WebhookProcessingResult
            {
                return WebhookProcessingResult::validationFailed();
            }
        };

        $result = $processor->process(new WebhookInput(rawBody: '{"id":"evt_invalid"}'));

        $this->assertSame(WebhookProcessingStatus::ValidationFailed, $result->status);
        $this->assertNull($result->eventType);
        $this->assertNotNull($result->reason);
        $this->assertSame('validation_failed', $result->reason->code->value);
    }

    public function testProcessCanReturnUnknownEventResult(): void
    {
        $processor = new class implements WebhookProcessorInterface {
            public function process(WebhookInput $input): WebhookProcessingResult
            {
                return WebhookProcessingResult::unknownEvent('provider.event.not_in_mapping');
            }
        };

        $result = $processor->process(new WebhookInput(rawBody: '{"type":"provider.event.not_in_mapping"}'));

        $this->assertSame(WebhookProcessingStatus::UnknownEvent, $result->status);
        $this->assertNull($result->eventType);
        $this->assertNotNull($result->reason);
        $this->assertSame('unknown_event_type', $result->reason->code->value);
        $this->assertSame('provider.event.not_in_mapping', $result->reason->providerEventType);
    }

    public function testProcessCanReturnUnsupportedEventResultWithRawData(): void
    {
        $rawData = new WebhookRawData(
            rawBody: '{"type":"charge.refunded"}',
            headers: ['Stripe-Signature' => 't=123,v1=signature'],
            payload: ['type' => 'charge.refunded'],
            providerEventType: 'charge.refunded',
        );

        $processor = new class ($rawData) implements WebhookProcessorInterface {
            public function __construct(
                private readonly WebhookRawData $rawData,
            ) {
            }

            public function process(WebhookInput $input): WebhookProcessingResult
            {
                return WebhookProcessingResult::unsupportedEvent(
                    WebhookEventType::PaymentRefunded,
                    'charge.refunded',
                    $this->rawData,
                );
            }
        };

        $result = $processor->process(new WebhookInput(rawBody: '{"type":"charge.refunded"}'));

        $this->assertSame(WebhookProcessingStatus::UnsupportedEvent, $result->status);
        $this->assertSame(WebhookEventType::PaymentRefunded, $result->eventType);
        $this->assertNotNull($result->reason);
        $this->assertSame('unsupported_event_type', $result->reason->code->value);
        $this->assertSame('charge.refunded', $result->reason->providerEventType);
        $this->assertSame($rawData, $result->rawData);
    }
}
