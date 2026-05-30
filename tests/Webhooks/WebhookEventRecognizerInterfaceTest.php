<?php

declare(strict_types=1);

namespace Yiisoft\Payments\Tests\Webhooks;

use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use Yiisoft\Payments\Webhooks\WebhookEventRecognizerInterface;
use Yiisoft\Payments\Webhooks\WebhookEventType;
use Yiisoft\Payments\Webhooks\WebhookInput;

final class WebhookEventRecognizerInterfaceTest extends TestCase
{
    public function testRecognizeProviderEventTypeAcceptsWebhookInputAndReturnsNullableString(): void
    {
        $method = new ReflectionMethod(WebhookEventRecognizerInterface::class, 'recognizeProviderEventType');
        $parameters = $method->getParameters();
        $returnType = $method->getReturnType();

        $this->assertCount(1, $parameters);
        $this->assertSame('input', $parameters[0]->getName());
        $this->assertFalse($parameters[0]->allowsNull());
        $this->assertSame(WebhookInput::class, $parameters[0]->getType()?->getName());
        $this->assertNotNull($returnType);
        $this->assertTrue($returnType->allowsNull());
        $this->assertSame('string', $returnType->getName());
    }

    public function testRecognizeEventTypeAcceptsProviderEventTypeAndReturnsNullableWebhookEventType(): void
    {
        $method = new ReflectionMethod(WebhookEventRecognizerInterface::class, 'recognizeEventType');
        $parameters = $method->getParameters();
        $returnType = $method->getReturnType();

        $this->assertCount(1, $parameters);
        $this->assertSame('providerEventType', $parameters[0]->getName());
        $this->assertFalse($parameters[0]->allowsNull());
        $this->assertSame('string', $parameters[0]->getType()?->getName());
        $this->assertNotNull($returnType);
        $this->assertTrue($returnType->allowsNull());
        $this->assertSame(WebhookEventType::class, $returnType->getName());
    }

    public function testRecognizerCanReturnRawProviderEventTypeWithoutNormalizingIt(): void
    {
        $recognizer = new class implements WebhookEventRecognizerInterface {
            public function recognizeProviderEventType(WebhookInput $input): ?string
            {
                $payload = json_decode($input->rawBody, true);

                return is_array($payload) && isset($payload['type']) && is_string($payload['type'])
                    ? $payload['type']
                    : null;
            }

            public function recognizeEventType(string $providerEventType): ?WebhookEventType
            {
                return $providerEventType === 'payment_intent.succeeded'
                    ? WebhookEventType::PaymentSucceeded
                    : null;
            }
        };

        $input = new WebhookInput(rawBody: '{"type":"payment_intent.succeeded"}', providerId: 'stripe');

        $this->assertSame('payment_intent.succeeded', $recognizer->recognizeProviderEventType($input));
    }

    public function testRecognizerCanReturnNullWhenProviderEventTypeIsMissing(): void
    {
        $recognizer = new class implements WebhookEventRecognizerInterface {
            public function recognizeProviderEventType(WebhookInput $input): ?string
            {
                $payload = json_decode($input->rawBody, true);

                return is_array($payload) && isset($payload['type']) && is_string($payload['type'])
                    ? $payload['type']
                    : null;
            }

            public function recognizeEventType(string $providerEventType): ?WebhookEventType
            {
                return null;
            }
        };

        $input = new WebhookInput(rawBody: '{"id":"evt_without_type"}', providerId: 'stripe');

        $this->assertNull($recognizer->recognizeProviderEventType($input));
    }

    public function testRecognizerCanReturnNormalizedWebhookEventTypeForSupportedPaymentEvent(): void
    {
        $recognizer = new class implements WebhookEventRecognizerInterface {
            public function recognizeProviderEventType(WebhookInput $input): ?string
            {
                return null;
            }

            public function recognizeEventType(string $providerEventType): ?WebhookEventType
            {
                return match ($providerEventType) {
                    'payment_intent.succeeded' => WebhookEventType::PaymentSucceeded,
                    default => null,
                };
            }
        };

        $this->assertSame(
            WebhookEventType::PaymentSucceeded,
            $recognizer->recognizeEventType('payment_intent.succeeded')
        );
    }

    public function testRecognizerCanReturnNullForUnknownProviderEventType(): void
    {
        $recognizer = new class implements WebhookEventRecognizerInterface {
            public function recognizeProviderEventType(WebhookInput $input): ?string
            {
                return null;
            }

            public function recognizeEventType(string $providerEventType): ?WebhookEventType
            {
                return null;
            }
        };

        $this->assertNull($recognizer->recognizeEventType('provider.event.not_in_mapping'));
    }
}
