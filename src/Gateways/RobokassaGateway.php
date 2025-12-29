<?php

declare(strict_types=1);

namespace Yiisoft\Payments\Gateways;

use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Log\LoggerInterface;
use Yiisoft\Payments\Exceptions\PaymentException;
use Yiisoft\Payments\Models\Customer;
use Yiisoft\Payments\Models\PaymentIntent;
use Yiisoft\Payments\Models\PaymentMethod;

/**
 * Robokassa gateway implementation.
 *
 * Implemented APIs:
 * - Invoice API (JWT-based):
 *   - CreateInvoice:     https://services.robokassa.ru/InvoiceServiceWebApi/api/CreateInvoice
 *   - DeactivateInvoice: https://services.robokassa.ru/InvoiceServiceWebApi/api/DeactivateInvoice
 *
 * - Refund API v2 (JWT-based):
 *   - Create:            https://services.robokassa.ru/RefundService/Refund/Create
 *   - Status:            https://services.robokassa.ru/RefundService/Refund/Status
 *
 * - XML interface (status / OpKey):
 *   - OpStateExt:        https://auth.robokassa.ru/Merchant/WebService/Service.asmx/OpStateExt
 *
 * Important notes:
 * - Robokassa does not provide "customer" or "payment method" resources compatible with the common interface.
 *   Those methods are implemented as local no-ops (similar to PayPal gateway).
 * - Robokassa "payments" are invoice-based. createPaymentIntent() creates an invoice and returns a redirect URL
 *   in PaymentIntent::nextAction, so the payer can complete payment on Robokassa checkout.
 * - Refund API v2 requires Password#3 (separate secret). If it is not configured, createRefund() will throw.
 *
 * Amount format:
 * - Internally this library uses integer minor units (e.g. cents). Robokassa expects decimal strings (e.g. "10.00").
 * - This gateway converts between these representations assuming 2 decimal places.
 */
final class RobokassaGateway extends AbstractGateway
{
    private const INVOICE_API_BASE_URI = 'https://services.robokassa.ru/InvoiceServiceWebApi/api';
    private const REFUND_API_BASE_URI = 'https://services.robokassa.ru/RefundService/Refund';
    private const XML_API_BASE_URI = 'https://auth.robokassa.ru/Merchant/WebService/Service.asmx';

    public function __construct(
        private string $merchantLogin,
        private string $password1,
        private string $password2,
        private ?string $password3,
        private bool $testMode,
        ClientInterface $httpClient,
        RequestFactoryInterface $requestFactory,
        StreamFactoryInterface $streamFactory,
        ?LoggerInterface $logger = null
    ) {
        parent::__construct($httpClient, $requestFactory, $streamFactory, $logger);
    }

    /**
     * Robokassa invoice/refund APIs are absolute-URI based, so base URI is unused.
     * It is kept for compatibility with AbstractGateway, but should not be relied upon.
     */
    protected function getBaseUri(): string
    {
        return 'https://services.robokassa.ru';
    }

    // ---------------------------------------------------------------------
    // Customer and PaymentMethod operations (no-op placeholders)
    // ---------------------------------------------------------------------

    public function createCustomer(Customer $customer): Customer
    {
        if ($customer->id !== null) {
            return $customer;
        }

        return new Customer(
            id: 'robokassa_customer_' . bin2hex(random_bytes(8)),
            email: $customer->email,
            name: $customer->name,
            phone: $customer->phone,
            address: $customer->address,
            metadata: $customer->metadata,
            description: $customer->description,
        );
    }

    public function retrieveCustomer(string $customerId): Customer
    {
        return new Customer(id: $customerId);
    }

    public function updateCustomer(Customer $customer): Customer
    {
        return $customer;
    }

    public function deleteCustomer(string $customerId): void
    {
        // Intentionally no-op.
    }

    public function createPaymentMethod(PaymentMethod $paymentMethod): PaymentMethod
    {
        if ($paymentMethod->id !== null) {
            return $paymentMethod;
        }

        return new PaymentMethod(
            id: 'robokassa_payment_method_' . bin2hex(random_bytes(8)),
            type: $paymentMethod->type,
            details: $paymentMethod->details,
            customerId: $paymentMethod->customerId,
            billingDetails: $paymentMethod->billingDetails,
            metadata: $paymentMethod->metadata,
        );
    }

