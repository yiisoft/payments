<?php

declare(strict_types=1);

namespace Yiisoft\Payments\Webhooks;

/**
 * Parses Robokassa callback fields into an intermediate provider-processing payload.
 */
final readonly class WebhookRobokassaPayloadParser implements WebhookPayloadParserInterface
{
    public function parsePayload(
        WebhookInput $input,
        WebhookEventType $eventType,
        ?string $providerEventType = null,
    ): WebhookPayload {
        $data = $this->collectCallbackData($input);

        return new WebhookPayload(
            providerId: $input->providerId,
            eventType: $eventType,
            providerEventType: $providerEventType,
            data: $data,
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
     * @return array<string, mixed>
     */
    private function collectCallbackData(WebhookInput $input): array
    {
        return $input->queryParams + $input->bodyParams;
    }
}
