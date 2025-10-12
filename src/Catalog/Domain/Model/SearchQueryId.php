<?php

declare(strict_types=1);

namespace App\Catalog\Domain\Model;

use InvalidArgumentException;
use Symfony\Component\Uid\Ulid;

/**
 * Value Object for Search Query ID.
 */
final readonly class SearchQueryId
{
    private function __construct(
        private string $value
    ) {
        if (!Ulid::isValid($value)) {
            throw new InvalidArgumentException(sprintf('Invalid SearchQueryId: %s', $value));
        }
    }

    public static function generate(): self
    {
        return new self((string) new Ulid());
    }

    public static function fromString(string $value): self
    {
        return new self($value);
    }

    public function toString(): string
    {
        return $this->value;
    }

    public function equals(SearchQueryId $other): bool
    {
        return $this->value === $other->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
