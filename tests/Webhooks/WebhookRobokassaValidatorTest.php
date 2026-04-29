<?php

declare(strict_types=1);

namespace Yiisoft\Payments\Tests\Webhooks;

use PHPUnit\Framework\TestCase;
use Yiisoft\Payments\Webhooks\WebhookInput;
use Yiisoft\Payments\Webhooks\WebhookProviderValidatorInterface;
use Yiisoft\Payments\Webhooks\WebhookRobokassaCallbackFormat;
use Yiisoft\Payments\Webhooks\WebhookRobokassaValidator;

final class WebhookRobokassaValidatorTest extends TestCase
{
    public function testImplementsProviderValidatorContract(): void
    {
        $validator = new WebhookRobokassaValidator();

        $this->assertInstanceOf(WebhookProviderValidatorInterface::class, $validator);
        $this->assertSame('robokassa', $validator->getProviderId());
    }

    public function testReturnsFailClosedAfterRequiredParametersPassUntilSignatureValidationIsImplemented(): void
    {
        $result = (new WebhookRobokassaValidator())->validate(new WebhookInput(
            rawBody: '',
            queryParams: [
                'OutSum' => '100.00',
                'InvId' => '123',
                'SignatureValue' => 'signature',
            ],
            providerId: 'robokassa',
        ));

        $this->assertFalse($result->isValid);
        $this->assertNotNull($result->reason);
        $this->assertSame('robokassa_webhook_validation_not_implemented', $result->reason->code->value);
        $this->assertSame('Robokassa webhook validation is not implemented yet.', $result->reason->message);
        $this->assertNull($result->reason->providerEventType);
    }

    public function testAcceptsRequiredParametersFromBodyParamsBeforeSignatureValidation(): void
    {
        $result = (new WebhookRobokassaValidator())->validate(new WebhookInput(
            rawBody: '',
            bodyParams: [
                'OutSum' => '100.00',
                'InvId' => '123',
                'SignatureValue' => 'signature',
            ],
            providerId: 'robokassa',
        ));

        $this->assertFalse($result->isValid);
        $this->assertNotNull($result->reason);
        $this->assertSame('robokassa_webhook_validation_not_implemented', $result->reason->code->value);
        $this->assertSame('Robokassa webhook validation is not implemented yet.', $result->reason->message);
        $this->assertNull($result->reason->providerEventType);
    }

    /**
     * @dataProvider missingRequiredParameterProvider
     */
    public function testRejectsMissingRequiredParameter(string $parameterName): void
    {
        $queryParams = [
            'OutSum' => '100.00',
            'InvId' => '123',
            'SignatureValue' => 'signature',
        ];
        unset($queryParams[$parameterName]);

        $result = (new WebhookRobokassaValidator())->validate(new WebhookInput(
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
            'SignatureValue' => 'signature',
        ];
        $queryParams[$parameterName] = $value;

        $result = (new WebhookRobokassaValidator())->validate(new WebhookInput(
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
