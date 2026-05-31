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
     * Returns provider callback fields as received, without renaming Robokassa keys such as
     * OutSum, InvId, SignatureValue, or Shp_* to application-specific names.
     *
     * @return array<string, mixed>
     */
    private function collectCallbackData(WebhookInput $input): array
    {
        $data = $input->bodyParams;

        foreach ($input->queryParams as $parameterName => $parameterValue) {
            $data[$parameterName] = $parameterValue;
        }

        return $data;
    }
}
