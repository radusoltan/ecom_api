<?php

declare(strict_types=1);

namespace App\Order\Domain\Exception;

final class CheckoutValidationException extends \DomainException
{
    /** @var array<int, array{productId: string, productName: string, oldPrice: int, newPrice: int, currency: string}> */
    private array $priceChanges;

    /** @var array<int, array{productId: string, productName: string, requestedQuantity: int, availableQuantity: int}> */
    private array $stockIssues;

    /**
     * @param array<int, array{productId: string, productName: string, oldPrice: int, newPrice: int, currency: string}> $priceChanges
     * @param array<int, array{productId: string, productName: string, requestedQuantity: int, availableQuantity: int}> $stockIssues
     */
    public static function withIssues(array $priceChanges, array $stockIssues): self
    {
        $parts = [];

        if ([] !== $priceChanges) {
            $parts[] = sprintf('%d product(s) have changed price', count($priceChanges));
        }

        if ([] !== $stockIssues) {
            $parts[] = sprintf('%d product(s) have insufficient stock', count($stockIssues));
        }

        $exception = new self(
            sprintf('Checkout validation failed: %s', implode(', ', $parts)),
        );

        $exception->priceChanges = $priceChanges;
        $exception->stockIssues = $stockIssues;

        return $exception;
    }

    /**
     * @return array<int, array{productId: string, productName: string, oldPrice: int, newPrice: int, currency: string}>
     */
    public function priceChanges(): array
    {
        return $this->priceChanges;
    }

    /**
     * @return array<int, array{productId: string, productName: string, requestedQuantity: int, availableQuantity: int}>
     */
    public function stockIssues(): array
    {
        return $this->stockIssues;
    }

    public function hasPriceChanges(): bool
    {
        return [] !== $this->priceChanges;
    }

    public function hasStockIssues(): bool
    {
        return [] !== $this->stockIssues;
    }

    /**
     * @return array{error: string, message: string, priceChanges: array<int, array{productId: string, productName: string, oldPrice: int, newPrice: int, currency: string}>, stockIssues: array<int, array{productId: string, productName: string, requestedQuantity: int, availableQuantity: int}>}
     */
    public function toArray(): array
    {
        return [
            'error' => 'checkout_validation_failed',
            'message' => $this->getMessage(),
            'priceChanges' => $this->priceChanges,
            'stockIssues' => $this->stockIssues,
        ];
    }
}