    public function retrievePaymentMethod(string $paymentMethodId): PaymentMethod
    {
        return new PaymentMethod(id: $paymentMethodId, type: 'robokassa');
    }

    public function updatePaymentMethod(PaymentMethod $paymentMethod): PaymentMethod
    {
        return $paymentMethod;
    }

    public function detachPaymentMethod(string $paymentMethodId): void
    {
        // Intentionally no-op.
    }

    public function attachPaymentMethod(string $paymentMethodId, string $customerId): PaymentMethod
    {
        return $this->retrievePaymentMethod($paymentMethodId);
    }

    // ---------------------------------------------------------------------
    // PaymentIntent operations
    // ---------------------------------------------------------------------

    /**
     * Creates a Robokassa invoice via Invoice API and returns a PaymentIntent containing:
     * - id: invoice ID (InvId)
     * - nextAction.redirect_to_url.url: payment URL (InvoiceUrl) if returned by API
     *
     * The gateway will populate invoice request fields using the provided PaymentIntent:
     * - OutSum: derived from $paymentIntent->amount and formatted as "10.00"
     * - Description: $paymentIntent->description (or a default)
     *
     * Additional Robokassa-specific invoice fields can be provided via metadata:
     * - InvoiceType, ExpirationDate, Culture, Email, SuccessUrl, FailUrl, ResultUrl, Receipt, etc.
     * All metadata is passed to Robokassa as-is (except reserved keys shown above).
     */
    public function createPaymentIntent(PaymentIntent $paymentIntent): PaymentIntent
    {
        $outSum = self::formatAmount($paymentIntent->amount);
        $description = $paymentIntent->description ?? 'Payment';

        $payload = $paymentIntent->metadata ?? [];

        $payload['MerchantLogin'] = $this->merchantLogin;
        $payload['OutSum'] = $payload['OutSum'] ?? $outSum;
        $payload['Description'] = $payload['Description'] ?? $description;

        // In test mode Robokassa expects IsTest=1.
        if ($this->testMode) {
            $payload['IsTest'] = 1;
        }

        $jwt = self::encodeJwt($payload, $this->password1);

        $response = $this->sendRawJsonRequest(
            'POST',
            self::INVOICE_API_BASE_URI . '/CreateInvoice',
            $jwt
        );

        $invoiceId = (string) ($response['InvoiceID'] ?? $response['InvId'] ?? $response['invoiceId'] ?? $response['invoice_id'] ?? '');
        $invoiceUrl = (string) ($response['InvoiceUrl'] ?? $response['invoiceUrl'] ?? $response['url'] ?? '');

        $nextAction = null;
        if ($invoiceUrl !== '') {
            $nextAction = [
                'type' => 'redirect_to_url',
                'redirect_to_url' => [
                    'url' => $invoiceUrl,
                ],
            ];
        }

        $meta = $paymentIntent->metadata ?? [];
        $meta['robokassa_invoice'] = $response;

        return PaymentIntent::fromArray([
            'id' => $invoiceId !== '' ? $invoiceId : ($paymentIntent->id ?? ''),
            'amount' => $paymentIntent->amount,
            'currency' => strtoupper($paymentIntent->currency),
            'status' => (string) ($response['Status'] ?? $response['status'] ?? 'CREATED'),
            'customer_id' => $paymentIntent->customerId,
            'payment_method_id' => $paymentIntent->paymentMethodId ?? 'robokassa',
            'metadata' => $meta,
            'description' => $paymentIntent->description,
            'receipt_email' => $paymentIntent->receiptEmail,
            'next_action' => $nextAction,
            'capture_method' => $paymentIntent->captureMethod,
        ]);
    }

