<?php

declare(strict_types=1);

namespace Yiisoft\Payments\Models;

use Yiisoft\Payments\Exceptions\InvalidArgumentException;

/**
 * Represents a payment intent in the payment gateway system.
 */
readonly class PaymentIntent
{
    private const ISO4217_CURRENCY_PATTERN = '/^[A-Za-z]{3}$/';

    private function validateCurrency(string $currency): string
    {
        if (!preg_match(self::ISO4217_CURRENCY_PATTERN, $currency)) {
            throw new InvalidArgumentException(sprintf(
                'Currency must be a valid ISO 4217 currency code, "%s" given.',
                $currency
            ));
        }
        return strtoupper($currency);
    }

    public const STATUS_REQUIRES_PAYMENT_METHOD = 'requires_payment_method';
    public const STATUS_REQUIRES_CONFIRMATION = 'requires_confirmation';
    public const STATUS_REQUIRES_ACTION = 'requires_action';
    public const STATUS_PROCESSING = 'processing';
    public const STATUS_REQUIRES_CAPTURE = 'requires_capture';
    public const STATUS_CANCELED = 'canceled';
    public const STATUS_SUCCEEDED = 'succeeded';

    /**
     * @param string|null $id The unique identifier for the payment intent.
     * @param string|null $status The status of the payment intent.
     * @param int|null $amount The amount to be collected by this payment intent.
     * @param string|null $currency Three-letter ISO currency code.
     * @param string|null $customerId ID of the customer this payment intent is for.
     * @param string|null $paymentMethodId ID of the payment method used with this payment intent.
     * @param string|null $clientSecret The client secret of this PaymentIntent.
     * @param string|null $description An arbitrary string attached to the object.
     * @param array|null $metadata Set of key-value pairs for storing additional data.
     * @param array|null $nextAction Next action to take to complete the payment.
     * @param array|null $charges List of charges associated with this payment intent.
     * @param bool|null $captureMethod Whether to capture the payment later.
     * @param bool|null $confirm Whether to confirm the payment intent immediately.
     * @param bool|null $offSession Whether the payment is off-session.
     * @param string|null $receiptEmail Email address that the receipt will be sent to.
     * @param string|null $statementDescriptor A string to be displayed on the customer's statement.
     * @param int|null $createdAt The time at which the payment intent was created.
     */
    public string|null $currency;

    public function __construct(
        public ?string $id = null,
        public ?string $status = null,
        public ?int $amount = null,
        ?string $currency = null,
        public ?string $customerId = null,
        public ?string $paymentMethodId = null,
        public ?string $clientSecret = null,
        public ?string $description = null,
        public ?array $metadata = null,
        public ?array $nextAction = null,
        public ?array $charges = null,
        public ?bool $captureMethod = null,
        public ?bool $confirm = null,
        public ?bool $offSession = null,
        public ?string $receiptEmail = null,
        public ?string $statementDescriptor = null,
        public ?int $createdAt = null,
    ) {
        if ($currency !== null) {
            $this->currency = $this->validateCurrency($currency);
        }
    }



    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'status' => $this->status,
            'amount' => $this->amount,
            'currency' => $this->currency,
            'customer_id' => $this->customerId,
            'payment_method_id' => $this->paymentMethodId,
            'client_secret' => $this->clientSecret,
            'description' => $this->description,
            'metadata' => $this->metadata,
            'next_action' => $this->nextAction,
            'charges' => $this->charges,
            'capture_method' => $this->captureMethod,
            'confirm' => $this->confirm,
            'off_session' => $this->offSession,
            'receipt_email' => $this->receiptEmail,
            'statement_descriptor' => $this->statementDescriptor,
            'created_at' => $this->createdAt,
        ];
    }

    /**
     * Creates a new PaymentIntent instance from an array of data.
     *
     * @param array<string, mixed> $data The payment intent data.
     * @return self A new PaymentIntent instance.
     */
    public static function fromArray(array $data): self
    {
        $currency = $data['currency'] ?? null;
        if ($currency !== null && !is_string($currency)) {
            throw new InvalidArgumentException('Currency must be a string or null');
        }

        $captureMethod = $data['capture_method'] ?? $data['captureMethod'] ?? null;
        if (is_string($captureMethod)) {
            $captureMethod = match ($captureMethod) {
                'manual' => true,
                'automatic' => false,
                default => null,
            };
        }

        return new self(
            id: $data['id'] ?? null,
            status: $data['status'] ?? null,
            amount: $data['amount'] ?? null,
            currency: $currency,
            customerId: $data['customer_id'] ?? $data['customerId'] ?? null,
            paymentMethodId: $data['payment_method_id'] ?? $data['paymentMethodId'] ?? null,
            clientSecret: $data['client_secret'] ?? $data['clientSecret'] ?? null,
            description: $data['description'] ?? null,
            metadata: $data['metadata'] ?? null,
            nextAction: $data['next_action'] ?? $data['nextAction'] ?? null,
            charges: $data['charges'] ?? null,
            captureMethod: $captureMethod,
            confirm: $data['confirm'] ?? null,
            offSession: $data['off_session'] ?? $data['offSession'] ?? null,
            receiptEmail: $data['receipt_email'] ?? $data['receiptEmail'] ?? null,
            statementDescriptor: $data['statement_descriptor'] ?? $data['statementDescriptor'] ?? null,
            createdAt: $data['created_at'] ?? $data['createdAt'] ?? null,
        );
    }
}
