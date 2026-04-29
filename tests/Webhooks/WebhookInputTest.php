<?php

declare(strict_types=1);

namespace Yiisoft\Payments\Tests\Webhooks;

use PHPUnit\Framework\TestCase;
use Yiisoft\Payments\Webhooks\WebhookInput;

final class WebhookInputTest extends TestCase
{
    public function testHeaderLookupSupportsSingleValueHeaders(): void
    {
        $input = new WebhookInput(
            rawBody: '{}',
            headers: [
                'Stripe-Signature' => 'test_signature',
            ],
        );

        $this->assertSame(['test_signature'], $input->getHeader('stripe-signature'));
    }

    public function testHeaderLookupSupportsMultiValueHeaders(): void
    {
        $input = new WebhookInput(
            rawBody: '{}',
            headers: [
                'X-Test-Header' => ['first', 'second'],
            ],
        );

        $this->assertSame(['first', 'second'], $input->getHeader('x-test-header'));
    }

    public function testHeaderLookupMergesSingleAndMultiValueHeadersCaseInsensitively(): void
    {
        $input = new WebhookInput(
            rawBody: '{}',
            headers: [
                'X-Test-Header' => 'first',
                'x-test-header' => ['second', 'third'],
            ],
        );

        $this->assertSame(['first', 'second', 'third'], $input->getHeader('X-TEST-HEADER'));
    }

    public function testOriginalHeaderMapKeepsSingleAndMultiValueHeaders(): void
    {
        $headers = [
            'Stripe-Signature' => 'test_signature',
            'X-Test-Header' => ['first', 'second'],
        ];
        $input = new WebhookInput(rawBody: '{}', headers: $headers);

        $this->assertSame($headers, $input->getHeaders());
    }

    public function testHeaderLookupSupportsDifferentHeaderNameCases(): void
    {
        $input = new WebhookInput(
            rawBody: '{}',
            headers: [
                'Stripe-Signature' => 'test_signature',
            ],
        );

        foreach (['stripe-signature', 'STRIPE-SIGNATURE', 'Stripe-Signature', 'sTrIpE-sIgNaTuRe'] as $lookupName) {
            $this->assertSame(['test_signature'], $input->getHeader($lookupName));
        }
    }

    public function testHeaderLookupKeepsOriginalHeaderNamesWhenUsingDifferentCases(): void
    {
        $headers = [
            'Stripe-Signature' => 'test_signature',
            'X-Custom-Signature' => 'custom_signature',
        ];
        $input = new WebhookInput(rawBody: '{}', headers: $headers);

        $this->assertSame(['test_signature'], $input->getHeader('stripe-signature'));
        $this->assertSame(['custom_signature'], $input->getHeader('x-custom-signature'));
        $this->assertSame($headers, $input->getHeaders());
    }

    public function testQueryParamsArePreservedForFormCallbacks(): void
    {
        $queryParams = [
            'OutSum' => '100.00',
            'InvId' => '42',
            'SignatureValue' => 'ABC123',
            'optional' => null,
        ];

        $input = new WebhookInput(rawBody: '', queryParams: $queryParams);

        $this->assertSame($queryParams, $input->queryParams);
    }

    public function testBodyParamsArePreservedForFormCallbacks(): void
    {
        $bodyParams = [
            'OutSum' => '100.00',
            'InvId' => '42',
            'SignatureValue' => 'ABC123',
            'Shp_user_id' => 'user-123',
        ];

        $input = new WebhookInput(rawBody: '', bodyParams: $bodyParams);

        $this->assertSame($bodyParams, $input->bodyParams);
    }

    public function testQueryAndBodyParamsAreKeptSeparatelyForFormCallbacks(): void
    {
        $queryParams = [
            'source' => 'query',
            'InvId' => '42',
        ];
        $bodyParams = [
            'source' => 'body',
            'SignatureValue' => 'ABC123',
        ];

        $input = new WebhookInput(
            rawBody: 'OutSum=100.00&InvId=42&SignatureValue=ABC123',
            queryParams: $queryParams,
            bodyParams: $bodyParams,
        );

        $this->assertSame($queryParams, $input->queryParams);
        $this->assertSame($bodyParams, $input->bodyParams);
    }

    public function testProviderIdDoesNotChangeRawInputData(): void
    {
        $rawBody = '  {"type":"payment_intent.succeeded","id":"evt_123"}  ';
        $headers = [
            'Stripe-Signature' => 't=123,v1=abc',
            'PayPal-Transmission-Id' => 'paypal-transmission-id',
            'Content-Type' => ['application/json'],
        ];
        $queryParams = [
            'OutSum' => '100.00',
            'InvId' => '42',
        ];
        $bodyParams = [
            'SignatureValue' => 'ABC123',
            'Shp_user_id' => 'user-123',
        ];

        $input = new WebhookInput(
            rawBody: $rawBody,
            headers: $headers,
            queryParams: $queryParams,
            bodyParams: $bodyParams,
            providerId: 'stripe',
        );

        $this->assertSame($rawBody, $input->rawBody);
        $this->assertSame($headers, $input->getHeaders());
        $this->assertSame($queryParams, $input->queryParams);
        $this->assertSame($bodyParams, $input->bodyParams);
        $this->assertSame('stripe', $input->providerId);
    }

    public function testProviderIdDoesNotTriggerProviderSpecificHeaderAliases(): void
    {
        $input = new WebhookInput(
            rawBody: '{}',
            headers: [
                'PayPal-Transmission-Id' => 'paypal-transmission-id',
            ],
            providerId: 'stripe',
        );

        $this->assertSame(['paypal-transmission-id'], $input->getHeader('paypal-transmission-id'));
        $this->assertSame([], $input->getHeader('stripe-signature'));
    }
}
