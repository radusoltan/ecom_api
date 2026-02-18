<?php

declare(strict_types=1);

namespace App\Catalog\Domain\Model;

use App\Catalog\Domain\ValueObject\LocalizedString;
use App\Catalog\Domain\ValueObject\OptionValueCode;

/**
 * OptionValue entity (child entity of Option)
 * Represents a specific value for an option (e.g., "Red" for Color option).
 *
 * Business Rules:
 * - Value code must be unique within an option
 * - Position determines display order
 * - Name must be translatable
 */
final class OptionValue
{
    private function __construct(
        private OptionValueId $id,
        private OptionValueCode $code,
        private LocalizedString $nameTranslations,
        private int $position,
    ) {
        $this->validatePosition($position);
    }

    public static function create(
        OptionValueId $id,
        OptionValueCode $code,
        LocalizedString $nameTranslations,
        int $position = 0,
    ): self {
        return new self($id, $code, $nameTranslations, $position);
    }

    /**
     * Reconstitute from persistence.
     */
    public static function reconstituteFromPersistence(
        OptionValueId $id,
        OptionValueCode $code,
        LocalizedString $nameTranslations,
        int $position,
    ): self {
        return new self($id, $code, $nameTranslations, $position);
    }

    /**
     * Update position.
     */
    public function updatePosition(int $position): void
    {
        $this->validatePosition($position);
        $this->position = $position;
    }

    /**
     * Update name translations.
     */
    public function updateNameTranslations(LocalizedString $nameTranslations): void
    {
        $this->nameTranslations = $nameTranslations;
    }

    // Getters
    public function getId(): OptionValueId
    {
        return $this->id;
    }

    public function getCode(): OptionValueCode
    {
        return $this->code;
    }

    public function getNameTranslations(): LocalizedString
    {
        return $this->nameTranslations;
    }

    public function getPosition(): int
    {
        return $this->position;
    }

    public function equals(self $other): bool
    {
        return $this->id->equals($other->id);
    }

    private function validatePosition(int $position): void
    {
        if ($position < 0) {
            throw new \InvalidArgumentException('Position must be non-negative');
        }
    }
}

/**
 * Value object for OptionValue ID.
 */
final readonly class OptionValueId
{
    private function __construct(
        private string $value,
    ) {
        if (empty($value)) {
            throw new \InvalidArgumentException('Option value ID cannot be empty');
        }
    }

    public static function generate(): self
    {
        return new self(uniqid('optval_', true));
    }

    public static function fromString(string $value): self
    {
        return new self($value);
    }

    public function toString(): string
    {
        return $this->value;
    }

    public function value(): string
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
