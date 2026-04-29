<?php

declare(strict_types=1);

namespace Yiisoft\Payments\Tests\Webhooks;

use PHPUnit\Framework\TestCase;
use Yiisoft\Payments\Webhooks\WebhookInput;
use Yiisoft\Payments\Webhooks\WebhookProviderValidatorInterface;
use Yiisoft\Payments\Webhooks\WebhookYooKassaValidator;

final class WebhookYooKassaValidatorTest extends TestCase
{
    public function testImplementsProviderValidatorContract(): void
    {
        $validator = new WebhookYooKassaValidator();

        $this->assertInstanceOf(WebhookProviderValidatorInterface::class, $validator);
        $this->assertSame('yookassa', $validator->getProviderId());
    }

    public function testReturnsFailClosedSkeletonValidationResult(): void
    {
        $result = (new WebhookYooKassaValidator())->validate(new WebhookInput(
            rawBody: '{"type":"notification","event":"payment.succeeded","object":{"id":"payment-id"}}',
            headers: [],
            providerId: 'yookassa',
        ));

        $this->assertFalse($result->isValid);
        $this->assertNotNull($result->reason);
        $this->assertSame('yookassa_webhook_validation_not_implemented', $result->reason->code->value);
        $this->assertSame('YooKassa webhook validation is not implemented yet.', $result->reason->message);
        $this->assertNull($result->reason->providerEventType);
    }
}
