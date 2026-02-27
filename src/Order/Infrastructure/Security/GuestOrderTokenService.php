<?php

declare(strict_types=1);

namespace App\Order\Infrastructure\Security;

use Symfony\Component\DependencyInjection\Attribute\Autowire;

final readonly class GuestOrderTokenService
{
    public function __construct(
        #[Autowire('%kernel.secret%')]
        private string $secret,
    ) {
    }

    public function generateToken(string $orderId): string
    {
        return hash_hmac('sha256', $orderId, $this->secret);
    }

    public function validateToken(string $orderId, string $token): bool
    {
        return hash_equals($this->generateToken($orderId), $token);
    }
}
