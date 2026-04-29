<?php

declare(strict_types=1);

namespace Yiisoft\Payments\Tests\Webhooks;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Yiisoft\Payments\Webhooks\ProviderWebhookProcessorInterface;
use Yiisoft\Payments\Webhooks\ProviderWebhookProcessorRegistry;
use Yiisoft\Payments\Webhooks\WebhookInput;
use Yiisoft\Payments\Webhooks\WebhookProcessingResult;
use Yiisoft\Payments\Webhooks\WebhookProcessingStatus;

final class ProviderWebhookProcessorRegistryTest extends TestCase
{
    public function testRegistryResolvesProviderProcessorByProviderId(): void
    {
        $stripeProcessor = $this->createProcessor('stripe');
        $paypalProcessor = $this->createProcessor('paypal');
        $registry = new ProviderWebhookProcessorRegistry($stripeProcessor, $paypalProcessor);

        $this->assertTrue($registry->has('stripe'));
        $this->assertTrue($registry->has('paypal'));
        $this->assertSame($stripeProcessor, $registry->get('stripe'));
        $this->assertSame($paypalProcessor, $registry->get('paypal'));
    }

    public function testRegistryReturnsNullForUnknownProviderId(): void
    {
        $registry = new ProviderWebhookProcessorRegistry($this->createProcessor('stripe'));

        $this->assertFalse($registry->has('robokassa'));
        $this->assertNull($registry->get('robokassa'));
    }

    public function testRegistryDoesNotApplyImplicitProviderNameNormalization(): void
    {
        $stripeProcessor = $this->createProcessor('stripe');
        $registry = new ProviderWebhookProcessorRegistry($stripeProcessor);

        $this->assertSame($stripeProcessor, $registry->get('stripe'));
        $this->assertNull($registry->get('Stripe'));
        $this->assertNull($registry->get(' stripe '));
    }

    public function testRegistryRejectsEmptyProviderId(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Webhook provider processor ID must be a non-empty string.');

        new ProviderWebhookProcessorRegistry($this->createProcessor(''));
    }

    public function testRegistryRejectsDuplicateProviderId(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Webhook provider processor with ID "stripe" is already registered.');

        new ProviderWebhookProcessorRegistry(
            $this->createProcessor('stripe'),
            $this->createProcessor('stripe'),
        );
    }

    private function createProcessor(string $providerId): ProviderWebhookProcessorInterface
    {
        return new class ($providerId) implements ProviderWebhookProcessorInterface {
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
