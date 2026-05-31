<?php

declare(strict_types=1);

namespace Yiisoft\Payments\Tests\Webhooks;

use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use Yiisoft\Payments\Webhooks\WebhookEventType;
use Yiisoft\Payments\Webhooks\WebhookInput;
use Yiisoft\Payments\Webhooks\WebhookPayload;
use Yiisoft\Payments\Webhooks\WebhookPayloadParserInterface;
use Yiisoft\Payments\Webhooks\WebhookRawData;

final class WebhookPayloadParserInterfaceTest extends TestCase
{
    public function testParsePayloadAcceptsWebhookInputEventTypeAndOptionalProviderEventType(): void
    {
        $method = new ReflectionMethod(WebhookPayloadParserInterface::class, 'parsePayload');
        $parameters = $method->getParameters();
        $returnType = $method->getReturnType();

        $this->assertCount(3, $parameters);
        $this->assertSame('input', $parameters[0]->getName());
        $this->assertFalse($parameters[0]->allowsNull());
        $this->assertSame(WebhookInput::class, $parameters[0]->getType()?->getName());
        $this->assertSame('eventType', $parameters[1]->getName());
        $this->assertFalse($parameters[1]->allowsNull());
        $this->assertSame(WebhookEventType::class, $parameters[1]->getType()?->getName());
        $this->assertSame('providerEventType', $parameters[2]->getName());
        $this->assertTrue($parameters[2]->allowsNull());
        $this->assertSame('string', $parameters[2]->getType()?->getName());
        $this->assertTrue($parameters[2]->isDefaultValueAvailable());
        $this->assertNull($parameters[2]->getDefaultValue());
        $this->assertNotNull($returnType);
        $this->assertFalse($returnType->allowsNull());
        $this->assertSame(WebhookPayload::class, $returnType->getName());
    }

    public function testParserCanReturnIntermediateWebhookPayload(): void
    {
        $parser = new class implements WebhookPayloadParserInterface {
            public function parsePayload(
                WebhookInput $input,
                WebhookEventType $eventType,
                ?string $providerEventType = null,
            ): WebhookPayload {
                $data = json_decode($input->rawBody, true);
                $data = is_array($data) ? $data : [];

                return new WebhookPayload(
                    providerId: $input->providerId,
                    eventType: $eventType,
                    providerEventType: $providerEventType,
                    data: $data,
                    paymentStatus: isset($data['status']) && is_string($data['status']) ? $data['status'] : null,
                    rawData: new WebhookRawData(
                        rawBody: $input->rawBody,
                        headers: $input->headers,
                        payload: $data,
                        providerEventType: $providerEventType,
                        queryParams: $input->queryParams,
                        bodyParams: $input->bodyParams,
                    ),
                );
            }
        };

        $input = new WebhookInput(
            rawBody: '{"id":"evt_123","status":"succeeded"}',
            headers: ['Stripe-Signature' => 't=123,v1=abc'],
            providerId: 'stripe',
        );

        $payload = $parser->parsePayload(
            $input,
            WebhookEventType::PaymentSucceeded,
            'payment_intent.succeeded',
        );

        $this->assertSame('stripe', $payload->providerId);
        $this->assertSame(WebhookEventType::PaymentSucceeded, $payload->eventType);
        $this->assertSame('payment_intent.succeeded', $payload->providerEventType);
        $this->assertSame(['id' => 'evt_123', 'status' => 'succeeded'], $payload->data);
        $this->assertSame('succeeded', $payload->paymentStatus);
        $this->assertInstanceOf(WebhookRawData::class, $payload->rawData);
        $this->assertSame($input->rawBody, $payload->rawData->rawBody);
        $this->assertSame($input->headers, $payload->rawData->headers);
    }

    public function testParserCanPreserveRawDataForMalformedPayload(): void
    {
        $parser = new class implements WebhookPayloadParserInterface {
            public function parsePayload(
                WebhookInput $input,
                WebhookEventType $eventType,
                ?string $providerEventType = null,
            ): WebhookPayload {
                $data = json_decode($input->rawBody, true);
                $data = is_array($data) ? $data : [];

                return new WebhookPayload(
                    providerId: $input->providerId,
                    eventType: $eventType,
                    providerEventType: $providerEventType,
                    data: $data,
                    rawData: new WebhookRawData(
                        rawBody: $input->rawBody,
                        headers: $input->headers,
                        payload: $data,
                        providerEventType: $providerEventType,
                        queryParams: $input->queryParams,
                        bodyParams: $input->bodyParams,
                    ),
                );
            }
        };

        $input = new WebhookInput(
            rawBody: '{malformed-json',
            headers: ['Content-Type' => 'application/json'],
            providerId: 'stripe',
        );

        $payload = $parser->parsePayload(
            $input,
            WebhookEventType::PaymentSucceeded,
            'payment_intent.succeeded',
        );

        $this->assertSame([], $payload->data);
        $this->assertInstanceOf(WebhookRawData::class, $payload->rawData);
        $this->assertSame('{malformed-json', $payload->rawData->rawBody);
        $this->assertSame(['Content-Type' => 'application/json'], $payload->rawData->headers);
        $this->assertSame('payment_intent.succeeded', $payload->rawData->providerEventType);
    }
}
