<?php

declare(strict_types=1);

namespace Yiisoft\Payments\Tests\Webhooks;

use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Yiisoft\Payments\Webhooks\WebhookInput;
use Yiisoft\Payments\Webhooks\WebhookProviderValidatorInterface;
use Yiisoft\Payments\Webhooks\WebhookYooKassaValidator;

final class WebhookYooKassaValidatorTest extends TestCase
{
    public function testImplementsProviderValidatorContract(): void
    {
        $validator = self::validator();

        $this->assertInstanceOf(WebhookProviderValidatorInterface::class, $validator);
        $this->assertSame('yookassa', $validator->getProviderId());
    }

    public function testRejectsEmptyShopId(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('YooKassa shop ID must be a non-empty string.');

        new WebhookYooKassaValidator(' ', 'secret-key');
    }

    public function testRejectsEmptySecretKey(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('YooKassa secret key must be a non-empty string.');

        new WebhookYooKassaValidator('shop-id', ' ');
    }

    public function testAcceptsValidBasicAuthWebhook(): void
    {
        $result = self::validator()->validate(new WebhookInput(
            rawBody: self::payload([
                'type' => 'notification',
                'event' => 'payment.succeeded',
                'object' => [
                    'id' => 'payment-id',
                    'status' => 'succeeded',
                    'paid' => true,
                ],
            ]),
            headers: self::headers(),
            providerId: 'yookassa',
        ));

        $this->assertTrue($result->isValid);
        $this->assertNull($result->reason);
    }

    public function testAcceptsCaseInsensitiveAuthorizationHeaderNameAndScheme(): void
    {
        $result = self::validator()->validate(new WebhookInput(
            rawBody: '{"event":"payment.succeeded","object":{"id":"payment-id"}}',
            headers: ['authorization' => ['bAsIc ' . base64_encode('shop-id:secret-key')]],
            providerId: 'yookassa',
        ));

        $this->assertTrue($result->isValid);
        $this->assertNull($result->reason);
    }

    #[DataProvider('validBasicAuthHeaderProvider')]
    public function testAcceptsValidBasicAuthHeaders(
        string $shopId,
        string $secretKey,
        string $authorizationHeader,
    ): void {
        $result = (new WebhookYooKassaValidator($shopId, $secretKey))->validate(new WebhookInput(
            rawBody: '{"event":"payment.succeeded","object":{"id":"payment-id"}}',
            headers: ['Authorization' => [$authorizationHeader]],
            providerId: 'yookassa',
        ));

        $this->assertTrue($result->isValid);
        $this->assertNull($result->reason);
    }

    /**
     * @return iterable<string, array{string, string, string}>
     */
    public static function validBasicAuthHeaderProvider(): iterable
    {
        yield 'numeric YooKassa shop ID and secret key' => [
            '123456',
            'test_secret_key',
            'Basic ' . base64_encode('123456:test_secret_key'),
        ];

        yield 'secret key containing colon' => [
            '123456',
            'secret:with:colon',
            'Basic ' . base64_encode('123456:secret:with:colon'),
        ];

        yield 'authorization value with surrounding whitespace' => [
            'shop-id',
            'secret-key',
            '  Basic ' . base64_encode('shop-id:secret-key') . '  ',
        ];
    }

    /**
     * @param array<string, string|list<string>> $headers
     */
    #[DataProvider('missingAuthorizationHeaderProvider')]
    public function testRejectsMissingAuthorizationHeader(array $headers): void
    {
        $result = self::validator()->validate(new WebhookInput(
            rawBody: '{"event":"payment.succeeded","object":{"id":"payment-id"}}',
            headers: $headers,
            providerId: 'yookassa',
        ));

        $this->assertFalse($result->isValid);
        $this->assertNotNull($result->reason);
        $this->assertSame('yookassa_authorization_header_missing', $result->reason->code->value);
        $this->assertSame('YooKassa Authorization header is missing.', $result->reason->message);
        $this->assertSame('payment.succeeded', $result->reason->providerEventType);
    }

    /**
     * @return iterable<string, array{array<string, string|list<string>>}>
     */
    public static function missingAuthorizationHeaderProvider(): iterable
    {
        yield 'no headers' => [[]];
        yield 'only content type header' => [['Content-Type' => ['application/json']]];
        yield 'only unrelated webhook header' => [['X-YooKassa-Event' => ['payment.succeeded']]];
    }

