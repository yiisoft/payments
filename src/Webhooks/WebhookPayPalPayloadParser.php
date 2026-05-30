<?php

declare(strict_types=1);

namespace Yiisoft\Payments\Webhooks;

/**
 * Parses PayPal JSON webhook payloads into an intermediate provider-processing payload.
 */
final readonly class WebhookPayPalPayloadParser implements WebhookPayloadParserInterface
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
        $resource = $data['resource'] ?? null;

        if (!is_array($resource)) {
            return null;
        }

        $status = $resource['status'] ?? null;

        return is_string($status) ? $status : null;
    }
}
