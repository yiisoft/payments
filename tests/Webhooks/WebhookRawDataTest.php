<?php

declare(strict_types=1);

namespace Yiisoft\Payments\Tests\Webhooks;

use PHPUnit\Framework\TestCase;
use Yiisoft\Payments\Webhooks\WebhookRawData;

final class WebhookRawDataTest extends TestCase
{
    public function testRawBodyIsPreservedWithoutChanges(): void
    {
        $rawBody = "  {\n    \"event\": \"payment.succeeded\",\n    \"amount\": 1000\n  }\n";

        $rawData = new WebhookRawData(rawBody: $rawBody);

        $this->assertSame($rawBody, $rawData->rawBody);
    }

    public function testRawBodyPreservesBinarySafeContent(): void
    {
        $rawBody = "prefix\0middle\r\n suffix ";

        $rawData = new WebhookRawData(rawBody: $rawBody);

        $this->assertSame($rawBody, $rawData->rawBody);
    }

    public function testRawHeadersArePreservedWithoutChanges(): void
    {
        $headers = [
            'Stripe-Signature' => 't=123,v1=abc',
            'X-Custom-Header' => ['first', 'second'],
            'x-provider-event' => 'payment.succeeded',
        ];

        $rawData = new WebhookRawData(rawBody: '{}', headers: $headers);

        $this->assertSame($headers, $rawData->headers);
        $this->assertSame($headers, $rawData->getHeaders());
    }

    public function testRawHeadersPreserveNameCasingAndValues(): void
    {
        $headers = [
            'Content-Type' => 'application/json',
            'content-type' => 'application/x-www-form-urlencoded',
            'X-Multi-Value' => [' value with spaces ', 'second-value'],
        ];

        $rawData = new WebhookRawData(rawBody: '{}', headers: $headers);

        $this->assertSame(
            ['Content-Type', 'content-type', 'X-Multi-Value'],
            array_keys($rawData->getHeaders()),
        );
        $this->assertSame('application/json', $rawData->getHeaders()['Content-Type']);
        $this->assertSame('application/x-www-form-urlencoded', $rawData->getHeaders()['content-type']);
        $this->assertSame([' value with spaces ', 'second-value'], $rawData->getHeaders()['X-Multi-Value']);
    }
}
