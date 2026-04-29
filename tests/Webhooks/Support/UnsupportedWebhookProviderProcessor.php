<?php

declare(strict_types=1);

namespace Yiisoft\Payments\Tests\Webhooks\Support;

use Yiisoft\Payments\Webhooks\WebhookEventType;
use Yiisoft\Payments\Webhooks\WebhookInput;
use Yiisoft\Payments\Webhooks\WebhookProcessingResult;
use Yiisoft\Payments\Webhooks\WebhookProviderProcessorInterface;
use Yiisoft\Payments\Webhooks\WebhookRawData;

/**
 * Test-only provider processor that always returns an unsupported event result.
 */
final class UnsupportedWebhookProviderProcessor implements WebhookProviderProcessorInterface
{
    public int $processCalls = 0;
    public ?WebhookInput $processedInput = null;

    public function __construct(
        private readonly string $providerId = 'test-provider',
        private readonly WebhookEventType $eventType = WebhookEventType::PaymentRefunded,
        private readonly ?string $providerEventType = 'test.payment_refunded',
        private readonly mixed $payload = null,
    ) {
    }

    public function getProviderId(): string
    {
        return $this->providerId;
    }

    public function process(WebhookInput $input): WebhookProcessingResult
    {
        $this->processCalls++;
        $this->processedInput = $input;

        return WebhookProcessingResult::unsupportedEvent(
            eventType: $this->eventType,
            providerEventType: $this->providerEventType,
            rawData: new WebhookRawData(
                rawBody: $input->rawBody,
                headers: $input->headers,
                payload: $this->payload,
                providerEventType: $this->providerEventType,
            ),
        );
    }
}
