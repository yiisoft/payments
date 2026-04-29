<?php

declare(strict_types=1);

namespace Yiisoft\Payments\Webhooks;

use JsonException;

/**
 * Provider-specific validator skeleton for YooKassa webhook requests.
 *
 * R1 validates only request/payload indicators that are available in the
 * incoming request itself: a non-empty JSON object payload with YooKassa
 * notification event and object fields. The current package configuration
 * exposes YooKassa API credentials for outgoing API requests, but it does not
 * expose a webhook-specific signing secret, shared callback token, certificate
 * chain, signature header, or a provider-side verification endpoint contract
 * that can be used for local authenticity validation. Signature-level
 * validation is therefore intentionally not supported in R1 and must be added
 * only as a separate, explicitly agreed provider-specific contract. Until then,
 * this validator must stay fail-closed after structural request/payload
 * validation passes.
 */
final readonly class WebhookYooKassaValidator implements WebhookProviderValidatorInterface
{
    private const R1_LIMITATION_REASON_CODE = 'yookassa_authenticity_indicators_not_available';

    public function getProviderId(): string
    {
        return 'yookassa';
    }

    public function validate(WebhookInput $input): WebhookValidationResult
    {
        $payload = $this->decodePayload($input->rawBody);

        if ($payload instanceof WebhookValidationResult) {
            return $payload;
        }

        if (!$this->hasNonEmptyStringField($payload, 'event')) {
            return WebhookValidationResult::failure(new WebhookReason(
                code: new WebhookReasonCode('yookassa_event_missing'),
                message: 'YooKassa webhook payload must contain a non-empty event field.',
            ));
        }

        if (!isset($payload['object']) || !is_array($payload['object'])) {
            return WebhookValidationResult::failure(new WebhookReason(
                code: new WebhookReasonCode('yookassa_object_missing'),
                message: 'YooKassa webhook payload must contain an object field.',
                providerEventType: $payload['event'],
            ));
        }

        return WebhookValidationResult::failure(new WebhookReason(
            code: new WebhookReasonCode(self::R1_LIMITATION_REASON_CODE),
            message: 'YooKassa webhook signature-level validation is not supported in R1 because the current API/config does not expose a webhook-specific authenticity indicator.',
            providerEventType: $payload['event'],
        ));
    }

    /**
     * @return array<string, mixed>|WebhookValidationResult
     */
    private function decodePayload(string $rawBody): array|WebhookValidationResult
    {
        if (trim($rawBody) === '') {
            return WebhookValidationResult::failure(new WebhookReason(
                code: new WebhookReasonCode('yookassa_payload_empty'),
                message: 'YooKassa webhook payload must be a non-empty JSON object.',
            ));
        }

        try {
            $payload = json_decode($rawBody, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return WebhookValidationResult::failure(new WebhookReason(
                code: new WebhookReasonCode('yookassa_payload_malformed_json'),
                message: 'YooKassa webhook payload must be valid JSON.',
            ));
        }

        if (!is_array($payload)) {
            return WebhookValidationResult::failure(new WebhookReason(
                code: new WebhookReasonCode('yookassa_payload_invalid'),
                message: 'YooKassa webhook payload must be a JSON object.',
            ));
        }

        return $payload;
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function hasNonEmptyStringField(array $payload, string $field): bool
    {
        return isset($payload[$field]) && is_string($payload[$field]) && trim($payload[$field]) !== '';
    }
}
