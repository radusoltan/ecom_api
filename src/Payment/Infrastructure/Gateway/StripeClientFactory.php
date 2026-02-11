<?php

declare(strict_types=1);

namespace App\Payment\Infrastructure\Gateway;

use Stripe\StripeClient;

/**
 * Factory for creating Stripe SDK client instances.
 *
 * This factory encapsulates Stripe client configuration to ensure consistent
 * API key and version usage across the application.
 */
final class StripeClientFactory
{
    public function __construct(
        private readonly string $apiKey,
        private readonly string $apiVersion
    ) {
    }

    /**
     * Create a configured Stripe client instance.
     */
    public function create(): StripeClient
    {
        return new StripeClient([
            'api_key' => $this->apiKey,
            'stripe_version' => $this->apiVersion,
        ]);
    }
}
