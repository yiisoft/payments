<?php

declare(strict_types=1);

namespace Yiisoft\Payments\Tests\Webhooks\Support;

use Yiisoft\Payments\Webhooks\WebhookEventType;
use Yiisoft\Payments\Webhooks\WebhookInput;
use Yiisoft\Payments\Webhooks\WebhookProcessingResult;
use Yiisoft\Payments\Webhooks\WebhookProcessingStatus;
use Yiisoft\Payments\Webhooks\WebhookProviderProcessorInterface;
use Yiisoft\Payments\Webhooks\WebhookRawData;

/**
 * Test-only provider processor that always returns a successful processed result.
 */
final class SuccessfulWebhookProviderProcessor implements WebhookProviderProcessorInterface
{
    public int $processCalls = 0;
    public ?WebhookInput $processedInput = null;

    public function __construct(
        private readonly string $providerId = 'test-provider',
        private readonly WebhookEventType $eventType = WebhookEventType::PaymentSucceeded,
        private readonly ?string $providerEventType = 'test.payment_succeeded',
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

        return new WebhookProcessingResult(
            status: WebhookProcessingStatus::Processed,
            eventType: $this->eventType,
            rawData: new WebhookRawData(
                rawBody: $input->rawBody,
                headers: $input->headers,
                payload: $this->payload,
                providerEventType: $this->providerEventType,
            ),
        );
    }
}
