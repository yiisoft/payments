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
}