    #[DataProvider('invalidAuthorizationHeaderProvider')]
    public function testRejectsInvalidAuthorizationHeader(string $authorizationHeader): void
    {
        $result = self::validator()->validate(new WebhookInput(
            rawBody: '{"event":"payment.succeeded","object":{"id":"payment-id"}}',
            headers: ['Authorization' => [$authorizationHeader]],
            providerId: 'yookassa',
        ));

        $this->assertFalse($result->isValid);
        $this->assertNotNull($result->reason);
        $this->assertSame('yookassa_basic_auth_mismatch', $result->reason->code->value);
        $this->assertSame(
            'YooKassa Basic Auth credentials do not match the configured shop ID and secret key.',
            $result->reason->message,
        );
        $this->assertSame('payment.succeeded', $result->reason->providerEventType);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidAuthorizationHeaderProvider(): iterable
    {
        yield 'unsupported scheme' => ['Bearer token'];
        yield 'empty basic credentials' => ['Basic '];
        yield 'malformed base64 credentials' => ['Basic not-base64'];
        yield 'credentials without colon' => ['Basic ' . base64_encode('shop-id')];
        yield 'empty shop id' => ['Basic ' . base64_encode(':secret-key')];
        yield 'empty secret key' => ['Basic ' . base64_encode('shop-id:')];
        yield 'wrong shop id' => ['Basic ' . base64_encode('wrong-shop:secret-key')];
        yield 'wrong secret key' => ['Basic ' . base64_encode('shop-id:wrong-secret')];
        yield 'wrong shop id and secret key' => ['Basic ' . base64_encode('wrong-shop:wrong-secret')];
        yield 'invalid credentials with leading whitespace' => ['Basic ' . base64_encode(' shop-id:secret-key')];
        yield 'invalid credentials with trailing whitespace' => ['Basic ' . base64_encode('shop-id:secret-key ')];
    }

    public function testRejectsMultipleInvalidAuthorizationHeaders(): void
    {
        $result = self::validator()->validate(new WebhookInput(
            rawBody: '{"event":"payment.succeeded","object":{"id":"payment-id"}}',
            headers: [
                'Authorization' => [
                    'Bearer token',
                    'Basic not-base64',
                    'Basic ' . base64_encode('wrong-shop:wrong-secret'),
                ],
            ],
            providerId: 'yookassa',
        ));

        $this->assertFalse($result->isValid);
        $this->assertNotNull($result->reason);
        $this->assertSame('yookassa_basic_auth_mismatch', $result->reason->code->value);
        $this->assertSame(
            'YooKassa Basic Auth credentials do not match the configured shop ID and secret key.',
            $result->reason->message,
        );
        $this->assertSame('payment.succeeded', $result->reason->providerEventType);
    }

    public function testAcceptsAnyValidAuthorizationHeaderWhenMultipleAreProvided(): void
    {
        $result = self::validator()->validate(new WebhookInput(
            rawBody: '{"event":"payment.succeeded","object":{"id":"payment-id"}}',
            headers: [
                'Authorization' => [
                    'Bearer token',
                    'Basic ' . base64_encode('shop-id:secret-key'),
                ],
            ],
            providerId: 'yookassa',
        ));

        $this->assertTrue($result->isValid);
        $this->assertNull($result->reason);
    }

    public function testRejectsEmptyPayload(): void
    {
        $result = self::validator()->validate(new WebhookInput(
            rawBody: '   ',
            headers: self::headers(),
            providerId: 'yookassa',
        ));

        $this->assertFalse($result->isValid);
        $this->assertNotNull($result->reason);
        $this->assertSame('yookassa_payload_empty', $result->reason->code->value);
        $this->assertNull($result->reason->providerEventType);
    }

    public function testRejectsMalformedJsonPayload(): void
    {
        $result = self::validator()->validate(new WebhookInput(
            rawBody: '{"event":"payment.succeeded",',
            headers: self::headers(),
            providerId: 'yookassa',
        ));

        $this->assertFalse($result->isValid);
        $this->assertNotNull($result->reason);
        $this->assertSame('yookassa_payload_malformed_json', $result->reason->code->value);
        $this->assertNull($result->reason->providerEventType);
    }

    public function testRejectsNonObjectJsonPayload(): void
    {
        $result = self::validator()->validate(new WebhookInput(
            rawBody: '"payment.succeeded"',
            headers: self::headers(),
            providerId: 'yookassa',
        ));

        $this->assertFalse($result->isValid);
        $this->assertNotNull($result->reason);
        $this->assertSame('yookassa_payload_invalid', $result->reason->code->value);
        $this->assertNull($result->reason->providerEventType);
    }

    public function testRejectsPayloadWithoutEvent(): void
    {
        $result = self::validator()->validate(new WebhookInput(
            rawBody: '{"object":{"id":"payment-id"}}',
            headers: self::headers(),
            providerId: 'yookassa',
        ));

        $this->assertFalse($result->isValid);
        $this->assertNotNull($result->reason);
        $this->assertSame('yookassa_event_missing', $result->reason->code->value);
        $this->assertNull($result->reason->providerEventType);
    }

    public function testRejectsPayloadWithEmptyEvent(): void
    {
        $result = self::validator()->validate(new WebhookInput(
            rawBody: '{"event":"   ","object":{"id":"payment-id"}}',
            headers: self::headers(),
            providerId: 'yookassa',
        ));

        $this->assertFalse($result->isValid);
        $this->assertNotNull($result->reason);
        $this->assertSame('yookassa_event_missing', $result->reason->code->value);
        $this->assertNull($result->reason->providerEventType);
    }

    public function testRejectsPayloadWithoutObject(): void
    {
        $result = self::validator()->validate(new WebhookInput(
            rawBody: '{"event":"payment.succeeded"}',
            headers: self::headers(),
            providerId: 'yookassa',
        ));

        $this->assertFalse($result->isValid);
        $this->assertNotNull($result->reason);
        $this->assertSame('yookassa_object_missing', $result->reason->code->value);
        $this->assertSame('payment.succeeded', $result->reason->providerEventType);
    }

    public function testRejectsPayloadWithNonObjectObjectField(): void
    {
        $result = self::validator()->validate(new WebhookInput(
            rawBody: '{"event":"payment.succeeded","object":"payment-id"}',
            headers: self::headers(),
            providerId: 'yookassa',
        ));

        $this->assertFalse($result->isValid);
        $this->assertNotNull($result->reason);
        $this->assertSame('yookassa_object_missing', $result->reason->code->value);
        $this->assertSame('payment.succeeded', $result->reason->providerEventType);
    }

    #[DataProvider('invalidEventPayloadProvider')]
    public function testRejectsPayloadWithInvalidEventField(string $rawBody): void
    {
        $result = self::validator()->validate(new WebhookInput(
            rawBody: $rawBody,
            headers: self::headers(),
            providerId: 'yookassa',
        ));

        $this->assertFalse($result->isValid);
        $this->assertNotNull($result->reason);
        $this->assertSame('yookassa_event_missing', $result->reason->code->value);
        $this->assertNull($result->reason->providerEventType);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidEventPayloadProvider(): iterable
    {
        yield 'event is null' => ['{"event":null,"object":{"id":"payment-id"}}'];
        yield 'event is boolean' => ['{"event":true,"object":{"id":"payment-id"}}'];
        yield 'event is number' => ['{"event":123,"object":{"id":"payment-id"}}'];
        yield 'event is array' => ['{"event":["payment.succeeded"],"object":{"id":"payment-id"}}'];
    }

    #[DataProvider('invalidObjectPayloadProvider')]
    public function testRejectsPayloadWithAdditionalInvalidObjectFields(string $rawBody): void
    {
        $result = self::validator()->validate(new WebhookInput(
            rawBody: $rawBody,
            headers: self::headers(),
            providerId: 'yookassa',
        ));

        $this->assertFalse($result->isValid);
        $this->assertNotNull($result->reason);
        $this->assertSame('yookassa_object_missing', $result->reason->code->value);
        $this->assertSame('payment.succeeded', $result->reason->providerEventType);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidObjectPayloadProvider(): iterable
    {
        yield 'object is null' => ['{"event":"payment.succeeded","object":null}'];
        yield 'object is boolean' => ['{"event":"payment.succeeded","object":false}'];
        yield 'object is number' => ['{"event":"payment.succeeded","object":123}'];
    }

    /**
     * @return array<string, list<string>>
     */
    private static function headers(): array
    {
        return ['Authorization' => ['Basic ' . base64_encode('shop-id:secret-key')]];
    }

    private static function validator(): WebhookYooKassaValidator
    {
        return new WebhookYooKassaValidator('shop-id', 'secret-key');
    }

    /**
     * @param array<string, mixed> $payload
     */
    private static function payload(array $payload): string
    {
        return json_encode($payload, JSON_THROW_ON_ERROR);
    }
}
