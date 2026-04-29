<?php

declare(strict_types=1);

namespace Yiisoft\Payments\Tests\Webhooks;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Yiisoft\Payments\Webhooks\StripeWebhookValidator;
use Yiisoft\Payments\Webhooks\WebhookInput;
use Yiisoft\Payments\Webhooks\WebhookProviderValidatorInterface;

final class StripeWebhookValidatorTest extends TestCase
{
    public function testImplementsProviderValidatorContract(): void
    {
        $validator = new StripeWebhookValidator('whsec_test_secret');

        $this->assertInstanceOf(WebhookProviderValidatorInterface::class, $validator);
        $this->assertSame('stripe', $validator->getProviderId());
    }

    public function testSigningSecretMustBeNonEmpty(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Stripe webhook signing secret must be a non-empty string.');

        new StripeWebhookValidator('   ');
    }

    public function testSkeletonFailsClosedUntilSignatureValidationIsImplemented(): void
    {
        $validator = new StripeWebhookValidator('whsec_test_secret');

        $result = $validator->validate(new WebhookInput(
            rawBody: '{"id":"evt_123","type":"payment_intent.succeeded"}',
            headers: [
                'Stripe-Signature' => 't=1700000000,v1=test_signature',
            ],
            providerId: 'stripe',
        ));

        $this->assertFalse($result->isValid);
        $this->assertNotNull($result->reason);
        $this->assertSame('stripe_signature_validation_not_implemented', $result->reason->code->value);
        $this->assertSame('Stripe webhook signature validation is not implemented yet.', $result->reason->message);
        $this->assertNull($result->reason->providerEventType);
    }
}
