<?php

declare(strict_types=1);

namespace Yiisoft\Payments\Tests\Webhooks;

use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Yiisoft\Payments\Webhooks\WebhookInput;
use Yiisoft\Payments\Webhooks\WebhookProviderValidatorInterface;
use Yiisoft\Payments\Webhooks\WebhookRobokassaCallbackFormat;
use Yiisoft\Payments\Webhooks\WebhookRobokassaValidator;

final class WebhookRobokassaValidatorTest extends TestCase
{
    public function testImplementsProviderValidatorContract(): void
    {
        $validator = new WebhookRobokassaValidator('pass2');

        $this->assertInstanceOf(WebhookProviderValidatorInterface::class, $validator);
        $this->assertSame('robokassa', $validator->getProviderId());
    }

    public function testReturnsSuccessWhenSignatureMatchesRequiredParameters(): void
    {
        $result = (new WebhookRobokassaValidator('pass2'))->validate(new WebhookInput(
            rawBody: '',
            queryParams: [
                'OutSum' => '100.00',
                'InvId' => '123',
                'SignatureValue' => md5('100.00:123:pass2'),
            ],
            providerId: 'robokassa',
        ));

        $this->assertTrue($result->isValid);
        $this->assertNull($result->reason);
    }

    public function testAcceptsRequiredParametersFromBodyParamsForSignatureValidation(): void
    {
        $result = (new WebhookRobokassaValidator('pass2'))->validate(new WebhookInput(
            rawBody: '',
            bodyParams: [
                'OutSum' => '100.00',
                'InvId' => '123',
                'SignatureValue' => md5('100.00:123:pass2'),
            ],
            providerId: 'robokassa',
        ));

        $this->assertTrue($result->isValid);
        $this->assertNull($result->reason);
    }

    public function testReturnsSuccessWhenSignatureValueUsesUppercaseHex(): void
    {
        $result = (new WebhookRobokassaValidator('pass2'))->validate(new WebhookInput(
            rawBody: '',
            queryParams: [
                'OutSum' => '100.00',
                'InvId' => '123',
                'SignatureValue' => strtoupper(md5('100.00:123:pass2')),
            ],
            providerId: 'robokassa',
        ));

        $this->assertTrue($result->isValid);
        $this->assertNull($result->reason);
    }

    public function testReturnsSuccessWhenSignatureIncludesCustomShpParametersInSortedOrder(): void
    {
        $result = (new WebhookRobokassaValidator('pass2'))->validate(new WebhookInput(
            rawBody: '',
            queryParams: [
                'OutSum' => '100.00',
                'InvId' => '123',
                'Shp_user' => '42',
                'Shp_order' => 'abc',
                'SignatureValue' => md5('100.00:123:pass2:Shp_order=abc:Shp_user=42'),
            ],
            providerId: 'robokassa',
        ));

        $this->assertTrue($result->isValid);
        $this->assertNull($result->reason);
    }

    public function testReturnsSuccessWhenCustomShpParametersAreProvidedAcrossQueryAndBodyParams(): void
    {
        $result = (new WebhookRobokassaValidator('pass2'))->validate(new WebhookInput(
            rawBody: '',
            queryParams: [
                'OutSum' => '100.00',
                'InvId' => '123',
                'Shp_user' => '42',
            ],
            bodyParams: [
                'SignatureValue' => md5('100.00:123:pass2:Shp_order=abc:Shp_user=42'),
                'Shp_order' => 'abc',
            ],
            providerId: 'robokassa',
        ));

        $this->assertTrue($result->isValid);
        $this->assertNull($result->reason);
    }

    public function testReturnsSuccessWhenSignatureUsesTrimmedRequiredAndCustomParameterValues(): void
    {
        $result = (new WebhookRobokassaValidator('pass2'))->validate(new WebhookInput(
            rawBody: '',
            queryParams: [
                'OutSum' => ' 100.00 ',
                'InvId' => ' 123 ',
                'Shp_user' => ' 42 ',
                'SignatureValue' => md5('100.00:123:pass2:Shp_user=42'),
            ],
            providerId: 'robokassa',
        ));

        $this->assertTrue($result->isValid);
        $this->assertNull($result->reason);
    }

    public function testRejectsInvalidSignature(): void
    {
        $result = (new WebhookRobokassaValidator('pass2'))->validate(new WebhookInput(
            rawBody: '',
            queryParams: [
                'OutSum' => '100.00',
                'InvId' => '123',
                'SignatureValue' => md5('100.00:123:wrong-pass2'),
            ],
            providerId: 'robokassa',
        ));

        $this->assertFalse($result->isValid);
        $this->assertNotNull($result->reason);
        $this->assertSame('robokassa_signature_mismatch', $result->reason->code->value);
        $this->assertSame('Robokassa callback signature does not match the request parameters.', $result->reason->message);
        $this->assertNull($result->reason->providerEventType);
    }

