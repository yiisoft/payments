<?php

declare(strict_types=1);

namespace Yiisoft\Payments\Tests\Webhooks;

use PHPUnit\Framework\TestCase;
use Yiisoft\Payments\Webhooks\WebhookEventType;
use Yiisoft\Payments\Webhooks\WebhookInput;
use Yiisoft\Payments\Webhooks\WebhookPayloadParserInterface;
use Yiisoft\Payments\Webhooks\WebhookRawData;
use Yiisoft\Payments\Webhooks\WebhookRobokassaCallbackFormat;
use Yiisoft\Payments\Webhooks\WebhookRobokassaPayloadParser;

final class WebhookRobokassaPayloadParserTest extends TestCase
{
    public function testImplementsPayloadParserInterface(): void
    {
        $this->assertInstanceOf(WebhookPayloadParserInterface::class, new WebhookRobokassaPayloadParser());
    }

    public function testParsesRobokassaCallbackPayload(): void
    {
        $parser = new WebhookRobokassaPayloadParser();
        $input = new WebhookInput(
            rawBody: '',
            headers: ['Content-Type' => 'application/x-www-form-urlencoded'],
            queryParams: [
                'OutSum' => '100.00',
                'InvId' => '123',
                'SignatureValue' => 'abc123',
            ],
            providerId: WebhookRobokassaCallbackFormat::PROVIDER_ID,
        );

        $payload = $parser->parsePayload(
            $input,
            WebhookEventType::PaymentSucceeded,
            WebhookRobokassaCallbackFormat::CALLBACK_TYPE,
        );

        $this->assertSame(WebhookRobokassaCallbackFormat::PROVIDER_ID, $payload->providerId);
        $this->assertSame(WebhookEventType::PaymentSucceeded, $payload->eventType);
        $this->assertSame(WebhookRobokassaCallbackFormat::CALLBACK_TYPE, $payload->providerEventType);
        $this->assertSame('100.00', $payload->data['OutSum']);
        $this->assertSame('123', $payload->data['InvId']);
        $this->assertSame('abc123', $payload->data['SignatureValue']);
        $this->assertNull($payload->paymentStatus);
        $this->assertInstanceOf(WebhookRawData::class, $payload->rawData);
        $this->assertSame('', $payload->rawData->rawBody);
        $this->assertSame($input->headers, $payload->rawData->headers);
        $this->assertSame($payload->data, $payload->rawData->payload);
        $this->assertSame(WebhookRobokassaCallbackFormat::CALLBACK_TYPE, $payload->rawData->providerEventType);
    }
}
