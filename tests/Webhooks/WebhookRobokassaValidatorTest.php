<?php

declare(strict_types=1);

namespace Yiisoft\Payments\Tests\Webhooks;

use InvalidArgumentException;
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

    public function testRejectsEmptyPassword2(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Robokassa password2 must be a non-empty string.');

        new WebhookRobokassaValidator('   ');
    }

    /**
     * @dataProvider missingRequiredParameterProvider
     */
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

    /**
     * @dataProvider emptyRequiredParameterProvider
     */
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
