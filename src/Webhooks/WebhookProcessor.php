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
        private readonly ?WebhookProviderValidatorRegistry $providerValidatorRegistry = null,
    ) {
    }

    public function process(WebhookInput $input): WebhookContext
    {
        if ($input->providerId === null) {
            throw new LogicException('Webhook provider processor resolution is not implemented yet.');
        }

        $providerValidator = $this->providerValidatorRegistry?->get($input->providerId);

        if ($providerValidator !== null) {
            $validationResult = $providerValidator->validate($input);

            if (!$validationResult->isValid) {
                return $this->createContext(
                    $input,
                    WebhookProcessingResult::validationFailed(
                        rawData: $this->createRawData($input),
                        reason: $validationResult->reason,
                    ),
                );
            }
        }

        $providerProcessor = $this->providerProcessorRegistry->get($input->providerId);

        if ($providerProcessor === null) {
            return $this->createContext(
                $input,
                $this->providerProcessorRegistry->missingProcessorResult(
                    $input->providerId,
                    $this->createRawData($input),
                ),
            );
        }

        return $this->createContext($input, $providerProcessor->process($input));
    }

    private function createRawData(WebhookInput $input): WebhookRawData
    {
        return new WebhookRawData(
            rawBody: $input->rawBody,
            headers: $input->headers,
            queryParams: $input->queryParams,
            bodyParams: $input->bodyParams,
        );
    }

    private function createContext(WebhookInput $input, WebhookProcessingResult $result): WebhookContext
    {
        return new WebhookContext(
            providerId: $input->providerId,
            eventType: $result->eventType,
            status: $result->status,
            paymentStatus: $result->paymentStatus,
            validationFailureReason: $result->status === WebhookProcessingStatus::ValidationFailed ? $result->reason : null,
            unsupportedEventReason: $result->status === WebhookProcessingStatus::UnsupportedEvent ? $result->reason : null,
            unknownEventReason: $result->status === WebhookProcessingStatus::UnknownEvent ? $result->reason : null,
            rawInput: $input,
            rawData: $result->rawData,
        );
    }
}
