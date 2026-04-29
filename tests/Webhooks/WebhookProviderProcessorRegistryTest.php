<?php

declare(strict_types=1);

namespace Yiisoft\Payments\Tests\Webhooks;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Yiisoft\Payments\Webhooks\WebhookProviderProcessorInterface;
use Yiisoft\Payments\Webhooks\WebhookProviderProcessorRegistry;
use Yiisoft\Payments\Webhooks\WebhookInput;
use Yiisoft\Payments\Webhooks\WebhookProcessingResult;
use Yiisoft\Payments\Webhooks\WebhookProcessingStatus;
use Yiisoft\Payments\Webhooks\WebhookRawData;

final class WebhookProviderProcessorRegistryTest extends TestCase
{
    public function testRegistryResolvesProviderProcessorByProviderId(): void
    {
        $stripeProcessor = $this->createProcessor('stripe');
        $paypalProcessor = $this->createProcessor('paypal');
        $registry = new WebhookProviderProcessorRegistry($stripeProcessor, $paypalProcessor);

        $this->assertTrue($registry->has('stripe'));
        $this->assertTrue($registry->has('paypal'));
        $this->assertSame($stripeProcessor, $registry->get('stripe'));
        $this->assertSame($paypalProcessor, $registry->get('paypal'));
    }

    public function testRegistryReturnsNullForUnknownProviderId(): void
    {
        $registry = new WebhookProviderProcessorRegistry($this->createProcessor('stripe'));

        $this->assertFalse($registry->has('robokassa'));
        $this->assertNull($registry->get('robokassa'));
    }

    public function testRegistryDoesNotApplyImplicitProviderNameNormalization(): void
    {
        $stripeProcessor = $this->createProcessor('stripe');
        $registry = new WebhookProviderProcessorRegistry($stripeProcessor);

        $this->assertSame($stripeProcessor, $registry->get('stripe'));
        $this->assertNull($registry->get('Stripe'));
        $this->assertNull($registry->get(' stripe '));
    }

    public function testRegistryReturnsValidationFailedResultForMissingProviderProcessor(): void
    {
        $registry = new WebhookProviderProcessorRegistry($this->createProcessor('stripe'));

        $result = $registry->missingProcessorResult('paypal');

        $this->assertSame(WebhookProcessingStatus::ValidationFailed, $result->status);
        $this->assertNull($result->eventType);
        $this->assertNotNull($result->reason);
        $this->assertSame('missing_provider_processor', $result->reason->code->value);
        $this->assertSame(
            'Webhook provider processor is not registered for provider "paypal".',
            $result->reason->message,
        );
    }

    public function testRegistryKeepsRawDataForMissingProviderProcessor(): void
    {
        $registry = new WebhookProviderProcessorRegistry($this->createProcessor('stripe'));
        $rawData = new WebhookRawData(
            rawBody: '{"event":"payment.captured"}',
            headers: ['X-Provider' => 'paypal'],
            payload: ['event' => 'payment.captured'],
            providerEventType: 'payment.captured',
        );

        $result = $registry->missingProcessorResult('paypal', $rawData);

        $this->assertSame(WebhookProcessingStatus::ValidationFailed, $result->status);
        $this->assertNotNull($result->reason);
        $this->assertSame('payment.captured', $result->reason->providerEventType);
        $this->assertSame($rawData, $result->rawData);
    }

    public function testRegistryRejectsEmptyProviderId(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Webhook provider processor ID must be a non-empty string.');

        new WebhookProviderProcessorRegistry($this->createProcessor(''));
    }

    public function testRegistryRejectsDuplicateProviderId(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Webhook provider processor with ID "stripe" is already registered.');

        new WebhookProviderProcessorRegistry(
            $this->createProcessor('stripe'),
            $this->createProcessor('stripe'),
        );
    }

    private function createProcessor(string $providerId): WebhookProviderProcessorInterface
    {
        return new class ($providerId) implements WebhookProviderProcessorInterface {
            public function __construct(
                private readonly string $providerId,
            ) {
            }

            public function getProviderId(): string
            {
                return $this->providerId;
            }

            public function process(WebhookInput $input): WebhookProcessingResult
            {
                return new WebhookProcessingResult(WebhookProcessingStatus::Processed);
            }
        };
    }
}
