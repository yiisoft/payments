<?php

declare(strict_types=1);

namespace Yiisoft\Payments\Webhooks;

/**
 * Parses Stripe JSON webhook payloads into an intermediate provider-processing payload.
 */
final readonly class WebhookStripePayloadParser implements WebhookPayloadParserInterface
{
    public function __construct(
        private WebhookJsonPayloadDecoder $decoder = new WebhookJsonPayloadDecoder(),
    ) {
    }

    public function parsePayload(
        WebhookInput $input,
        WebhookEventType $eventType,
        ?string $providerEventType = null,
    ): WebhookPayload {
        $data = $this->decoder->decode($input->rawBody);

        return new WebhookPayload(
            providerId: $input->providerId,
            eventType: $eventType,
            providerEventType: $providerEventType,
            data: $data,
            paymentStatus: $this->extractPaymentStatus($data),
            rawData: new WebhookRawData(
                rawBody: $input->rawBody,
                headers: $input->headers,
                payload: $data,
                providerEventType: $providerEventType,
                queryParams: $input->queryParams,
                bodyParams: $input->bodyParams,
            ),
        );
    }

    /**
     * @param array<string, mixed> $data
     */
    private function extractPaymentStatus(array $data): ?string
    {
        $object = $data['data']['object'] ?? null;

        if (!is_array($object)) {
            return null;
        }

        $status = $object['status'] ?? null;

        return is_string($status) ? $status : null;
    }
}
