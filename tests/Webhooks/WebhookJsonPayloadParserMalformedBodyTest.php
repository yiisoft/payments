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

final class WebhookJsonPayloadParserMalformedBodyTest extends TestCase
{
    #[DataProvider('jsonPayloadParserProvider')]
    public function testInvalidJsonBodyReturnsEmptyPayloadAndPreservesRawBody(
        WebhookPayloadParserInterface $parser,
        string $providerId,
        string $providerEventType,
    ): void {
        $input = new WebhookInput(
            rawBody: '{invalid-json',
            headers: ['Content-Type' => 'application/json'],
            queryParams: ['source' => 'query-value'],
            bodyParams: ['source' => 'body-value'],
            providerId: $providerId,
        );

        $payload = $parser->parsePayload(
            $input,
            WebhookEventType::PaymentSucceeded,
            $providerEventType,
        );

        $this->assertSame($providerId, $payload->providerId);
        $this->assertSame(WebhookEventType::PaymentSucceeded, $payload->eventType);
        $this->assertSame($providerEventType, $payload->providerEventType);
        $this->assertSame([], $payload->data);
        $this->assertNull($payload->paymentStatus);
        $this->assertInstanceOf(WebhookRawData::class, $payload->rawData);
        $this->assertSame('{invalid-json', $payload->rawData->rawBody);
        $this->assertSame(['Content-Type' => 'application/json'], $payload->rawData->headers);
        $this->assertSame([], $payload->rawData->payload);
        $this->assertSame($providerEventType, $payload->rawData->providerEventType);
        $this->assertSame(['source' => 'query-value'], $payload->rawData->queryParams);
        $this->assertSame(['source' => 'body-value'], $payload->rawData->bodyParams);
    }

    #[DataProvider('jsonPayloadParserProvider')]
    public function testEmptyRequiredJsonBodyReturnsEmptyPayloadAndPreservesRawBody(
        WebhookPayloadParserInterface $parser,
        string $providerId,
        string $providerEventType,
    ): void {
        $input = new WebhookInput(
            rawBody: '',
            headers: ['Content-Type' => 'application/json'],
            providerId: $providerId,
        );

        $payload = $parser->parsePayload(
            $input,
            WebhookEventType::PaymentSucceeded,
            $providerEventType,
        );

        $this->assertSame($providerId, $payload->providerId);
        $this->assertSame(WebhookEventType::PaymentSucceeded, $payload->eventType);
        $this->assertSame($providerEventType, $payload->providerEventType);
        $this->assertSame([], $payload->data);
        $this->assertNull($payload->paymentStatus);
        $this->assertInstanceOf(WebhookRawData::class, $payload->rawData);
        $this->assertSame('', $payload->rawData->rawBody);
        $this->assertSame(['Content-Type' => 'application/json'], $payload->rawData->headers);
        $this->assertSame([], $payload->rawData->payload);
        $this->assertSame($providerEventType, $payload->rawData->providerEventType);
    }

    /**
     * @return iterable<string, array{WebhookPayloadParserInterface, string, string}>
     */
    public static function jsonPayloadParserProvider(): iterable
    {
        yield 'stripe' => [
            new WebhookStripePayloadParser(),
            'stripe',
            'payment_intent.succeeded',
        ];
        yield 'paypal' => [
            new WebhookPayPalPayloadParser(),
            'paypal',
            'PAYMENT.CAPTURE.COMPLETED',
        ];
        yield 'yookassa' => [
            new WebhookYooKassaPayloadParser(),
            'yookassa',
            'payment.succeeded',
        ];
    }
}
