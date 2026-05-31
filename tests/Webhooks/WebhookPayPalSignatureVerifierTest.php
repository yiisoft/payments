<?php

declare(strict_types=1);

namespace Yiisoft\Payments\Tests\Webhooks;

use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\TestCase;
use Yiisoft\Payments\Endpoints\PayPalEndpoints;
use Yiisoft\Payments\Tests\Support\TestHttpClient;
use Yiisoft\Payments\Webhooks\WebhookInput;
use Yiisoft\Payments\Webhooks\WebhookPayPalSignatureVerifier;

final class WebhookPayPalSignatureVerifierTest extends TestCase
{
    public function testReturnsSuccessForSuccessfulPayPalVerificationResponse(): void
    {
        $factory = new Psr17Factory();
        $httpClient = new TestHttpClient($factory);
        $httpClient->queueJsonResponse([
            'access_token' => 'ACCESS-TOKEN-123',
            'expires_in' => 3600,
        ]);
        $httpClient->queueJsonResponse([
            'verification_status' => 'SUCCESS',
        ]);

        $verifier = new WebhookPayPalSignatureVerifier(
            clientId: 'client-id',
            clientSecret: 'client-secret',
            sandbox: true,
            httpClient: $httpClient,
            requestFactory: $factory,
            streamFactory: $factory,
            endpoints: new PayPalEndpoints(
                sandboxBaseUri: 'https://paypal-sandbox.test',
                liveBaseUri: 'https://paypal-live.test',
            ),
        );

        $result = $verifier->verify($this->input(), 'WH-CONFIGURED-123');

        $this->assertTrue($result->isValid);
        $this->assertNull($result->reason);
        $this->assertSame('POST', $httpClient->lastRequest['method']);
        $this->assertSame(
            'https://paypal-sandbox.test/v1/notifications/verify-webhook-signature',
            $httpClient->lastRequest['uri'],
        );
        $this->assertSame(['Bearer ACCESS-TOKEN-123'], $httpClient->lastRequest['headers']['Authorization']);
        $this->assertSame(['application/json'], $httpClient->lastRequest['headers']['Content-Type']);

        $requestBody = json_decode($httpClient->lastRequest['body'], true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame('SHA256withRSA', $requestBody['auth_algo']);
        $this->assertSame('https://api-m.paypal.com/certs/test.pem', $requestBody['cert_url']);
        $this->assertSame('transmission-id', $requestBody['transmission_id']);
        $this->assertSame('signature', $requestBody['transmission_sig']);
        $this->assertSame('2026-04-29T10:00:00Z', $requestBody['transmission_time']);
        $this->assertSame('WH-CONFIGURED-123', $requestBody['webhook_id']);
        $this->assertSame([
            'id' => 'WH-EVENT-123',
            'event_type' => 'PAYMENT.CAPTURE.COMPLETED',
            'resource' => [
                'id' => 'CAPTURE-123',
                'status' => 'COMPLETED',
            ],
        ], $requestBody['webhook_event']);
    }

    public function testReturnsFailureForFailedPayPalVerificationResponse(): void
    {
        $factory = new Psr17Factory();
        $httpClient = new TestHttpClient($factory);
        $httpClient->queueJsonResponse([
            'access_token' => 'ACCESS-TOKEN-123',
            'expires_in' => 3600,
        ]);
        $httpClient->queueJsonResponse([
            'verification_status' => 'FAILURE',
        ]);

        $verifier = new WebhookPayPalSignatureVerifier(
            clientId: 'client-id',
            clientSecret: 'client-secret',
            sandbox: true,
            httpClient: $httpClient,
            requestFactory: $factory,
            streamFactory: $factory,
            endpoints: new PayPalEndpoints(
                sandboxBaseUri: 'https://paypal-sandbox.test',
                liveBaseUri: 'https://paypal-live.test',
            ),
        );

        $result = $verifier->verify($this->input(), 'WH-CONFIGURED-123');

        $this->assertFalse($result->isValid);
        $this->assertNotNull($result->reason);
        $this->assertSame('paypal_signature_verification_failed', $result->reason->code->value);
        $this->assertSame('PayPal webhook signature verification failed.', $result->reason->message);
        $this->assertSame('POST', $httpClient->lastRequest['method']);
        $this->assertSame(
            'https://paypal-sandbox.test/v1/notifications/verify-webhook-signature',
            $httpClient->lastRequest['uri'],
        );
    }


    /**
     * @dataProvider malformedVerificationResponseProvider
     */
    public function testReturnsFailureForMalformedPayPalVerificationResponse(
        string $responseBody,
        string $expectedCode,
        string $expectedMessage,
    ): void {
        $factory = new Psr17Factory();
        $httpClient = new TestHttpClient($factory);
        $httpClient->queueJsonResponse([
            'access_token' => 'ACCESS-TOKEN-123',
            'expires_in' => 3600,
        ]);
        $httpClient->queueRawResponse($responseBody, 200, ['Content-Type' => ['application/json']]);

        $verifier = $this->createVerifier($factory, $httpClient);

        $result = $verifier->verify($this->input(), 'WH-CONFIGURED-123');

        $this->assertFalse($result->isValid);
        $this->assertNotNull($result->reason);
        $this->assertSame($expectedCode, $result->reason->code->value);
        $this->assertSame($expectedMessage, $result->reason->message);
    }

    public static function malformedVerificationResponseProvider(): array
    {
        return [
            'invalid JSON' => [
                '{not-json',
                'paypal_signature_verification_failed',
                'PayPal webhook signature verification request failed: Syntax error',
            ],
            'JSON list' => [
                '[]',
                'paypal_signature_verification_response_invalid',
                'PayPal webhook signature verification response does not contain a supported verification status.',
            ],
            'missing verification status' => [
                json_encode(['id' => 'VERIFY-123'], JSON_THROW_ON_ERROR),
                'paypal_signature_verification_response_invalid',
                'PayPal webhook signature verification response does not contain a supported verification status.',
            ],
            'unsupported verification status' => [
                json_encode(['verification_status' => 'PENDING'], JSON_THROW_ON_ERROR),
                'paypal_signature_verification_response_invalid',
                'PayPal webhook signature verification response does not contain a supported verification status.',
            ],
        ];
    }

    public function testReturnsFailureForPayPalVerificationHttpErrorResponse(): void
    {
        $factory = new Psr17Factory();
        $httpClient = new TestHttpClient($factory);
        $httpClient->queueJsonResponse([
            'access_token' => 'ACCESS-TOKEN-123',
            'expires_in' => 3600,
        ]);
        $httpClient->queueJsonResponse(['name' => 'INTERNAL_SERVER_ERROR'], 500);

        $verifier = $this->createVerifier($factory, $httpClient);

        $result = $verifier->verify($this->input(), 'WH-CONFIGURED-123');

        $this->assertFalse($result->isValid);
        $this->assertNotNull($result->reason);
        $this->assertSame('paypal_signature_verification_failed', $result->reason->code->value);
        $this->assertSame(
            'PayPal webhook signature verification request failed: PayPal API returned HTTP 500 during webhook signature verification.',
            $result->reason->message,
        );
    }

    /**
     * @dataProvider malformedAccessTokenResponseProvider
     */
    public function testReturnsFailureForMalformedPayPalAccessTokenResponse(
        string $responseBody,
        string $expectedMessage,
    ): void {
        $factory = new Psr17Factory();
        $httpClient = new TestHttpClient($factory);
        $httpClient->queueRawResponse($responseBody, 200, ['Content-Type' => ['application/json']]);

        $verifier = $this->createVerifier($factory, $httpClient);

        $result = $verifier->verify($this->input(), 'WH-CONFIGURED-123');

        $this->assertFalse($result->isValid);
        $this->assertNotNull($result->reason);
        $this->assertSame('paypal_signature_verification_failed', $result->reason->code->value);
        $this->assertSame($expectedMessage, $result->reason->message);
    }

    public static function malformedAccessTokenResponseProvider(): array
    {
        return [
            'invalid JSON' => [
                '{not-json',
                'PayPal webhook signature verification request failed: Syntax error',
            ],
            'JSON list' => [
                '[]',
                'PayPal webhook signature verification request failed: PayPal access token response does not contain a non-empty access token.',
            ],
            'missing access token' => [
                json_encode(['expires_in' => 3600], JSON_THROW_ON_ERROR),
                'PayPal webhook signature verification request failed: PayPal access token response does not contain a non-empty access token.',
            ],
            'empty access token' => [
                json_encode(['access_token' => '', 'expires_in' => 3600], JSON_THROW_ON_ERROR),
                'PayPal webhook signature verification request failed: PayPal access token response does not contain a non-empty access token.',
            ],
        ];
    }

    public function testReturnsFailureForPayPalAccessTokenHttpErrorResponse(): void
    {
        $factory = new Psr17Factory();
        $httpClient = new TestHttpClient($factory);
        $httpClient->queueJsonResponse(['error' => 'invalid_client'], 401);

        $verifier = $this->createVerifier($factory, $httpClient);

        $result = $verifier->verify($this->input(), 'WH-CONFIGURED-123');

        $this->assertFalse($result->isValid);
        $this->assertNotNull($result->reason);
        $this->assertSame('paypal_signature_verification_failed', $result->reason->code->value);
        $this->assertSame(
            'PayPal webhook signature verification request failed: PayPal API returned HTTP 401 while requesting an access token.',
            $result->reason->message,
        );
    }


    private function createVerifier(Psr17Factory $factory, TestHttpClient $httpClient): WebhookPayPalSignatureVerifier
    {
        return new WebhookPayPalSignatureVerifier(
            clientId: 'client-id',
            clientSecret: 'client-secret',
            sandbox: true,
            httpClient: $httpClient,
            requestFactory: $factory,
            streamFactory: $factory,
            endpoints: new PayPalEndpoints(
                sandboxBaseUri: 'https://paypal-sandbox.test',
                liveBaseUri: 'https://paypal-live.test',
            ),
        );
    }

    private function input(): WebhookInput
    {
        return new WebhookInput(
            rawBody: json_encode([
                'id' => 'WH-EVENT-123',
                'event_type' => 'PAYMENT.CAPTURE.COMPLETED',
                'resource' => [
                    'id' => 'CAPTURE-123',
                    'status' => 'COMPLETED',
                ],
            ], JSON_THROW_ON_ERROR),
            headers: [
                'PayPal-Transmission-Id' => ['  transmission-id  '],
                'PayPal-Transmission-Time' => ['  2026-04-29T10:00:00Z  '],
                'PayPal-Cert-Url' => ['  https://api-m.paypal.com/certs/test.pem  '],
                'PayPal-Auth-Algo' => ['  SHA256withRSA  '],
                'PayPal-Transmission-Sig' => ['  signature  '],
            ],
            providerId: 'paypal',
        );
    }
}
