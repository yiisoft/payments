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
            rawBody: '{"event":"payment.succeeded","object":{"id":"payment-id"}}',
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
        $this->assertSame('payment.succeeded', $result->reason->providerEventType);
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
        $this->assertSame('payment.succeeded', $result->reason->providerEventType);
    }

    public function testRejectsEmptyPayload(): void
    {
        $result = (new WebhookYooKassaValidator())->validate(new WebhookInput(
            rawBody: '   ',
            headers: ['Content-Type' => ['application/json']],
            providerId: 'yookassa',
        ));

        $this->assertFalse($result->isValid);
        $this->assertNotNull($result->reason);
        $this->assertSame('yookassa_payload_empty', $result->reason->code->value);
        $this->assertNull($result->reason->providerEventType);
    }

    public function testRejectsMalformedJsonPayload(): void
    {
        $result = (new WebhookYooKassaValidator())->validate(new WebhookInput(
            rawBody: '{"event":"payment.succeeded",',
            headers: ['Content-Type' => ['application/json']],
            providerId: 'yookassa',
        ));

        $this->assertFalse($result->isValid);
        $this->assertNotNull($result->reason);
        $this->assertSame('yookassa_payload_malformed_json', $result->reason->code->value);
        $this->assertNull($result->reason->providerEventType);
    }

    public function testRejectsNonObjectJsonPayload(): void
    {
        $result = (new WebhookYooKassaValidator())->validate(new WebhookInput(
            rawBody: '"payment.succeeded"',
            headers: ['Content-Type' => ['application/json']],
            providerId: 'yookassa',
        ));

        $this->assertFalse($result->isValid);
        $this->assertNotNull($result->reason);
        $this->assertSame('yookassa_payload_invalid', $result->reason->code->value);
        $this->assertNull($result->reason->providerEventType);
    }

    public function testRejectsPayloadWithoutEvent(): void
    {
        $result = (new WebhookYooKassaValidator())->validate(new WebhookInput(
            rawBody: '{"object":{"id":"payment-id"}}',
            headers: ['Content-Type' => ['application/json']],
            providerId: 'yookassa',
        ));

        $this->assertFalse($result->isValid);
        $this->assertNotNull($result->reason);
        $this->assertSame('yookassa_event_missing', $result->reason->code->value);
        $this->assertNull($result->reason->providerEventType);
    }

    public function testRejectsPayloadWithEmptyEvent(): void
    {
        $result = (new WebhookYooKassaValidator())->validate(new WebhookInput(
            rawBody: '{"event":"   ","object":{"id":"payment-id"}}',
            headers: ['Content-Type' => ['application/json']],
            providerId: 'yookassa',
        ));

        $this->assertFalse($result->isValid);
        $this->assertNotNull($result->reason);
        $this->assertSame('yookassa_event_missing', $result->reason->code->value);
        $this->assertNull($result->reason->providerEventType);
    }

    public function testRejectsPayloadWithoutObject(): void
    {
        $result = (new WebhookYooKassaValidator())->validate(new WebhookInput(
            rawBody: '{"event":"payment.succeeded"}',
            headers: ['Content-Type' => ['application/json']],
            providerId: 'yookassa',
        ));

        $this->assertFalse($result->isValid);
        $this->assertNotNull($result->reason);
        $this->assertSame('yookassa_object_missing', $result->reason->code->value);
        $this->assertSame('payment.succeeded', $result->reason->providerEventType);
    }

    public function testRejectsPayloadWithNonObjectObjectField(): void
    {
        $result = (new WebhookYooKassaValidator())->validate(new WebhookInput(
            rawBody: '{"event":"payment.succeeded","object":"payment-id"}',
            headers: ['Content-Type' => ['application/json']],
            providerId: 'yookassa',
        ));

        $this->assertFalse($result->isValid);
        $this->assertNotNull($result->reason);
        $this->assertSame('yookassa_object_missing', $result->reason->code->value);
        $this->assertSame('payment.succeeded', $result->reason->providerEventType);
    }
}