    /**
     * Retrieves invoice status via XML API (OpStateExt).
     *
     * Returns:
     * - status: Robokassa state code (string) in metadata and mapped high-level status in PaymentIntent::status
     * - metadata.robokassa_op_key: operation key for refunds (when available)
     */
    public function retrievePaymentIntent(string $paymentIntentId): PaymentIntent
    {
        $xml = $this->sendXmlRequest('POST', self::XML_API_BASE_URI . '/OpStateExt', [
            'MerchantLogin' => $this->merchantLogin,
            'InvoiceID' => $paymentIntentId,
            'Signature' => md5($this->merchantLogin . ':' . $paymentIntentId . ':' . $this->password2),
        ]);

        $result = $xml->Result ?? null;
        $code = isset($result->Code) ? (int) $result->Code : null;
        if ($code !== 0) {
            $desc = isset($result->Description) ? (string) $result->Description : 'Robokassa OpStateExt error';
            throw new PaymentException($desc, (string) $code, 'robokassa', null, null, 400);
        }

        $stateCode = isset($xml->State->Code) ? (int) $xml->State->Code : null;
        $opKey = isset($xml->Info->OpKey) ? (string) $xml->Info->OpKey : null;

        return PaymentIntent::fromArray([
            'id' => $paymentIntentId,
            'status' => $this->mapStateToStatus($stateCode),
            'metadata' => [
                'robokassa_state_code' => $stateCode,
                'robokassa_op_key' => $opKey,
                'robokassa_raw' => $this->xmlToArray($xml),
            ],
        ]);
    }

    /**
     * For Robokassa, payer action happens on a hosted payment page.
     * This method simply re-fetches invoice state.
     */
    public function confirmPaymentIntent(string $paymentIntentId): PaymentIntent
    {
        return $this->retrievePaymentIntent($paymentIntentId);
    }

    /**
     * Robokassa does not support "capture" in the same way as card processors (it is invoice-based).
     * This method re-fetches invoice state.
     */
    public function capturePaymentIntent(string $paymentIntentId, array $params = []): PaymentIntent
    {
        return $this->retrievePaymentIntent($paymentIntentId);
    }

    /**
     * Attempts to deactivate an invoice via Invoice API.
     *
     * If the Invoice API call is not available/authorized, returns a best-effort local status.
     */
    public function cancelPaymentIntent(string $paymentIntentId): PaymentIntent
    {
        $payload = [
            'MerchantLogin' => $this->merchantLogin,
            'InvoiceID' => $paymentIntentId,
        ];

        if ($this->testMode) {
            $payload['IsTest'] = 1;
        }

        $jwt = self::encodeJwt($payload, $this->password1);

        try {
            $response = $this->sendRawJsonRequest(
                'POST',
                self::INVOICE_API_BASE_URI . '/DeactivateInvoice',
                $jwt
            );

            return PaymentIntent::fromArray([
                'id' => $paymentIntentId,
                'status' => (string) ($response['Status'] ?? $response['status'] ?? 'CANCELLED'),
                'metadata' => [
                    'robokassa_deactivate' => $response,
                ],
            ]);
        } catch (PaymentException $e) {
            return PaymentIntent::fromArray([
                'id' => $paymentIntentId,
                'status' => 'CANCELLED',
                'metadata' => [
                    'robokassa_cancel_error' => $e->getMessage(),
                ],
            ]);
        }
    }

    /**
     * Creates a refund using Refund API v2.
     *
     * Robokassa Refund API v2 operates with an operation key (OpKey), not an invoice ID directly.
     *
     * You can provide OpKey explicitly via $params['op_key'].
     * If it is not provided, the gateway will call OpStateExt to obtain it.
     *
     * @return array<string,mixed>
     */
    public function createRefund(string $paymentIntentId, int $amount = null, array $params = []): array
    {
        if ($this->password3 === null || $this->password3 === '') {
            throw new PaymentException(
                'Robokassa Refund API v2 requires Password#3 to be configured.',
                'robokassa_password3_required',
                'robokassa',
                null,
                null,
                500
            );
        }

        $opKey = $params['op_key'] ?? null;
        if ($opKey === null) {
            $intent = $this->retrievePaymentIntent($paymentIntentId);
            $opKey = $intent->metadata['robokassa_op_key'] ?? null;
        }

        if ($opKey === null || $opKey === '') {
            throw new PaymentException(
                'Cannot create Robokassa refund: OpKey is missing (provide params[\'op_key\'] or ensure invoice is paid).',
                'robokassa_op_key_missing',
                'robokassa',
                null,
                null,
                400
            );
        }

        $payload = [
            'MerchantLogin' => $this->merchantLogin,
            'OpKey' => (string) $opKey,
        ];

        if ($amount !== null) {
            $payload['RefundSum'] = self::formatAmount($amount);
        }

        // Allow passing additional Refund API parameters (e.g. InvoiceItems) via $params['refund'].
        if (isset($params['refund']) && is_array($params['refund'])) {
            $payload = array_merge($payload, $params['refund']);
        }

        $jwt = self::encodeJwt($payload, $this->password3);

        return $this->sendRawJsonRequest(
            'POST',
            self::REFUND_API_BASE_URI . '/Create',
            $jwt
        );
    }

