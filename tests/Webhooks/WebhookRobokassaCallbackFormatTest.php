<?php

declare(strict_types=1);

namespace Yiisoft\Payments\Tests\Webhooks;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Yiisoft\Payments\Webhooks\WebhookEventType;
use Yiisoft\Payments\Webhooks\WebhookRobokassaCallbackFormat;

final class WebhookRobokassaCallbackFormatTest extends TestCase
{
    public function testDefinesSupportedR1CallbackFormat(): void
    {
        $this->assertSame('robokassa', WebhookRobokassaCallbackFormat::PROVIDER_ID);
        $this->assertSame('result_url', WebhookRobokassaCallbackFormat::CALLBACK_TYPE);
        $this->assertSame('SignatureValue', WebhookRobokassaCallbackFormat::SIGNATURE_PARAMETER);
        $this->assertSame('password2', WebhookRobokassaCallbackFormat::SIGNATURE_SECRET);
        $this->assertSame('md5', WebhookRobokassaCallbackFormat::SIGNATURE_ALGORITHM);
        $this->assertSame('Shp_', WebhookRobokassaCallbackFormat::CUSTOM_PARAMETER_PREFIX);
    }

    public function testDefinesRequiredResultUrlParameters(): void
    {
        $this->assertSame(
            ['OutSum', 'InvId', 'SignatureValue'],
            WebhookRobokassaCallbackFormat::requiredParameters(),
        );

        $this->assertTrue(WebhookRobokassaCallbackFormat::isRequiredParameter('OutSum'));
        $this->assertTrue(WebhookRobokassaCallbackFormat::isRequiredParameter('InvId'));
        $this->assertTrue(WebhookRobokassaCallbackFormat::isRequiredParameter('SignatureValue'));
        $this->assertFalse(WebhookRobokassaCallbackFormat::isRequiredParameter('Shp_order'));
        $this->assertFalse(WebhookRobokassaCallbackFormat::isRequiredParameter('Culture'));
    }

    public function testDefinesOnlyPaymentSucceededAsSupportedR1PaymentOutcome(): void
    {
        $this->assertSame(
            WebhookEventType::PaymentSucceeded,
            WebhookRobokassaCallbackFormat::supportedR1PaymentOutcome(),
        );
    }

    #[DataProvider('unsupportedR1PaymentOutcomeProvider')]
    public function testDoesNotSupportNonSuccessR1PaymentOutcomes(WebhookEventType $eventType): void
    {
        $this->assertFalse(WebhookRobokassaCallbackFormat::supportsR1PaymentOutcome($eventType));
    }

    /**
     * @return iterable<string, array{WebhookEventType}>
     */
    public static function unsupportedR1PaymentOutcomeProvider(): iterable
    {
        yield 'created' => [WebhookEventType::PaymentCreated];
        yield 'processing' => [WebhookEventType::PaymentProcessing];
        yield 'requires action' => [WebhookEventType::PaymentRequiresAction];
        yield 'requires capture' => [WebhookEventType::PaymentRequiresCapture];
        yield 'failed' => [WebhookEventType::PaymentFailed];
        yield 'canceled' => [WebhookEventType::PaymentCanceled];
    }

    public function testDefinesOptionalCustomParameterPrefix(): void
    {
        $this->assertTrue(WebhookRobokassaCallbackFormat::isCustomParameter('Shp_order'));
        $this->assertTrue(WebhookRobokassaCallbackFormat::isCustomParameter('Shp_user_id'));
        $this->assertFalse(WebhookRobokassaCallbackFormat::isCustomParameter('shp_order'));
        $this->assertFalse(WebhookRobokassaCallbackFormat::isCustomParameter('OutSum'));
        $this->assertFalse(WebhookRobokassaCallbackFormat::isCustomParameter('SignatureValue'));
    }
}
