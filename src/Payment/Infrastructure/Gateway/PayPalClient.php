<?php

declare(strict_types=1);

namespace App\Payment\Infrastructure\Gateway;

use Psr\Log\LoggerInterface;

/**
 * PayPal REST API Client.
 *
 * Uses OAuth 2.0 for authentication and PayPal Orders API v2.
 * No SDK dependency - direct REST calls for better control.
 *
 * PayPal API Documentation:
 * - Orders API: https://developer.paypal.com/docs/api/orders/v2/
 * - OAuth 2.0: https://developer.paypal.com/api/rest/authentication/
 * - Webhook Verification: https://developer.paypal.com/api/rest/webhooks/
 */
final class PayPalClient
{
    private const SANDBOX_URL = 'https://api-m.sandbox.paypal.com';
    private const LIVE_URL = 'https://api-m.paypal.com';

    private ?string $accessToken = null;
    private ?\DateTimeImmutable $tokenExpiry = null;

    public function __construct(
        private readonly string $clientId,
        private readonly string $clientSecret,
        private readonly string $environment,
        private readonly LoggerInterface $logger
    ) {
    }

    public function getBaseUrl(): string
    {
        return 'live' === $this->environment ? self::LIVE_URL : self::SANDBOX_URL;
    }

    /**
     * Get OAuth 2.0 access token (cached until expiry).
     *
     * Token is cached in memory to avoid excessive API calls.
     * Refreshed automatically 60 seconds before expiry.
     */
    public function getAccessToken(): string
    {
        if (null !== $this->accessToken && $this->tokenExpiry > new \DateTimeImmutable()) {
            return $this->accessToken;
        }

        $response = $this->request('POST', '/v1/oauth2/token', [
            'grant_type' => 'client_credentials',
        ], [
            'Authorization' => 'Basic '.base64_encode($this->clientId.':'.$this->clientSecret),
            'Content-Type' => 'application/x-www-form-urlencoded',
        ], true);

        if (!isset($response['access_token']) || !is_string($response['access_token'])) {
            throw new \RuntimeException('Invalid access token response from PayPal');
        }

        $this->accessToken = $response['access_token'];
        $this->tokenExpiry = (new \DateTimeImmutable())->modify('+'.((int) ($response['expires_in'] ?? 3600) - 60).' seconds');

        $this->logger->debug('PayPal access token obtained', [
            'expires_in' => $response['expires_in'] ?? 3600,
        ]);

        return $this->accessToken;
    }

    /**
     * Create PayPal order.
     *
     * @param array<string, mixed> $orderData Order creation payload
     *
     * @return array<string, mixed> Created order data
     */
    public function createOrder(array $orderData): array
    {
        return $this->request('POST', '/v2/checkout/orders', $orderData);
    }

    /**
     * Capture PayPal order after approval.
     *
     * @return array<string, mixed> Capture result data
     */
    public function captureOrder(string $orderId): array
    {
        return $this->request('POST', "/v2/checkout/orders/{$orderId}/capture");
    }

    /**
     * Get order details.
     *
     * @return array<string, mixed> Order details
     */
    public function getOrder(string $orderId): array
    {
        return $this->request('GET', "/v2/checkout/orders/{$orderId}");
    }

    /**
     * Create refund for a capture.
     *
     * @param array<string, mixed> $refundData Refund payload
     *
     * @return array<string, mixed> Refund result data
     */
    public function createRefund(string $captureId, array $refundData): array
    {
        return $this->request('POST', "/v2/payments/captures/{$captureId}/refund", $refundData);
    }

    /**
     * Verify webhook signature.
     *
     * @param array<string, string> $headers Request headers
     */
    public function verifyWebhookSignature(
        string $webhookId,
        array $headers,
        string $body
    ): bool {
        $verificationData = [
            'auth_algo' => $headers['PAYPAL-AUTH-ALGO'] ?? '',
            'cert_url' => $headers['PAYPAL-CERT-URL'] ?? '',
            'transmission_id' => $headers['PAYPAL-TRANSMISSION-ID'] ?? '',
            'transmission_sig' => $headers['PAYPAL-TRANSMISSION-SIG'] ?? '',
            'transmission_time' => $headers['PAYPAL-TRANSMISSION-TIME'] ?? '',
            'webhook_id' => $webhookId,
            'webhook_event' => json_decode($body, true),
        ];

        try {
            $result = $this->request('POST', '/v1/notifications/verify-webhook-signature', $verificationData);

            return ($result['verification_status'] ?? '') === 'SUCCESS';
        } catch (\Exception $e) {
            $this->logger->warning('PayPal webhook verification failed', ['error' => $e->getMessage()]);

            return false;
        }
    }

    /**
     * Make HTTP request to PayPal API.
     *
     * @param array<string, mixed>  $data    Request body data
     * @param array<string, string> $headers Additional headers
     *
     * @return array<string, mixed> Response data
     *
     * @throws \RuntimeException If request fails
     */
    private function request(
        string $method,
        string $path,
        array $data = [],
        array $headers = [],
        bool $isTokenRequest = false
    ): array {
        // Use cURL for HTTP requests
        $ch = curl_init();
        $url = $this->getBaseUrl().$path;

        $defaultHeaders = $isTokenRequest ? [] : [
            'Authorization' => 'Bearer '.$this->getAccessToken(),
            'Content-Type' => 'application/json',
        ];
        $headers = array_merge($defaultHeaders, $headers);

        if ('' === $url) {
            throw new \RuntimeException('PayPal API URL cannot be empty');
        }

        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $this->formatHeaders($headers));

        if ('POST' === $method) {
            curl_setopt($ch, CURLOPT_POST, true);
            $body = $isTokenRequest ? http_build_query($data) : json_encode($data);
            if (false !== $body) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
            }
        }

        $response = curl_exec($ch);
        $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if (!is_string($response)) {
            throw new \RuntimeException('PayPal API request failed: Invalid response');
        }

        if ('' !== $curlError) {
            $this->logger->error('PayPal cURL error', [
                'url' => $url,
                'error' => $curlError,
            ]);

            throw new \RuntimeException("PayPal API request failed: {$curlError}");
        }

        if ($statusCode >= 400) {
            $this->logger->error('PayPal API error', [
                'url' => $url,
                'status' => $statusCode,
                'response' => $response,
            ]);

            throw new \RuntimeException("PayPal API error: {$statusCode}");
        }

        return json_decode($response, true) ?? [];
    }

    /**
     * Format headers array for cURL.
     *
     * @param array<string, string> $headers
     *
     * @return array<int, string>
     */
    private function formatHeaders(array $headers): array
    {
        return array_map(
            fn ($key, $value) => "{$key}: {$value}",
            array_keys($headers),
            $headers
        );
    }
}
