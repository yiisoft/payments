<?php

declare(strict_types=1);

namespace Yiisoft\Payments\Webhooks;

use LogicException;

/**
 * Common webhook processing service that owns provider processor resolution flow.
 */
final class WebhookProcessor implements WebhookProcessorInterface
{
    public function __construct(
        private readonly WebhookProviderProcessorRegistry $providerProcessorRegistry,
    ) {
    }

    public function process(WebhookInput $input): WebhookProcessingResult
    {
        if ($input->providerId === null) {
            throw new LogicException('Webhook provider processor resolution is not implemented yet.');
        }

        $providerProcessor = $this->providerProcessorRegistry->get($input->providerId);

        if ($providerProcessor === null) {
            return $this->providerProcessorRegistry->missingProcessorResult(
                $input->providerId,
                new WebhookRawData(
                    rawBody: $input->rawBody,
                    headers: $input->headers,
                ),
            );
        }

        $result = $providerProcessor->process($input);

        if ($result->status === WebhookProcessingStatus::ValidationFailed) {
            return $result;
        }

        if ($result->status === WebhookProcessingStatus::UnsupportedEvent) {
            return $result;
        }

        return $result;
    }
}
