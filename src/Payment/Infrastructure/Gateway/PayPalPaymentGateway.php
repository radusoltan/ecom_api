<?php

declare(strict_types=1);

namespace App\Payment\Infrastructure\Gateway;

use App\Payment\Domain\Service\PaymentGatewayInterface;
use App\Payment\Domain\ValueObject\PaymentMethod;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * PayPal Payment Gateway Adapter.
 *
 * Implements payment operations using PayPal REST API.
 * Supports PayPal payments with authorize + capture flow.
 *
 * @see https://developer.paypal.com/docs/api/orders/v2/
 */
final readonly class PayPalPaymentGateway implements PaymentGatewayInterface
{
    private const API_BASE_URL_SANDBOX = 'https://api-m.sandbox.paypal.com';
    private const API_BASE_URL_PRODUCTION = 'https://api-m.paypal.com';

    private string $apiBaseUrl;

    public function __construct(
        private string $clientId,
        private string $clientSecret,
        private HttpClientInterface $httpClient,
        private LoggerInterface $logger,
        private bool $sandbox = true
    ) {
        $this->apiBaseUrl = $sandbox ? self::API_BASE_URL_SANDBOX : self::API_BASE_URL_PRODUCTION;
    }

    public function authorize(
        int $amountInCents,
        string $currency,
        PaymentMethod $method,
        array $metadata = []
    ): array {
        try {
            $this->logger->info('PayPal: Authorizing payment', [
                'amount' => $amountInCents,
                'currency' => $currency,
                'metadata' => $metadata,
            ]);

            $accessToken = $this->getAccessToken();
            $amountFormatted = number_format($amountInCents / 100, 2, '.', '');

            $response = $this->httpClient->request('POST', "{$this->apiBaseUrl}/v2/checkout/orders", [
                'headers' => [
                    'Authorization' => "Bearer {$accessToken}",
                    'Content-Type' => 'application/json',
                ],
                'json' => [
                    'intent' => 'AUTHORIZE', // Authorize only, capture later
                    'purchase_units' => [
                        [
                            'amount' => [
                                'currency_code' => $currency,
                                'value' => $amountFormatted,
                            ],
                            'custom_id' => $metadata['order_id'] ?? null,
                        ],
                    ],
                ],
            ]);

            $data = $response->toArray();
            $orderId = $data['id'];

            $this->logger->info('PayPal: Order created, authorization pending', [
                'order_id' => $orderId,
                'status' => $data['status'],
            ]);

            return [
                'transaction_id' => $orderId,
                'status' => strtolower($data['status']),
                'metadata' => [
                    'approval_url' => $this->getApprovalUrl($data['links'] ?? []),
                    'paypal_order_id' => $orderId,
                ],
            ];
        } catch (\Throwable $e) {
            $this->logger->error('PayPal: Authorization failed', [
                'error' => $e->getMessage(),
            ]);

            throw new \RuntimeException(sprintf('PayPal authorization failed: %s', $e->getMessage()), previous: $e);
        }
    }

    public function capture(string $transactionId, ?int $amountInCents = null): array
    {
        try {
            $this->logger->info('PayPal: Capturing payment', [
                'order_id' => $transactionId,
                'amount' => $amountInCents,
            ]);

            $accessToken = $this->getAccessToken();

            // First, get authorization ID from order
            $orderData = $this->getOrderDetails($transactionId, $accessToken);
            $authorizationId = $this->getAuthorizationId($orderData);

            // Capture the authorization
            $captureData = [];
            if (null !== $amountInCents) {
                $amountFormatted = number_format($amountInCents / 100, 2, '.', '');
                $captureData['amount'] = [
                    'currency_code' => $orderData['purchase_units'][0]['amount']['currency_code'],
                    'value' => $amountFormatted,
                ];
            }

            $response = $this->httpClient->request(
                'POST',
                "{$this->apiBaseUrl}/v2/payments/authorizations/{$authorizationId}/capture",
                [
                    'headers' => [
                        'Authorization' => "Bearer {$accessToken}",
                        'Content-Type' => 'application/json',
                    ],
                    'json' => $captureData,
                ]
            );

            $data = $response->toArray();

            $this->logger->info('PayPal: Payment captured successfully', [
                'capture_id' => $data['id'],
                'status' => $data['status'],
            ]);

            $capturedAmount = (int) round((float) $data['amount']['value'] * 100);

            return [
                'transaction_id' => $data['id'],
                'status' => strtolower($data['status']),
                'captured_amount' => $capturedAmount,
            ];
        } catch (\Throwable $e) {
            $this->logger->error('PayPal: Capture failed', [
                'order_id' => $transactionId,
                'error' => $e->getMessage(),
            ]);

            throw new \RuntimeException(sprintf('PayPal capture failed: %s', $e->getMessage()), previous: $e);
        }
    }

    public function refund(string $transactionId, int $amountInCents, string $reason): array
    {
        try {
            $this->logger->info('PayPal: Processing refund', [
                'capture_id' => $transactionId,
                'amount' => $amountInCents,
                'reason' => $reason,
            ]);

            $accessToken = $this->getAccessToken();
            $amountFormatted = number_format($amountInCents / 100, 2, '.', '');

            // Get capture details to find currency
            $captureData = $this->getCaptureDetails($transactionId, $accessToken);
            $currency = $captureData['amount']['currency_code'];

            $response = $this->httpClient->request(
                'POST',
                "{$this->apiBaseUrl}/v2/payments/captures/{$transactionId}/refund",
                [
                    'headers' => [
                        'Authorization' => "Bearer {$accessToken}",
                        'Content-Type' => 'application/json',
                    ],
                    'json' => [
                        'amount' => [
                            'currency_code' => $currency,
                            'value' => $amountFormatted,
                        ],
                        'note_to_payer' => $reason,
                    ],
                ]
            );

            $data = $response->toArray();

            $this->logger->info('PayPal: Refund processed successfully', [
                'refund_id' => $data['id'],
                'status' => $data['status'],
            ]);

            return [
                'refund_id' => $data['id'],
                'status' => strtolower($data['status']),
                'refunded_amount' => $amountInCents,
            ];
        } catch (\Throwable $e) {
            $this->logger->error('PayPal: Refund failed', [
                'capture_id' => $transactionId,
                'error' => $e->getMessage(),
            ]);

            throw new \RuntimeException(sprintf('PayPal refund failed: %s', $e->getMessage()), previous: $e);
        }
    }

    public function cancel(string $transactionId, string $reason): array
    {
        try {
            $this->logger->info('PayPal: Cancelling authorization', [
                'order_id' => $transactionId,
                'reason' => $reason,
            ]);

            $accessToken = $this->getAccessToken();

            // Get authorization ID from order
            $orderData = $this->getOrderDetails($transactionId, $accessToken);
            $authorizationId = $this->getAuthorizationId($orderData);

            // Void the authorization
            $this->httpClient->request(
                'POST',
                "{$this->apiBaseUrl}/v2/payments/authorizations/{$authorizationId}/void",
                [
                    'headers' => [
                        'Authorization' => "Bearer {$accessToken}",
                        'Content-Type' => 'application/json',
                    ],
                ]
            );

            $this->logger->info('PayPal: Authorization voided successfully', [
                'authorization_id' => $authorizationId,
            ]);

            return [
                'status' => 'voided',
            ];
        } catch (\Throwable $e) {
            $this->logger->error('PayPal: Cancellation failed', [
                'order_id' => $transactionId,
                'error' => $e->getMessage(),
            ]);

            throw new \RuntimeException(sprintf('PayPal cancellation failed: %s', $e->getMessage()), previous: $e);
        }
    }

    public function getStatus(string $transactionId): array
    {
        try {
            $accessToken = $this->getAccessToken();
            $orderData = $this->getOrderDetails($transactionId, $accessToken);

            $amount = (int) round((float) $orderData['purchase_units'][0]['amount']['value'] * 100);
            $currency = $orderData['purchase_units'][0]['amount']['currency_code'];

            return [
                'status' => strtolower($orderData['status']),
                'amount' => $amount,
                'currency' => $currency,
                'metadata' => [
                    'paypal_order_id' => $orderData['id'],
                    'create_time' => $orderData['create_time'] ?? null,
                ],
            ];
        } catch (\Throwable $e) {
            $this->logger->error('PayPal: Status retrieval failed', [
                'order_id' => $transactionId,
                'error' => $e->getMessage(),
            ]);

            throw new \RuntimeException(sprintf('PayPal status retrieval failed: %s', $e->getMessage()), previous: $e);
        }
    }

    public function getName(): string
    {
        return 'paypal';
    }

    /**
     * Get OAuth 2.0 access token from PayPal.
     */
    private function getAccessToken(): string
    {
        try {
            $response = $this->httpClient->request('POST', "{$this->apiBaseUrl}/v1/oauth2/token", [
                'auth_basic' => [$this->clientId, $this->clientSecret],
                'body' => [
                    'grant_type' => 'client_credentials',
                ],
            ]);

            $data = $response->toArray();

            return $data['access_token'];
        } catch (\Throwable $e) {
            throw new \RuntimeException('Failed to obtain PayPal access token: '.$e->getMessage(), previous: $e);
        }
    }

    /**
     * Get order details from PayPal.
     */
    private function getOrderDetails(string $orderId, string $accessToken): array
    {
        $response = $this->httpClient->request('GET', "{$this->apiBaseUrl}/v2/checkout/orders/{$orderId}", [
            'headers' => [
                'Authorization' => "Bearer {$accessToken}",
            ],
        ]);

        return $response->toArray();
    }

    /**
     * Get capture details from PayPal.
     */
    private function getCaptureDetails(string $captureId, string $accessToken): array
    {
        $response = $this->httpClient->request('GET', "{$this->apiBaseUrl}/v2/payments/captures/{$captureId}", [
            'headers' => [
                'Authorization' => "Bearer {$accessToken}",
            ],
        ]);

        return $response->toArray();
    }

    /**
     * Extract authorization ID from order data.
     */
    private function getAuthorizationId(array $orderData): string
    {
        if (!isset($orderData['purchase_units'][0]['payments']['authorizations'][0]['id'])) {
            throw new \RuntimeException('No authorization found in PayPal order');
        }

        return $orderData['purchase_units'][0]['payments']['authorizations'][0]['id'];
    }

    /**
     * Extract approval URL from links array.
     */
    private function getApprovalUrl(array $links): ?string
    {
        foreach ($links as $link) {
            if ('approve' === $link['rel']) {
                return $link['href'];
            }
        }

        return null;
    }
}
