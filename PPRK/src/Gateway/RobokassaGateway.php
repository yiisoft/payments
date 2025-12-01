<?php

declare(strict_types=1);

namespace Yiisoft\Payments\Gateway;

use Yiisoft\Payments\Model\PaymentIntent;
use Yiisoft\Payments\Model\PaymentMethod;
use Yiisoft\Payments\Model\PaymentMethodType;

/**
 * Robokassa payment gateway implementation using redirect and signature.
 */
final class RobokassaGateway extends AbstractGateway
{
    /**
     * @param PaymentMethod $paymentMethod Should have providerType = ROBOKASSA.
     * @param array<string,mixed> $config Robokassa configuration (login, passwords, urls).
     */
    public function __construct(
        PaymentMethod $paymentMethod,
        array $config,
        \Psr\Http\Client\ClientInterface $http,
        \Psr\Http\Message\RequestFactoryInterface $requestFactory,
        \Psr\Http\Message\StreamFactoryInterface $streamFactory,
        \Psr\Log\LoggerInterface $logger,
    ) {
        if ($paymentMethod->providerType !== PaymentMethodType::ROBOKASSA) {
            throw new \InvalidArgumentException('PaymentMethod must use ROBOKASSA provider type.');
        }

        parent::__construct($paymentMethod, $config, $http, $requestFactory, $streamFactory, $logger);
    }

    public function createPayment(PaymentIntent $intent): array
    {
        $login = (string)$this->getConfig('merchant_login');
        $password1 = (string)$this->getConfig('password1');
        $isTest = (bool)$this->getConfig('is_test', false);
        $paymentUrl = (string)$this->getConfig('payment_url');

        $amount = number_format($intent->amount, 2, '.', '');
        $invId = $intent->id;
        $description = $intent->description ?? '';

        // Signature for payment page: MerchantLogin:OutSum:InvId:Password1 [web:22]
        $signature = md5($login . ':' . $amount . ':' . $invId . ':' . $password1);

        $query = [
            'MerchantLogin' => $login,
            'OutSum' => $amount,
            'InvId' => $invId,
            'Description' => $description,
            'SignatureValue' => $signature,
            'Email' => $intent->customer->email,
            'IsTest' => $isTest ? 1 : 0,
        ];

        $redirectUrl = $paymentUrl . '?' . http_build_query($query);

        $this->logger->info('Robokassa payment URL generated', [
            'invId' => $invId,
            'amount' => $amount,
            'redirectUrl' => $redirectUrl,
        ]);

        return [
            'success' => true,
            'status' => 'created',
            'redirect_url' => $redirectUrl,
            'raw' => [
                'signature' => $signature,
                'params' => $query,
            ],
        ];
    }

    /**
     * Capture is called during Result URL handling.
     * Robokassa sends OutSum, InvId, SignatureValue; signature is checked using Password2. [web:32]
     *
     * $providerData is expected to contain keys OutSum, InvId, SignatureValue.
     */
    public function capture(PaymentIntent $intent, array $providerData = []): array
    {
        $outSum = (string)($providerData['OutSum'] ?? '');
        $invId = (string)($providerData['InvId'] ?? '');
        $receivedSignature = strtoupper((string)($providerData['SignatureValue'] ?? ''));

        $password2 = (string)$this->getConfig('password2');

        // Signature for Result URL: OutSum:InvId:Password2 [web:32]
        $expectedSignature = strtoupper(md5($outSum . ':' . $invId . ':' . $password2));

        $valid = hash_equals($expectedSignature, $receivedSignature);

        $this->logger->info('Robokassa capture called', [
            'invId' => $invId,
            'outSum' => $outSum,
            'valid' => $valid,
        ]);

        if (!$valid) {
            return [
                'success' => false,
                'status' => 'failed',
                'raw' => [
                    'reason' => 'invalid_signature',
                    'expected' => $expectedSignature,
                    'received' => $receivedSignature,
                ],
            ];
        }

        // At this point you would normally verify amount and mark the intent as paid in your storage.
        return [
            'success' => true,
            'status' => 'paid',
            'raw' => [
                'outSum' => $outSum,
                'invId' => $invId,
            ],
        ];
    }

    /**
     * Robokassa refunds are not exposed as a public REST API in the same way as PayPal.
     * This method reports that refunds must be processed using Robokassa business tools.
     */
    public function refund(PaymentIntent $intent, float $amount, ?string $currency = null): array
    {
        $finalCurrency = $currency ?? $intent->currency;

        $this->logger->warning('Robokassa refund requested but not supported by public API', [
            'invId' => $intent->id,
            'amount' => $amount,
            'currency' => $finalCurrency,
        ]);

        return [
            'success' => false,
            'status' => 'not_supported',
            'raw' => [
                'message' => 'Robokassa refund must be processed via merchant tools or a private integration.',
            ],
        ];
    }
}
