<?php

declare(strict_types=1);

namespace App\Payment\Infrastructure\Gateway;

use App\Payment\Domain\Service\PaymentGatewayInterface;
use App\Payment\Domain\ValueObject\PaymentMethod;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * 2Checkout Payment Gateway Adapter
 *
 * Implements payment operations using 2Checkout API (now Verifone).
 * Supports card payments with authorize + capture flow.
 *
 * @see https://knowledgecenter.2checkout.com/Documentation/API-Integration
 */
final readonly class TwoCheckoutPaymentGateway implements PaymentGatewayInterface
{
    // Note: 2Checkout no longer uses separate sandbox URL
    // Use production URL with demo parameter for testing
    private const API_BASE_URL = 'https://api.2checkout.com';

    public function __construct(
        private string $merchantCode,
        private string $privateKey,
        private string $secretKey,
        private HttpClientInterface $httpClient,
        private LoggerInterface $logger,
        private bool $sandbox = true
    ) {
    }

    public function authorize(
        int $amountInCents,
        string $currency,
        PaymentMethod $method,
        array $metadata = []
    ): array {
        try {
            $this->logger->info('2Checkout: Authorizing payment', [
                'amount' => $amountInCents,
                'currency' => $currency,
                'metadata' => $metadata,
            ]);

            $amountFormatted = number_format($amountInCents / 100, 2, '.', '');
            $date = gmdate('Y-m-d H:i:s');

            // Build request payload
            $payload = [
                'MerchantCode' => $this->merchantCode,
                'DateTime' => $date,
                'Currency' => $currency,
                'CustomerIP' => $metadata['customer_ip'] ?? '127.0.0.1',
                'ExternalReference' => $metadata['order_id'] ?? uniqid('order_', true),
                'Items' => [
                    [
                        'Code' => 'PAYMENT',
                        'Quantity' => 1,
                        'Price' => $amountFormatted,
                        'Name' => 'Order Payment',
                        'Description' => $metadata['description'] ?? 'Payment for order',
                    ],
                ],
                'BillingDetails' => [
                    'FirstName' => $metadata['billing']['first_name'] ?? 'John',
                    'LastName' => $metadata['billing']['last_name'] ?? 'Doe',
                    'CountryCode' => $metadata['billing']['country'] ?? 'US',
                    'State' => $metadata['billing']['state'] ?? '',
                    'City' => $metadata['billing']['city'] ?? 'New York',
                    'Address1' => $metadata['billing']['address'] ?? '123 Main St',
                    'Zip' => $metadata['billing']['zip'] ?? '10001',
                    'Email' => $metadata['billing']['email'] ?? 'customer@example.com',
                ],
                'PaymentDetails' => [
                    'Type' => 'TEST', // In sandbox mode
                    'Currency' => $currency,
                    'CustomerIP' => $metadata['customer_ip'] ?? '127.0.0.1',
                    'PaymentMethod' => $this->mapPaymentMethod($method),
                ],
            ];

            // Add demo parameter for sandbox mode
            if ($this->sandbox) {
                $payload['Demo'] = 'Y';
            }

            // Generate HMAC signature
            $signature = $this->generateSignature($payload, $date);

            $response = $this->httpClient->request('POST', self::API_BASE_URL . "/rest/6.0/orders/", [
                'headers' => [
                    'Content-Type' => 'application/json',
                    'X-Avangate-Authentication' => $signature,
                ],
                'json' => $payload,
            ]);

            $data = $response->toArray();

            $this->logger->info('2Checkout: Payment authorized successfully', [
                'reference_no' => $data['RefNo'] ?? null,
                'order_no' => $data['OrderNo'] ?? null,
            ]);

            return [
                'transaction_id' => $data['RefNo'] ?? uniqid('2co_', true),
                'status' => 'authorized',
                'metadata' => [
                    'order_no' => $data['OrderNo'] ?? null,
                    'approval_url' => $data['PaymentDetails']['PaymentLink'] ?? null,
                ],
            ];
        } catch (\Throwable $e) {
            $this->logger->error('2Checkout: Authorization failed', [
                'error' => $e->getMessage(),
            ]);

            throw new \RuntimeException(
                sprintf('2Checkout authorization failed: %s', $e->getMessage()),
                previous: $e
            );
        }
    }

    public function capture(string $transactionId, ?int $amountInCents = null): array
    {
        try {
            $this->logger->info('2Checkout: Capturing payment', [
                'transaction_id' => $transactionId,
                'amount' => $amountInCents,
            ]);

            // Note: 2Checkout auto-captures after authorization approval
            // This method retrieves the order status to confirm capture

            $date = gmdate('Y-m-d H:i:s');
            $endpoint = self::API_BASE_URL . "/rest/6.0/orders/{$transactionId}/";

            $signature = $this->generateGetSignature($endpoint, $date);

            $response = $this->httpClient->request('GET', $endpoint, [
                'headers' => [
                    'Content-Type' => 'application/json',
                    'X-Avangate-Authentication' => $signature,
                ],
            ]);

            $data = $response->toArray();

            $capturedAmount = 0;
            if (isset($data['GrossPrice'])) {
                $capturedAmount = (int) round((float) $data['GrossPrice'] * 100);
            }

            $this->logger->info('2Checkout: Payment status retrieved', [
                'transaction_id' => $transactionId,
                'status' => $data['Status'] ?? 'unknown',
                'captured_amount' => $capturedAmount,
            ]);

            return [
                'transaction_id' => $transactionId,
                'status' => strtolower($data['Status'] ?? 'captured'),
                'captured_amount' => $capturedAmount,
            ];
        } catch (\Throwable $e) {
            $this->logger->error('2Checkout: Capture failed', [
                'transaction_id' => $transactionId,
                'error' => $e->getMessage(),
            ]);

            throw new \RuntimeException(
                sprintf('2Checkout capture failed: %s', $e->getMessage()),
                previous: $e
            );
        }
    }

    public function refund(string $transactionId, int $amountInCents, string $reason): array
    {
        try {
            $this->logger->info('2Checkout: Processing refund', [
                'transaction_id' => $transactionId,
                'amount' => $amountInCents,
                'reason' => $reason,
            ]);

            $date = gmdate('Y-m-d H:i:s');
            $amountFormatted = number_format($amountInCents / 100, 2, '.', '');

            $payload = [
                'amount' => $amountFormatted,
                'comment' => $reason,
            ];

            $signature = $this->generateSignature($payload, $date);

            $response = $this->httpClient->request(
                'POST',
                self::API_BASE_URL . "/rest/6.0/orders/{$transactionId}/refund/",
                [
                    'headers' => [
                        'Content-Type' => 'application/json',
                        'X-Avangate-Authentication' => $signature,
                    ],
                    'json' => $payload,
                ]
            );

            $data = $response->toArray();

            $this->logger->info('2Checkout: Refund processed successfully', [
                'refund_id' => $data['RefNo'] ?? $transactionId,
                'status' => $data['Status'] ?? 'refunded',
            ]);

            return [
                'refund_id' => $data['RefNo'] ?? $transactionId,
                'status' => 'refunded',
                'refunded_amount' => $amountInCents,
            ];
        } catch (\Throwable $e) {
            $this->logger->error('2Checkout: Refund failed', [
                'transaction_id' => $transactionId,
                'error' => $e->getMessage(),
            ]);

            throw new \RuntimeException(
                sprintf('2Checkout refund failed: %s', $e->getMessage()),
                previous: $e
            );
        }
    }

    public function cancel(string $transactionId, string $reason): array
    {
        try {
            $this->logger->info('2Checkout: Cancelling payment', [
                'transaction_id' => $transactionId,
                'reason' => $reason,
            ]);

            $date = gmdate('Y-m-d H:i:s');

            $payload = [
                'Reason' => $reason,
            ];

            $signature = $this->generateSignature($payload, $date);

            $this->httpClient->request(
                'DELETE',
                self::API_BASE_URL . "/rest/6.0/orders/{$transactionId}/",
                [
                    'headers' => [
                        'Content-Type' => 'application/json',
                        'X-Avangate-Authentication' => $signature,
                    ],
                    'json' => $payload,
                ]
            );

            $this->logger->info('2Checkout: Payment cancelled successfully', [
                'transaction_id' => $transactionId,
            ]);

            return [
                'status' => 'cancelled',
            ];
        } catch (\Throwable $e) {
            $this->logger->error('2Checkout: Cancellation failed', [
                'transaction_id' => $transactionId,
                'error' => $e->getMessage(),
            ]);

            throw new \RuntimeException(
                sprintf('2Checkout cancellation failed: %s', $e->getMessage()),
                previous: $e
            );
        }
    }

    public function getStatus(string $transactionId): array
    {
        try {
            $date = gmdate('Y-m-d H:i:s');
            $endpoint = self::API_BASE_URL . "/rest/6.0/orders/{$transactionId}/";

            $signature = $this->generateGetSignature($endpoint, $date);

            $response = $this->httpClient->request('GET', $endpoint, [
                'headers' => [
                    'Content-Type' => 'application/json',
                    'X-Avangate-Authentication' => $signature,
                ],
            ]);

            $data = $response->toArray();

            $amount = 0;
            if (isset($data['GrossPrice'])) {
                $amount = (int) round((float) $data['GrossPrice'] * 100);
            }

            return [
                'status' => strtolower($data['Status'] ?? 'unknown'),
                'amount' => $amount,
                'currency' => $data['Currency'] ?? 'USD',
                'metadata' => [
                    'order_no' => $data['OrderNo'] ?? null,
                    'order_date' => $data['OrderDate'] ?? null,
                ],
            ];
        } catch (\Throwable $e) {
            $this->logger->error('2Checkout: Status retrieval failed', [
                'transaction_id' => $transactionId,
                'error' => $e->getMessage(),
            ]);

            throw new \RuntimeException(
                sprintf('2Checkout status retrieval failed: %s', $e->getMessage()),
                previous: $e
            );
        }
    }

    public function getName(): string
    {
        return 'twocheckout';
    }

    /**
     * Generate HMAC signature for POST/DELETE requests
     */
    private function generateSignature(array $payload, string $date): string
    {
        $serializedPayload = $this->serializePayload($payload);
        $stringToHash = strlen($serializedPayload) . $serializedPayload . $date;

        $hash = hash_hmac('md5', $stringToHash, $this->secretKey);

        return sprintf(
            'code="%s" date="%s" hash="%s"',
            $this->merchantCode,
            $date,
            $hash
        );
    }

    /**
     * Generate HMAC signature for GET requests
     */
    private function generateGetSignature(string $endpoint, string $date): string
    {
        // Extract path from endpoint
        $path = parse_url($endpoint, PHP_URL_PATH);
        $stringToHash = strlen($path) . $path . $date;

        $hash = hash_hmac('md5', $stringToHash, $this->secretKey);

        return sprintf(
            'code="%s" date="%s" hash="%s"',
            $this->merchantCode,
            $date,
            $hash
        );
    }

    /**
     * Serialize payload for HMAC calculation
     */
    private function serializePayload(array $payload): string
    {
        ksort($payload);
        $serialized = '';

        foreach ($payload as $key => $value) {
            if (is_array($value)) {
                $serialized .= $this->serializePayload($value);
            } else {
                // Convert all values to string for serialization
                $valueStr = (string) $value;
                $serialized .= strlen($valueStr) . $valueStr;
            }
        }

        return $serialized;
    }

    /**
     * Map PaymentMethod to 2Checkout payment method type
     */
    private function mapPaymentMethod(PaymentMethod $method): array
    {
        return match (true) {
            $method->isCard() => [
                'Type' => 'CC',
                'Vendor' => '2CO',
            ],
            default => throw new \InvalidArgumentException(
                sprintf('Payment method "%s" is not supported by 2Checkout gateway', $method->value())
            ),
        };
    }
}
