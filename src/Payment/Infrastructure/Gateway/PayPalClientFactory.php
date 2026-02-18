<?php

declare(strict_types=1);

namespace App\Payment\Infrastructure\Gateway;

use Psr\Log\LoggerInterface;

/**
 * Factory for creating PayPalClient instances.
 *
 * Centralizes PayPal client configuration and instantiation.
 * Uses Symfony dependency injection to provide configuration values.
 */
final class PayPalClientFactory
{
    public function __construct(
        private readonly string $clientId,
        private readonly string $clientSecret,
        private readonly string $environment,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * Create a configured PayPal client instance.
     */
    public function create(): PayPalClient
    {
        return new PayPalClient(
            $this->clientId,
            $this->clientSecret,
            $this->environment,
            $this->logger
        );
    }
}
