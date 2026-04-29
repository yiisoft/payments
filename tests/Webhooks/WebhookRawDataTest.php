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
}
