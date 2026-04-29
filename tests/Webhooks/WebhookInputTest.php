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
}
