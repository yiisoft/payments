<?php

declare(strict_types=1);

namespace Yiisoft\Payments\Tests\Webhooks;

use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Yiisoft\Payments\Webhooks\WebhookInput;
use Yiisoft\Payments\Webhooks\WebhookPayPalSignatureVerifierInterface;
use Yiisoft\Payments\Webhooks\WebhookPayPalValidator;
use Yiisoft\Payments\Webhooks\WebhookValidationResult;

final class WebhookPayPalValidationCasesTest extends TestCase
{
    #[DataProvider('validStructuralInputProvider')]
    public function testAcceptsValidStructuralCases(array $headers): void
    {
        $result = $this->validator()->validate(new WebhookInput(
            rawBody: '{"id":"WH-EVENT-123","event_type":"PAYMENT.CAPTURE.COMPLETED","resource":{"id":"CAPTURE-123"}}',
            headers: $headers,
            providerId: 'paypal',
        ));

        $this->assertTrue($result->isValid);
        $this->assertNull($result->reason);
    }

    /**
     * @return iterable<string, array{array<string, string|list<string>>}>
     */
    public static function validStructuralInputProvider(): iterable
    {
        yield 'standard required transmission headers' => [
            self::requiredTransmissionHeaders(),
        ];

        yield 'case-insensitive required transmission headers' => [
            [
                'paypal-transmission-id' => 'transmission-id',
                'paypal-transmission-time' => '2026-04-29T10:00:00Z',
                'paypal-cert-url' => 'https://api-m.paypal.com/certs/test.pem',
                'paypal-auth-algo' => 'SHA256withRSA',
                'paypal-transmission-sig' => 'signature',
            ],
        ];

        yield 'multi-value required transmission headers with one non-empty value' => [
            [
                'PayPal-Transmission-Id' => ['', 'transmission-id'],
                'PayPal-Transmission-Time' => ['', '2026-04-29T10:00:00Z'],
                'PayPal-Cert-Url' => ['', 'https://api-m.paypal.com/certs/test.pem'],
                'PayPal-Auth-Algo' => ['', 'SHA256withRSA'],
                'PayPal-Transmission-Sig' => ['', 'signature'],
            ],
        ];

        yield 'required transmission headers with surrounding whitespace' => [
            [
                'PayPal-Transmission-Id' => '  transmission-id  ',
                'PayPal-Transmission-Time' => '  2026-04-29T10:00:00Z  ',
                'PayPal-Cert-Url' => '  https://api-m.paypal.com/certs/test.pem  ',
                'PayPal-Auth-Algo' => '  SHA256withRSA  ',
                'PayPal-Transmission-Sig' => '  signature  ',
            ],
        ];
    }

    #[DataProvider('invalidHeaderInputProvider')]
    public function testRejectsInvalidHeaderCases(
        array $headers,
        string $expectedReasonMessage,
    ): void {
        $result = $this->validator()->validate(new WebhookInput(
            rawBody: '{"id":"WH-EVENT-123","event_type":"PAYMENT.CAPTURE.COMPLETED"}',
            headers: $headers,
            providerId: 'paypal',
        ));

        $this->assertFalse($result->isValid);
        $this->assertNotNull($result->reason);
        $this->assertSame('paypal_required_transmission_header_missing', $result->reason->code->value);
        $this->assertSame($expectedReasonMessage, $result->reason->message);
        $this->assertNull($result->reason->providerEventType);
    }

    /**
     * @return iterable<string, array{array<string, string|list<string>>, string}>
     */
    public static function invalidHeaderInputProvider(): iterable
    {
        $headers = self::requiredTransmissionHeaders();
        unset($headers['PayPal-Transmission-Id']);

        yield 'missing PayPal-Transmission-Id' => [
            $headers,
            'Required PayPal transmission header "PayPal-Transmission-Id" is missing or empty.',
        ];

        $headers = self::requiredTransmissionHeaders();
        unset($headers['PayPal-Transmission-Time']);

        yield 'missing PayPal-Transmission-Time' => [
            $headers,
            'Required PayPal transmission header "PayPal-Transmission-Time" is missing or empty.',
        ];

        $headers = self::requiredTransmissionHeaders();
        unset($headers['PayPal-Cert-Url']);

        yield 'missing PayPal-Cert-Url' => [
            $headers,
            'Required PayPal transmission header "PayPal-Cert-Url" is missing or empty.',
        ];

        $headers = self::requiredTransmissionHeaders();
        unset($headers['PayPal-Auth-Algo']);

        yield 'missing PayPal-Auth-Algo' => [
            $headers,
            'Required PayPal transmission header "PayPal-Auth-Algo" is missing or empty.',
        ];

        $headers = self::requiredTransmissionHeaders();
        unset($headers['PayPal-Transmission-Sig']);

        yield 'missing PayPal-Transmission-Sig' => [
            $headers,
            'Required PayPal transmission header "PayPal-Transmission-Sig" is missing or empty.',
        ];

        yield 'empty PayPal-Transmission-Id' => [
            self::requiredTransmissionHeaders(['PayPal-Transmission-Id' => '   ']),
            'Required PayPal transmission header "PayPal-Transmission-Id" is missing or empty.',
        ];

        yield 'multi-value PayPal-Transmission-Sig with empty values only' => [
            self::requiredTransmissionHeaders(['PayPal-Transmission-Sig' => ['', " \t"]]),
            'Required PayPal transmission header "PayPal-Transmission-Sig" is missing or empty.',
        ];
    }

    public function testRejectsInvalidWebhookIdConfig(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('PayPal webhook ID must be a non-empty string.');

        new WebhookPayPalValidator(self::successfulVerifier(), " \t\n ");
    }

    private function validator(): WebhookPayPalValidator
    {
        return new WebhookPayPalValidator(self::successfulVerifier(), 'WH-123');
    }

    private static function successfulVerifier(): WebhookPayPalSignatureVerifierInterface
    {
        return new class implements WebhookPayPalSignatureVerifierInterface {
            public function verify(WebhookInput $input, string $webhookId): WebhookValidationResult
            {
                return WebhookValidationResult::success();
            }
        };
    }

    /**
     * @param array<string, string|list<string>> $overrides
     * @return array<string, string|list<string>>
     */
    private static function requiredTransmissionHeaders(array $overrides = []): array
    {
        return array_replace([
            'PayPal-Transmission-Id' => 'transmission-id',
            'PayPal-Transmission-Time' => '2026-04-29T10:00:00Z',
            'PayPal-Cert-Url' => 'https://api-m.paypal.com/certs/test.pem',
            'PayPal-Auth-Algo' => 'SHA256withRSA',
            'PayPal-Transmission-Sig' => 'signature',
        ], $overrides);
    }
}