    #[DataProvider('invalidSignatureProvider')]
    public function testRejectsInvalidSignatureVariants(array $queryParams, array $bodyParams = []): void
    {
        $result = (new WebhookRobokassaValidator('pass2'))->validate(new WebhookInput(
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

        yield 'signature includes different Shp parameter value' => [[
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

    #[DataProvider('missingRequiredParameterAcrossRequestPartsProvider')]
    public function testRejectsMissingRequiredParameterAcrossRequestParts(
        array $queryParams,
        array $bodyParams,
        string $missingParameterName,
    ): void {
        $result = (new WebhookRobokassaValidator('pass2'))->validate(new WebhookInput(
            rawBody: '',
            queryParams: $queryParams,
            bodyParams: $bodyParams,
            providerId: 'robokassa',
        ));

        $this->assertFalse($result->isValid);
        $this->assertNotNull($result->reason);
        $this->assertSame('robokassa_required_parameter_missing', $result->reason->code->value);
        $this->assertSame(
            sprintf('Required Robokassa callback parameter "%s" is missing or empty.', $missingParameterName),
            $result->reason->message,
        );
        $this->assertNull($result->reason->providerEventType);
    }

    /**
     * @return iterable<string, array{array<string, mixed>, array<string, mixed>, string}>
     */
    public static function missingRequiredParameterAcrossRequestPartsProvider(): iterable
    {
        yield 'missing OutSum when other required params are split across body and query' => [
            [
                'InvId' => '123',
            ],
            [
                'SignatureValue' => md5('100.00:123:pass2'),
            ],
            'OutSum',
        ];

        yield 'missing InvId when other required params are split across body and query' => [
            [
                'OutSum' => '100.00',
            ],
            [
                'SignatureValue' => md5('100.00:123:pass2'),
            ],
            'InvId',
        ];

        yield 'missing SignatureValue when OutSum and InvId are split across body and query' => [
            [
                'OutSum' => '100.00',
            ],
            [
                'InvId' => '123',
            ],
            'SignatureValue',
        ];
    }

    public function testRejectsEmptyPassword2(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Robokassa password2 must be a non-empty string.');

        new WebhookRobokassaValidator('   ');
    }

    #[DataProvider('missingRequiredParameterProvider')]
    public function testRejectsMissingRequiredParameter(string $parameterName): void
    {
        $queryParams = [
            'OutSum' => '100.00',
            'InvId' => '123',
            'SignatureValue' => md5('100.00:123:pass2'),
        ];
        unset($queryParams[$parameterName]);

        $result = (new WebhookRobokassaValidator('pass2'))->validate(new WebhookInput(
            rawBody: '',
            queryParams: $queryParams,
            providerId: 'robokassa',
        ));

        $this->assertFalse($result->isValid);
        $this->assertNotNull($result->reason);
        $this->assertSame('robokassa_required_parameter_missing', $result->reason->code->value);
        $this->assertSame(
            sprintf('Required Robokassa callback parameter "%s" is missing or empty.', $parameterName),
            $result->reason->message,
        );
        $this->assertNull($result->reason->providerEventType);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function missingRequiredParameterProvider(): iterable
    {
        yield 'missing OutSum' => ['OutSum'];
        yield 'missing InvId' => ['InvId'];
        yield 'missing SignatureValue' => ['SignatureValue'];
    }

    #[DataProvider('emptyRequiredParameterProvider')]
    public function testRejectsEmptyRequiredParameter(string $parameterName, mixed $value): void
    {
        $queryParams = [
            'OutSum' => '100.00',
            'InvId' => '123',
            'SignatureValue' => md5('100.00:123:pass2'),
        ];
        $queryParams[$parameterName] = $value;

        $result = (new WebhookRobokassaValidator('pass2'))->validate(new WebhookInput(
            rawBody: '',
            queryParams: $queryParams,
            providerId: 'robokassa',
        ));

        $this->assertFalse($result->isValid);
        $this->assertNotNull($result->reason);
        $this->assertSame('robokassa_required_parameter_missing', $result->reason->code->value);
        $this->assertSame(
            sprintf('Required Robokassa callback parameter "%s" is missing or empty.', $parameterName),
            $result->reason->message,
        );
        $this->assertNull($result->reason->providerEventType);
    }

    /**
     * @return iterable<string, array{string, mixed}>
     */
    public static function emptyRequiredParameterProvider(): iterable
    {
        foreach (WebhookRobokassaCallbackFormat::requiredParameters() as $parameterName) {
            yield $parameterName . ' is empty string' => [$parameterName, ''];
            yield $parameterName . ' is whitespace' => [$parameterName, '   '];
            yield $parameterName . ' is null' => [$parameterName, null];
            yield $parameterName . ' is array' => [$parameterName, ['value']];
        }
    }
}
