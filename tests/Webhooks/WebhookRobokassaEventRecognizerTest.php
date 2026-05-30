<?php

declare(strict_types=1);

namespace Yiisoft\Payments\Tests\Webhooks;

use PHPUnit\Framework\TestCase;
use Yiisoft\Payments\Webhooks\WebhookEventRecognizerInterface;
use Yiisoft\Payments\Webhooks\WebhookInput;
use Yiisoft\Payments\Webhooks\WebhookRobokassaEventRecognizer;

final class WebhookRobokassaEventRecognizerTest extends TestCase
{
    public function testImplementsEventRecognizerContract(): void
    {
        $recognizer = new WebhookRobokassaEventRecognizer();

        $this->assertInstanceOf(WebhookEventRecognizerInterface::class, $recognizer);
    }

    public function testDoesNotRecognizeProviderEventTypeYet(): void
    {
        $recognizer = new WebhookRobokassaEventRecognizer();

        $this->assertNull(
            $recognizer->recognizeProviderEventType(new WebhookInput(queryParams: ['OutSum' => '100.00'])),
        );
    }

    public function testDoesNotNormalizeEventTypeYet(): void
    {
        $recognizer = new WebhookRobokassaEventRecognizer();

        $this->assertNull($recognizer->recognizeEventType('result_url'));
    }
}
