<?php

declare(strict_types=1);

namespace Yiisoft\Payments\Tests\Webhooks;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Yiisoft\Payments\Webhooks\WebhookInput;
use Yiisoft\Payments\Webhooks\WebhookRobokassaValidator;

final class WebhookRobokassaValidationCasesTest extends TestCase
{
    /**
     * @dataProvider validSignatureProvider
     */
    public function testAcceptsValidSignatureCases(array $queryParams, array $bodyParams = []): void
    {
        $result = $this->validator()->validate(new WebhookInput(
            rawBody: '',
            queryParams: $queryParams,
            bodyParams: $bodyParams,
            providerId: 'robokassa',
        ));

        $this->assertTrue($result->isValid);
        $this->assertNull($result->reason);
    }

    /**
     * @return iterable<string, array{array<string, mixed>, 1?: array<string, mixed>}>
     */
    public static function validSignatureProvider(): iterable
    {
        yield 'standard ResultURL query params' => [[
            'OutSum' => '100.00',
            'InvId' => '123',
            'SignatureValue' => md5('100.00:123:pass2'),
        ]];

        yield 'ResultURL body params' => [
            [],
            [
                'OutSum' => '100.00',
                'InvId' => '123',
                'SignatureValue' => md5('100.00:123:pass2'),
            ],
        ];

        yield 'uppercase signature value' => [[
            'OutSum' => '100.00',
            'InvId' => '123',
            'SignatureValue' => strtoupper(md5('100.00:123:pass2')),
        ]];

        yield 'sorted custom Shp parameters' => [[
            'OutSum' => '100.00',
            'InvId' => '123',
            'Shp_user' => '42',
            'Shp_order' => 'abc',
            'SignatureValue' => md5('100.00:123:pass2:Shp_order=abc:Shp_user=42'),
        ]];

        yield 'custom Shp parameters split across query and body params' => [
            [
                'OutSum' => '100.00',
                'InvId' => '123',
                'Shp_user' => '42',
            ],
            [
                'Shp_order' => 'abc',
                'SignatureValue' => md5('100.00:123:pass2:Shp_order=abc:Shp_user=42'),
            ],
        ];

        yield 'values with surrounding whitespace' => [[
            'OutSum' => ' 100.00 ',
            'InvId' => ' 123 ',
            'Shp_user' => ' 42 ',
            'SignatureValue' => ' ' . md5('100.00:123:pass2:Shp_user=42') . ' ',
        ]];
    }

    /**
     * @dataProvider invalidSignatureProvider
     */
    public function testRejectsInvalidSignatureCases(array $queryParams, array $bodyParams = []): void
    {
        $result = $this->validator()->validate(new WebhookInput(
            rawBody: '',
            queryParams: $queryParams,
            bodyParams: $bodyParams,
            providerId: 'robokassa',
        ));

        $this->assertFalse($result->isValid);
        $this->assertNotNull($result->reason);
        $this->assertSame('robokassa_signature_mismatch', $result->reason->code->value);
        $this->assertSame('Robokassa callback signature does not match the request parameters.', $result->reason->message);
        $this->assertNull($result->reason->providerEventType);
    }

    /**
     * @return iterable<string, array{array<string, mixed>, 1?: array<string, mixed>}>
     */
    public static function invalidSignatureProvider(): iterable
    {
        yield 'plain invalid signature value' => [[
            'OutSum' => '100.00',
            'InvId' => '123',
            'SignatureValue' => 'invalid-signature',
        ]];

        yield 'signature created with another password2' => [[
            'OutSum' => '100.00',
            'InvId' => '123',
            'SignatureValue' => md5('100.00:123:another-pass2'),
        ]];

        yield 'signature does not include provided Shp parameter' => [[
            'OutSum' => '100.00',
            'InvId' => '123',
            'Shp_order' => 'abc',
            'SignatureValue' => md5('100.00:123:pass2'),
        ]];

        yield 'signature includes another Shp parameter value' => [[
            'OutSum' => '100.00',
            'InvId' => '123',
            'Shp_order' => 'abc',
            'SignatureValue' => md5('100.00:123:pass2:Shp_order=xyz'),
        ]];

        yield 'signature does not match params split across query and body' => [
            [
                'OutSum' => '100.00',
                'InvId' => '123',
                'Shp_user' => '42',
            ],
            [
                'SignatureValue' => md5('100.00:123:pass2'),
            ],
        ];
    }

    /**
     * @dataProvider invalidRequiredParameterProvider
     */
    public function testRejectsInvalidRequiredParameterCases(
        array $queryParams,
        array $bodyParams,
        string $expectedParameterName,
    ): void {
        $result = $this->validator()->validate(new WebhookInput(
            rawBody: '',
            queryParams: $queryParams,
            bodyParams: $bodyParams,
            providerId: 'robokassa',
        ));

        $this->assertFalse($result->isValid);
        $this->assertNotNull($result->reason);
        $this->assertSame('robokassa_required_parameter_missing', $result->reason->code->value);
        $this->assertSame(
            sprintf('Required Robokassa callback parameter "%s" is missing or empty.', $expectedParameterName),
            $result->reason->message,
        );
        $this->assertNull($result->reason->providerEventType);
    }

    /**
     * @return iterable<string, array{array<string, mixed>, array<string, mixed>, string}>
     */
    public static function invalidRequiredParameterProvider(): iterable
    {
        yield 'missing OutSum' => [
            [
                'InvId' => '123',
                'SignatureValue' => md5('100.00:123:pass2'),
            ],
            [],
            'OutSum',
        ];

        yield 'missing InvId' => [
            [
                'OutSum' => '100.00',
                'SignatureValue' => md5('100.00:123:pass2'),
            ],
            [],
            'InvId',
        ];

        yield 'missing SignatureValue' => [
            [
                'OutSum' => '100.00',
                'InvId' => '123',
            ],
            [],
            'SignatureValue',
        ];

        yield 'empty OutSum' => [
            [
                'OutSum' => '   ',
                'InvId' => '123',
                'SignatureValue' => md5('100.00:123:pass2'),
            ],
            [],
            'OutSum',
        ];

        yield 'non-string InvId' => [
            [
                'OutSum' => '100.00',
                'InvId' => 123,
                'SignatureValue' => md5('100.00:123:pass2'),
            ],
            [],
            'InvId',
        ];

        yield 'empty SignatureValue when params are split across query and body' => [
            [
                'OutSum' => '100.00',
            ],
            [
                'InvId' => '123',
                'SignatureValue' => '',
            ],
            'SignatureValue',
        ];
    }

    public function testRejectsInvalidPassword2Config(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Robokassa password2 must be a non-empty string.');

        new WebhookRobokassaValidator(" \t\n ");
    }

    private function validator(): WebhookRobokassaValidator
    {
        return new WebhookRobokassaValidator('pass2');
    }
}
