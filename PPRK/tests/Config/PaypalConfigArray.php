<?php

declare(strict_types=1);

namespace Yiisoft\Payments\Tests\Config;

/**
 * Returns config array compatible with PayPalGateway.
 */
final class PaypalConfigArray
{
    /**
     * @return array<string,mixed>
     */
    public static function asArray(): array
    {
        return [
            'client_id' => PaypalSandbox::CLIENT_ID,
            'client_secret' => PaypalSandbox::CLIENT_SECRET,
            'sandbox' => true,
            'sandbox_url' => PaypalSandbox::SANDBOX_URL,
            'live_url' => PaypalSandbox::LIVE_URL,
            'return_url' => PaypalSandbox::RETURN_URL,
            'cancel_url' => PaypalSandbox::CANCEL_URL,
        ];
    }
}
