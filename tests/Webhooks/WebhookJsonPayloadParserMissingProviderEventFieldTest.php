<?php

declare(strict_types=1);

namespace Yiisoft\Payments\Tests\Webhooks;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Yiisoft\Payments\Webhooks\WebhookEventType;
use Yiisoft\Payments\Webhooks\WebhookInput;
use Yiisoft\Payments\Webhooks\WebhookPayloadParserInterface;
use Yiisoft\Payments\Webhooks\WebhookPayPalPayloadParser;
use Yiisoft\Payments\Webhooks\WebhookRawData;
use Yiisoft\Payments\Webhooks\WebhookStripePayloadParser;
use Yiisoft\Payments\Webhooks\WebhookYooKassaPayloadParser;

final class WebhookJsonPayloadParserMissingProviderEventFieldTest extends TestCase
{
    /**
     * @param array<string, mixed> $expectedPayload
     */
    #[DataProvider('jsonPayloadWithoutProviderEventFieldProvider')]
    public function testMissingRequiredProviderEventFieldDoesNotFailAndPreservesDecodedPayload(
        WebhookPayloadParserInterface $parser,
        string $providerId,
        string $rawBody,
        array $expectedPayload,
        ?string $expectedPaymentStatus,
    ): void {
        $input = new WebhookInput(
            rawBody: $rawBody,
            headers: ['Content-Type' => 'application/json'],
            providerId: $providerId,
        );

        $payload = $parser->parsePayload(
            $input,
            WebhookEventType::PaymentSucceeded,
        );

        $this->assertSame($providerId, $payload->providerId);
        $this->assertSame(WebhookEventType::PaymentSucceeded, $payload->eventType);
        $this->assertNull($payload->providerEventType);
        $this->assertSame($expectedPayload, $payload->data);
        $this->assertSame($expectedPaymentStatus, $payload->paymentStatus);
        $this->assertInstanceOf(WebhookRawData::class, $payload->rawData);
        $this->assertSame($rawBody, $payload->rawData->rawBody);
        $this->assertSame(['Content-Type' => 'application/json'], $payload->rawData->headers);
        $this->assertSame($expectedPayload, $payload->rawData->payload);
        $this->assertNull($payload->rawData->providerEventType);
    }

    /**
     * @return iterable<string, array{WebhookPayloadParserInterface, string, string, array<string, mixed>, string}>
     */
    public static function jsonPayloadWithoutProviderEventFieldProvider(): iterable
    {
        yield 'stripe missing type' => [
            new WebhookStripePayloadParser(),
            'stripe',
            '{"id":"evt_123","data":{"object":{"id":"pi_123","status":"succeeded"}}}',
            ['id' => 'evt_123', 'data' => ['object' => ['id' => 'pi_123', 'status' => 'succeeded']]],
            'succeeded',
        ];
        yield 'paypal missing event_type' => [
            new WebhookPayPalPayloadParser(),
            'paypal',
            '{"id":"WH-123","resource":{"id":"CAPTURE-123","status":"COMPLETED"}}',
            ['id' => 'WH-123', 'resource' => ['id' => 'CAPTURE-123', 'status' => 'COMPLETED']],
            'COMPLETED',
        ];
        yield 'yookassa missing event' => [
            new WebhookYooKassaPayloadParser(),
            'yookassa',
            '{"type":"notification","object":{"id":"payment_123","status":"succeeded"}}',
            ['type' => 'notification', 'object' => ['id' => 'payment_123', 'status' => 'succeeded']],
            'succeeded',
        ];
    }
}
