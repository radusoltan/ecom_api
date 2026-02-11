<?php

declare(strict_types=1);

namespace App\Cart\Application\Command;

use App\Cart\Domain\Exception\CartNotFoundException;
use App\Cart\Domain\Model\CartId;
use App\Cart\Domain\Repository\CartRepositoryInterface;
use App\Customer\Domain\ValueObject\CustomerId;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class AssignCartToCustomerHandler
{
    public function __construct(
        private CartRepositoryInterface $cartRepository
    ) {
    }

    /**
     * @throws CartNotFoundException
     * @throws \InvalidArgumentException
     */
    public function __invoke(AssignCartToCustomer $command): void
    {
        $cartId = CartId::fromString($command->cartId);
        $cart = $this->cartRepository->findById($cartId);

        if (null === $cart) {
            throw CartNotFoundException::withId($command->cartId);
        }

        // Issue #2: Validate cart is active before assignment
        if (!$cart->isActive()) {
            throw new \InvalidArgumentException('Cannot assign inactive cart to customer');
        }

        $cart->assignToCustomer(CustomerId::fromString($command->customerId));

        $this->cartRepository->save($cart);
    }
}
