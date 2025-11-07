<?php

declare(strict_types=1);

namespace App\Cart\Application\Command;

use App\Cart\Application\Service\CartPriceCalculator;
use App\Cart\Application\Service\StockValidator;
use App\Cart\Domain\Exception\CartNotFoundException;
use App\Cart\Domain\Model\CartId;
use App\Cart\Domain\Model\Quantity;
use App\Cart\Domain\Repository\CartRepositoryInterface;
use App\Catalog\Domain\Model\ProductId;
use App\Shared\Domain\ValueObject\Money;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class AddItemToCartHandler
{
    public function __construct(
        private CartRepositoryInterface $cartRepository,
        private CartPriceCalculator $priceCalculator,
        private StockValidator $stockValidator
    ) {
    }

    public function __invoke(AddItemToCart $command): void
    {
        $cartId = CartId::fromString($command->cartId);
        $cart = $this->cartRepository->findById($cartId);

        if (null === $cart) {
            throw CartNotFoundException::withId($command->cartId);
        }

        $productId = ProductId::fromString($command->productId);

        // Step 1: Validate stock availability
        $this->stockValidator->validateStockAvailability(
            $productId,
            $command->variantId,
            $cart->tenantId(),
            $command->quantity
        );

        // Step 2: Determine price: use provided price or fetch current price
        if (null !== $command->unitPriceAmount && null !== $command->unitPriceCurrency) {
            // Price provided explicitly (backwards compatibility)
            $unitPrice = Money::fromScalars($command->unitPriceAmount, $command->unitPriceCurrency);
        } else {
            // Fetch current price from catalog/pricing
            $unitPrice = $this->priceCalculator->calculateItemPrice(
                $productId,
                $command->variantId,
                $cart->tenantId(),
                $cart->customerId()
            );
        }

        // Step 3: Add item to cart
        $cart->addItem(
            $productId,
            $command->variantId,
            Quantity::fromInt($command->quantity),
            $unitPrice
        );

        $this->cartRepository->save($cart);
    }
}
