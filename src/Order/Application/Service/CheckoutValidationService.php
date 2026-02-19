<?php

declare(strict_types=1);

namespace App\Order\Application\Service;

use App\Catalog\Domain\Model\ProductId;
use App\Catalog\Domain\Repository\ProductRepositoryInterface;
use App\Inventory\Domain\Repository\StockItemRepositoryInterface;
use App\Order\Domain\Exception\CheckoutValidationException;
use App\Shared\Domain\ValueObject\TenantId;

final readonly class CheckoutValidationService
{
    public function __construct(
        private ProductRepositoryInterface $productRepository,
        private StockItemRepositoryInterface $stockItemRepository,
    ) {
    }

    /**
     * Validate price freshness and stock availability for all order lines.
     *
     * Collects ALL issues before throwing, so the frontend can display
     * a complete diff to the user in a single round-trip.
     *
     * @param array<int, array{productId: string, productName: string, quantity: int, unitPriceAmount?: int, unitPriceCurrency?: string}> $lines
     *
     * @throws CheckoutValidationException when any prices have changed or stock is insufficient
     */
    public function validate(array $lines, TenantId $tenantId): void
    {
        /** @var array<int, array{productId: string, productName: string, oldPrice: int, newPrice: int, currency: string}> $priceChanges */
        $priceChanges = [];

        /** @var array<int, array{productId: string, productName: string, requestedQuantity: int, availableQuantity: int}> $stockIssues */
        $stockIssues = [];

        foreach ($lines as $line) {
            $productId = ProductId::fromString($line['productId']);

            // --- Price freshness check ---
            if (isset($line['unitPriceAmount'], $line['unitPriceCurrency'])) {
                $product = $this->productRepository->findById($productId);

                if (null === $product) {
                    // Product was deleted since it was added to cart
                    $priceChanges[] = [
                        'productId' => $line['productId'],
                        'productName' => $line['productName'],
                        'oldPrice' => $line['unitPriceAmount'],
                        'newPrice' => 0,
                        'currency' => $line['unitPriceCurrency'],
                    ];
                } else {
                    $currentPrice = $product->price();
                    $currentAmount = $currentPrice->getAmount();
                    $currentCurrency = $currentPrice->getCurrency()->getCurrencyCode();

                    if ($currentAmount !== $line['unitPriceAmount'] || $currentCurrency !== $line['unitPriceCurrency']) {
                        $priceChanges[] = [
                            'productId' => $line['productId'],
                            'productName' => $line['productName'],
                            'oldPrice' => $line['unitPriceAmount'],
                            'newPrice' => $currentAmount,
                            'currency' => $currentCurrency,
                        ];
                    }
                }
            }

            // --- Stock availability check ---
            $stockItems = $this->stockItemRepository->findByProduct($productId, $tenantId);

            $totalAvailable = 0;
            foreach ($stockItems as $stockItem) {
                $totalAvailable += $stockItem->calculateAvailable()->value();
            }

            if ($totalAvailable < $line['quantity']) {
                $stockIssues[] = [
                    'productId' => $line['productId'],
                    'productName' => $line['productName'],
                    'requestedQuantity' => $line['quantity'],
                    'availableQuantity' => $totalAvailable,
                ];
            }
        }

        if ([] !== $priceChanges || [] !== $stockIssues) {
            throw CheckoutValidationException::withIssues($priceChanges, $stockIssues);
        }
    }
}
