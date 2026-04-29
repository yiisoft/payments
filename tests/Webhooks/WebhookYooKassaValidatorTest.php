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

    public function testReturnsFailClosedWhenAuthenticityIndicatorsAreNotAvailable(): void
    {
        $result = (new WebhookYooKassaValidator())->validate(new WebhookInput(
            rawBody: '{"type":"notification","event":"payment.succeeded","object":{"id":"payment-id"}}',
            headers: [],
            providerId: 'yookassa',
        ));

        $this->assertFalse($result->isValid);
        $this->assertNotNull($result->reason);
        $this->assertSame('yookassa_authenticity_indicators_not_available', $result->reason->code->value);
        $this->assertSame(
            'YooKassa webhook validation cannot be completed because the current API/config does not expose a webhook-specific authenticity indicator.',
            $result->reason->message,
        );
        $this->assertNull($result->reason->providerEventType);
    }

    public function testCurrentApiAndConfigDoNotExposeWebhookSpecificAuthenticityIndicator(): void
    {
        $result = (new WebhookYooKassaValidator())->validate(new WebhookInput(
            rawBody: '{"event":"payment.succeeded","object":{"id":"payment-id"}}',
            headers: [
                'Content-Type' => ['application/json'],
                'User-Agent' => ['YooKassa'],
            ],
            providerId: 'yookassa',
        ));

        $this->assertFalse($result->isValid);
        $this->assertNotNull($result->reason);
        $this->assertSame('yookassa_authenticity_indicators_not_available', $result->reason->code->value);
        $this->assertNull($result->reason->providerEventType);
    }
}
