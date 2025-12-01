<?php

declare(strict_types=1);

namespace Yiisoft\Payments\Tests\Config;

/**
 * Returns config array compatible with RobokassaGateway.
 */
final class RobokassaConfigArray
{
    /**
     * @return array<string,mixed>
     */
    public static function asArray(): array
    {
        return [
            'merchant_login' => RobokassaSandbox::MERCHANT_LOGIN,
            'password1' => RobokassaSandbox::PASSWORD1,
            'password2' => RobokassaSandbox::PASSWORD2,
            'is_test' => true,
            'payment_url' => RobokassaSandbox::PAYMENT_URL,
            'result_url' => RobokassaSandbox::RESULT_URL,
            'success_url' => RobokassaSandbox::SUCCESS_URL,
            'fail_url' => RobokassaSandbox::FAIL_URL,
        ];
    }
}
