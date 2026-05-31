<?php

declare(strict_types=1);

namespace Yiisoft\Payments\Webhooks;

/**
 * Provider-specific processor that connects Stripe webhook recognition, parsing, and payment mapping.
 */
final readonly class WebhookStripeProviderProcessor implements WebhookProviderProcessorInterface
{
    public function __construct(
        private WebhookEventRecognizerInterface $eventRecognizer = new WebhookStripeEventRecognizer(),
        private WebhookPayloadParserInterface $payloadParser = new WebhookStripePayloadParser(),
        private PaymentWebhookMapperInterface $paymentWebhookMapper = new WebhookStripePaymentWebhookMapper(),
    ) {
    }

    public function getProviderId(): string
    {
        return 'stripe';
    }

    public function process(WebhookInput $input): WebhookProcessingResult
    {
        $providerEventType = $this->eventRecognizer->recognizeProviderEventType($input);

        if ($providerEventType === null) {
            return $this->paymentWebhookMapper->mapPaymentWebhook(
                new WebhookPayload(
                    providerId: $input->providerId,
                    rawData: $this->createRawData($input),
                ),
            );
        }

        $eventType = $this->eventRecognizer->recognizeEventType($providerEventType);

        if ($eventType === null) {
            return $this->paymentWebhookMapper->mapPaymentWebhook(
                new WebhookPayload(
                    providerId: $input->providerId,
                    providerEventType: $providerEventType,
                    rawData: $this->createRawData($input, $providerEventType),
                ),
            );
        }

        return $this->paymentWebhookMapper->mapPaymentWebhook(
            $this->payloadParser->parsePayload($input, $eventType, $providerEventType),
        );
    }

    private function createRawData(WebhookInput $input, ?string $providerEventType = null): WebhookRawData
    {
        $payload = (new WebhookJsonPayloadDecoder())->decode($input->rawBody);

        return new WebhookRawData(
            rawBody: $input->rawBody,
            headers: $input->headers,
            payload: $payload,
            providerEventType: $providerEventType,
            queryParams: $input->queryParams,
            bodyParams: $input->bodyParams,
        );
    }
}
