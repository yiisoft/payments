<?php

declare(strict_types=1);

namespace Yiisoft\Payments\Webhooks;

/**
 * Supported Robokassa callback format for Webhooks R1.
 *
 * R1 supports the classic Robokassa ResultURL payment callback. Robokassa sends callback fields using
 * its own parameter names, such as OutSum, InvId, SignatureValue, and Shp_* custom fields. Applications
 * must pass those fields as received from Robokassa: query callbacks go to WebhookInput::queryParams,
 * and form-body callbacks go to WebhookInput::bodyParams. The keys must not be renamed or normalized
 * to application-specific field names before passing the input to the library.
 *
 * Full parameter presence and signature validation are implemented by the Robokassa validator tasks.
 */
final class WebhookRobokassaCallbackFormat
{
    public const PROVIDER_ID = 'robokassa';
    public const CALLBACK_TYPE = 'result_url';
    public const SIGNATURE_PARAMETER = 'SignatureValue';
    public const SIGNATURE_SECRET = 'password2';
    public const SIGNATURE_ALGORITHM = 'md5';
    public const CUSTOM_PARAMETER_PREFIX = 'Shp_';

    /**
     * @var list<string>
     */
    private const REQUIRED_PARAMETERS = [
        'OutSum',
        'InvId',
        self::SIGNATURE_PARAMETER,
    ];

    private function __construct()
    {
    }

    /**
     * @return list<string>
     */
    public static function requiredParameters(): array
    {
        return self::REQUIRED_PARAMETERS;
    }

    public static function isRequiredParameter(string $name): bool
    {
        return in_array($name, self::REQUIRED_PARAMETERS, true);
    }

    public static function isCustomParameter(string $name): bool
    {
        return str_starts_with($name, self::CUSTOM_PARAMETER_PREFIX);
    }
}
