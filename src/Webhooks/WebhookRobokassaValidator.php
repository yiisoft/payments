<?php

declare(strict_types=1);

namespace Yiisoft\Payments\Webhooks;

use InvalidArgumentException;

/**
 * Provider-specific validator for Robokassa ResultURL callbacks.
 *
 * R1 supports the classic Robokassa ResultURL callback format described by
 * {@see WebhookRobokassaCallbackFormat}. This validator checks required
 * callback parameters and the password2-based MD5 signature.
 */
final readonly class WebhookRobokassaValidator implements WebhookProviderValidatorInterface
{
    public function __construct(
        private string $password2,
    ) {
        if (trim($password2) === '') {
            throw new InvalidArgumentException('Robokassa password2 must be a non-empty string.');
        }
    }

    public function getProviderId(): string
    {
        return WebhookRobokassaCallbackFormat::PROVIDER_ID;
    }

    public function validate(WebhookInput $input): WebhookValidationResult
    {
        $callbackParams = $input->queryParams + $input->bodyParams;

        foreach (WebhookRobokassaCallbackFormat::requiredParameters() as $parameterName) {
            if (
                !array_key_exists($parameterName, $callbackParams)
                || !is_string($callbackParams[$parameterName])
                || trim($callbackParams[$parameterName]) === ''
            ) {
                return WebhookValidationResult::failure(new WebhookReason(
                    code: new WebhookReasonCode('robokassa_required_parameter_missing'),
                    message: sprintf('Required Robokassa callback parameter "%s" is missing or empty.', $parameterName),
                ));
            }
        }

        $expectedSignature = $this->calculateSignature($callbackParams);
        $actualSignature = trim($callbackParams[WebhookRobokassaCallbackFormat::SIGNATURE_PARAMETER]);

        if (!hash_equals(strtolower($expectedSignature), strtolower($actualSignature))) {
            return WebhookValidationResult::failure(new WebhookReason(
                code: new WebhookReasonCode('robokassa_signature_mismatch'),
                message: 'Robokassa callback signature does not match the request parameters.',
            ));
        }

        return WebhookValidationResult::success();
    }

    /**
     * @param array<string, mixed> $callbackParams
     */
    private function calculateSignature(array $callbackParams): string
    {
        $signatureParts = [
            trim($callbackParams['OutSum']),
            trim($callbackParams['InvId']),
            $this->password2,
        ];

        foreach ($this->customParameterSignatureParts($callbackParams) as $signaturePart) {
            $signatureParts[] = $signaturePart;
        }

        return md5(implode(':', $signatureParts));
    }

    /**
     * @param array<string, mixed> $callbackParams
     *
     * @return list<string>
     */
    private function customParameterSignatureParts(array $callbackParams): array
    {
        $customParameters = [];

        foreach ($callbackParams as $parameterName => $parameterValue) {
            if (!WebhookRobokassaCallbackFormat::isCustomParameter($parameterName)) {
                continue;
            }

            if (!is_string($parameterValue)) {
                continue;
            }

            $customParameters[$parameterName] = $parameterValue;
        }

        ksort($customParameters, SORT_STRING);

        $signatureParts = [];

        foreach ($customParameters as $parameterName => $parameterValue) {
            $signatureParts[] = $parameterName . '=' . trim($parameterValue);
        }

        return $signatureParts;
    }
}