    // ---------------------------------------------------------------------
    // Internal helpers
    // ---------------------------------------------------------------------

    /**
     * Sends a JWT-as-body request to an endpoint that returns JSON.
     *
     * The JWT is sent as a plain string body, without JSON encoding.
     *
     * @return array<string,mixed>
     */
    private function sendRawJsonRequest(string $method, string $url, string $jwt): array
    {
        $request = $this->requestFactory->createRequest($method, $url)
            ->withHeader('Content-Type', 'text/plain')
            ->withHeader('Accept', 'application/json');

        $request = $request->withBody($this->streamFactory->createStream($jwt));

        $response = $this->httpClient->sendRequest($request);
        $body = (string) $response->getBody();
        $data = json_decode($body, true);

        if (!is_array($data)) {
            throw new PaymentException(
                'Robokassa API returned a non-JSON response.',
                'robokassa_invalid_response',
                'robokassa',
                null,
                null,
                $response->getStatusCode()
            );
        }

        if ($response->getStatusCode() >= 400) {
            $this->handleErrorResponse($data, $response->getStatusCode());
        }

        // Some Robokassa endpoints return 200 even on logical failure.
        if (isset($data['success']) && $data['success'] === false) {
            $message = (string) ($data['message'] ?? 'Robokassa request failed.');
            throw new PaymentException(
                $message,
                (string) ($data['code'] ?? 'robokassa_error'),
                'robokassa',
                null,
                null,
                $response->getStatusCode()
            );
        }

        return $data;
    }

    /**
     * Sends form-encoded request to Robokassa XML API endpoint and parses XML into SimpleXMLElement.
     *
     * @param array<string,string> $fields
     */
    private function sendXmlRequest(string $method, string $url, array $fields): \SimpleXMLElement
    {
        $body = http_build_query($fields);

        $request = $this->requestFactory->createRequest($method, $url)
            ->withHeader('Content-Type', 'application/x-www-form-urlencoded')
            ->withHeader('Accept', 'text/xml');

        $request = $request->withBody($this->streamFactory->createStream($body));

        $response = $this->httpClient->sendRequest($request);
        $xmlString = (string) $response->getBody();

        $xml = @simplexml_load_string($xmlString);
        if ($xml === false) {
            throw new PaymentException(
                'Robokassa XML API returned invalid XML.',
                'robokassa_invalid_xml',
                'robokassa',
                null,
                null,
                $response->getStatusCode()
            );
        }

        return $xml;
    }

    private function mapStateToStatus(?int $stateCode): string
    {
        // Robokassa common state codes:
        // - 5: payment completed successfully (commonly used)
        // - other codes depend on merchant settings and workflow.
        if ($stateCode === 5) {
            return 'SUCCEEDED';
        }

        if ($stateCode === 0 || $stateCode === 1 || $stateCode === 2) {
            return 'PENDING';
        }

        if ($stateCode === 10 || $stateCode === 100) {
            return 'CANCELLED';
        }

        return 'UNKNOWN';
    }

    private function xmlToArray(\SimpleXMLElement $xml): array
    {
        return json_decode(json_encode($xml, JSON_THROW_ON_ERROR), true, 512, JSON_THROW_ON_ERROR);
    }

    private static function formatAmount(int $amountMinor): string
    {
        return number_format($amountMinor / 100, 2, '.', '');
    }

    private static function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    /**
     * Encodes a JWT using HS256 with the provided secret.
     *
     * @param array<string,mixed> $payload
     */
    private static function encodeJwt(array $payload, string $secret): string
    {
        $header = ['alg' => 'HS256', 'typ' => 'JWT'];

        $headerB64 = self::base64UrlEncode(json_encode($header, JSON_THROW_ON_ERROR));
        $payloadB64 = self::base64UrlEncode(json_encode($payload, JSON_THROW_ON_ERROR));

        $data = $headerB64 . '.' . $payloadB64;
        $signature = hash_hmac('sha256', $data, $secret, true);

        return $data . '.' . self::base64UrlEncode($signature);
    }
}
