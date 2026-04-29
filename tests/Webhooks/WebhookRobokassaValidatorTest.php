<?php

declare(strict_types=1);

namespace Yiisoft\Payments\Tests\Webhooks;

use PHPUnit\Framework\TestCase;
use Yiisoft\Payments\Webhooks\WebhookInput;
use Yiisoft\Payments\Webhooks\WebhookProviderValidatorInterface;
use Yiisoft\Payments\Webhooks\WebhookRobokassaValidator;

final class WebhookRobokassaValidatorTest extends TestCase
{
    public function testImplementsProviderValidatorContract(): void
    {
        $validator = new WebhookRobokassaValidator();

        $this->assertInstanceOf(WebhookProviderValidatorInterface::class, $validator);
        $this->assertSame('robokassa', $validator->getProviderId());
    }

    public function testReturnsFailClosedUntilValidationLogicIsImplemented(): void
    {
        $result = (new WebhookRobokassaValidator())->validate(new WebhookInput(
            rawBody: '',
            queryParams: [
                'OutSum' => '100.00',
                'InvId' => '123',
                'SignatureValue' => 'signature',
            ],
            providerId: 'robokassa',
        ));

        $this->assertFalse($result->isValid);
        $this->assertNotNull($result->reason);
        $this->assertSame('robokassa_webhook_validation_not_implemented', $result->reason->code->value);
        $this->assertSame('Robokassa webhook validation is not implemented yet.', $result->reason->message);
        $this->assertNull($result->reason->providerEventType);
    }
}
