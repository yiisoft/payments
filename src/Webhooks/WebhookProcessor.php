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

    public function process(WebhookInput $input): WebhookContext
    {
        if ($input->providerId === null) {
            throw new LogicException('Webhook provider processor resolution is not implemented yet.');
        }

        $providerProcessor = $this->providerProcessorRegistry->get($input->providerId);

        if ($providerProcessor === null) {
            return $this->createContext(
                $input,
                $this->providerProcessorRegistry->missingProcessorResult(
                    $input->providerId,
                    new WebhookRawData(
                        rawBody: $input->rawBody,
                        headers: $input->headers,
                    ),
                ),
            );
        }

        return $this->createContext($input, $providerProcessor->process($input));
    }

    private function createContext(WebhookInput $input, WebhookProcessingResult $result): WebhookContext
    {
        return new WebhookContext(
            providerId: $input->providerId,
            eventType: $result->eventType,
            status: $result->status,
            validationFailureReason: $result->status === WebhookProcessingStatus::ValidationFailed ? $result->reason : null,
            unsupportedEventReason: $result->status === WebhookProcessingStatus::UnsupportedEvent ? $result->reason : null,
            unknownEventReason: $result->status === WebhookProcessingStatus::UnknownEvent ? $result->reason : null,
            rawInput: $input,
            rawData: $result->rawData,
        );
    }
}
