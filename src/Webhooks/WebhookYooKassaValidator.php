<?php

declare(strict_types=1);

namespace Yiisoft\Payments\Webhooks;

use InvalidArgumentException;
use JsonException;

/**
 * Provider-specific validator for YooKassa webhook requests.
 *
 * R1 performs minimal real authenticity validation with YooKassa Basic Auth
 * credentials and keeps structural JSON payload checks local to the validator.
 */
final readonly class WebhookYooKassaValidator implements WebhookProviderValidatorInterface
{
    public function __construct(
        private string $shopId,
        private string $secretKey,
    ) {
        if (trim($shopId) === '') {
            throw new InvalidArgumentException('YooKassa shop ID must be a non-empty string.');
        }

        if (trim($secretKey) === '') {
            throw new InvalidArgumentException('YooKassa secret key must be a non-empty string.');
        }
    }

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

        $authorizationResult = $this->validateAuthorization($input, $payload['event']);

        if ($authorizationResult instanceof WebhookValidationResult) {
            return $authorizationResult;
        }

        return WebhookValidationResult::success();
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

    private function validateAuthorization(WebhookInput $input, string $providerEventType): ?WebhookValidationResult
    {
        $authorizationHeaders = $input->getHeader('Authorization');

        if ($authorizationHeaders === []) {
            return WebhookValidationResult::failure(new WebhookReason(
                code: new WebhookReasonCode('yookassa_authorization_header_missing'),
                message: 'YooKassa Authorization header is missing.',
                providerEventType: $providerEventType,
            ));
        }

        foreach ($authorizationHeaders as $authorizationHeader) {
            $credentials = $this->parseBasicAuthorizationHeader($authorizationHeader);

            if ($credentials === null) {
                continue;
            }

            [$shopId, $secretKey] = $credentials;

            if (hash_equals($this->shopId, $shopId) && hash_equals($this->secretKey, $secretKey)) {
                return null;
            }
        }

        return WebhookValidationResult::failure(new WebhookReason(
            code: new WebhookReasonCode('yookassa_basic_auth_mismatch'),
            message: 'YooKassa Basic Auth credentials do not match the configured shop ID and secret key.',
            providerEventType: $providerEventType,
        ));
    }

    /**
     * @return array{string, string}|null
     */
    private function parseBasicAuthorizationHeader(string $authorizationHeader): ?array
    {
        $authorizationHeader = trim($authorizationHeader);

        if (!str_starts_with(strtolower($authorizationHeader), 'basic ')) {
            return null;
        }

        $encodedCredentials = trim(substr($authorizationHeader, 6));

        if ($encodedCredentials === '') {
            return null;
        }

        $decodedCredentials = base64_decode($encodedCredentials, true);

        if ($decodedCredentials === false || !str_contains($decodedCredentials, ':')) {
            return null;
        }

        [$shopId, $secretKey] = explode(':', $decodedCredentials, 2);

        if ($shopId === '' || $secretKey === '') {
            return null;
        }

        return [$shopId, $secretKey];
    }
}
