<?php

declare(strict_types=1);

namespace App\Cart\Application\Command;

use App\Cart\Domain\Model\Cart;
use App\Cart\Domain\Model\CartId;
use App\Cart\Domain\Model\SessionId;
use App\Cart\Domain\Repository\CartRepositoryInterface;
use App\Customer\Domain\ValueObject\CustomerId;
use App\Shared\Domain\ValueObject\TenantId;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class CreateCartHandler
{
    public function __construct(
        private CartRepositoryInterface $cartRepository,
    ) {
    }

    public function __invoke(CreateCart $command): void
    {
        // Validate: either customerId or sessionId must be provided
        if (null === $command->customerId && null === $command->sessionId) {
            throw new \InvalidArgumentException('Either customerId or sessionId must be provided');
        }

        $tenantId = TenantId::fromString($command->tenantId);
        $customerId = null !== $command->customerId ? CustomerId::fromString($command->customerId) : null;
        $sessionId = null !== $command->sessionId ? SessionId::fromString($command->sessionId) : null;

        // Check if cart already exists for this customer/session
        if (null !== $customerId) {
            $existingCart = $this->cartRepository->findByCustomerId($customerId, $tenantId);
            if (null !== $existingCart) {
                // Cart already exists, no need to create a new one
                return;
            }
        } elseif (null !== $sessionId) {
            $existingCart = $this->cartRepository->findBySessionId($sessionId, $tenantId);
            if (null !== $existingCart) {
                // Cart already exists, no need to create a new one
                return;
            }
        }

        // Create new cart
        $cart = Cart::create(
            CartId::fromString($command->cartId),
            $tenantId,
            $customerId,
            $sessionId
        );

        $this->cartRepository->save($cart);
    }
}
