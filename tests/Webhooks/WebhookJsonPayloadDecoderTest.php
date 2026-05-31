<?php

declare(strict_types=1);

namespace Yiisoft\Payments\Tests\Webhooks;

use PHPUnit\Framework\TestCase;
use Yiisoft\Payments\Webhooks\WebhookJsonPayloadDecoder;

final class WebhookJsonPayloadDecoderTest extends TestCase
{
    public function testDecodeReturnsDecodedJsonObject(): void
    {
        $decoder = new WebhookJsonPayloadDecoder();

        $this->assertSame(
            ['id' => 'evt_123', 'status' => 'succeeded'],
            $decoder->decode('{"id":"evt_123","status":"succeeded"}'),
        );
    }

    public function testDecodeReturnsEmptyArrayForMalformedJson(): void
    {
        $decoder = new WebhookJsonPayloadDecoder();

        $this->assertSame([], $decoder->decode('{malformed-json'));
    }

    public function testDecodeReturnsEmptyArrayForNonArrayJsonValue(): void
    {
        $decoder = new WebhookJsonPayloadDecoder();

        $this->assertSame([], $decoder->decode('"event"'));
    }
}
