<?php

declare(strict_types=1);

namespace App\Pricing\Domain\Model;

use Symfony\Component\Uid\Uuid;

/**
 * Value Object representing a unique identifier for a FlashSale aggregate.
 *
 * Uses UUID v4 for globally unique identification
 */
final readonly class FlashSaleId
{
    private function __construct(
        private string $value
    ) {
        if (!Uuid::isValid($this->value)) {
            throw new \InvalidArgumentException(sprintf('Invalid FlashSaleId: "%s" is not a valid UUID', $this->value));
        }
    }

    public static function generate(): self
    {
        return new self(Uuid::v4()->toRfc4122());
    }

    public static function fromString(string $value): self
    {
        return new self($value);
    }

    public function toString(): string
    {
        return $this->value;
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
