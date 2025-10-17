<?php

declare(strict_types=1);

namespace App\Catalog\Domain\Model;

use App\Catalog\Domain\ValueObject\LocalizedString;
use App\Catalog\Domain\ValueObject\OptionCode;
use App\Catalog\Domain\ValueObject\OptionValueCode;

/**
 * Option entity for configurable products (e.g., Size, Color)
 * Part of the ConfigurableProduct aggregate
 */
final class Option
{
    /**
     * @param OptionValue[] $values
     */
    private function __construct(
        private OptionId $id,
        private OptionCode $code,
        private LocalizedString $nameTranslations,
        private int $position,
        private array $values
    ) {
        $this->validateValues($values);
    }

    /**
     * Factory method to create a new option
     * @param OptionValue[] $values
     */
    public static function create(
        OptionId $id,
        OptionCode $code,
        LocalizedString $nameTranslations,
        int $position = 0,
        array $values = []
    ): self {
        return new self($id, $code, $nameTranslations, $position, $values);
    }

    /**
     * Add a value to this option
     */
    public function addValue(OptionValue $value): void
    {
        // Check for duplicate value codes
        foreach ($this->values as $existingValue) {
            if ($existingValue->getCode()->equals($value->getCode())) {
                throw new \DomainException(
                    sprintf('Value with code "%s" already exists for option "%s"',
                        $value->getCode()->toString(),
                        $this->code->toString()
                    )
                );
            }
        }

        $this->values[] = $value;
    }

    /**
     * Remove a value from this option
     */
    public function removeValue(OptionValueCode $valueCode): void
    {
        $this->values = array_filter(
            $this->values,
            fn(OptionValue $value) => !$value->getCode()->equals($valueCode)
        );
    }

    /**
     * Update the position of this option
     */
    public function updatePosition(int $position): void
    {
        if ($position < 0) {
            throw new \InvalidArgumentException('Position must be non-negative');
        }
        $this->position = $position;
    }

    /**
     * Update translations for this option
     */
    public function updateNameTranslations(LocalizedString $nameTranslations): void
    {
        $this->nameTranslations = $nameTranslations;
    }

    /**
     * Get value by code
     */
    public function getValueByCode(OptionValueCode $code): ?OptionValue
    {
        foreach ($this->values as $value) {
            if ($value->getCode()->equals($code)) {
                return $value;
            }
        }
        return null;
    }

    /**
     * Check if this option has a specific value
     */
    public function hasValue(OptionValueCode $code): bool
    {
        return $this->getValueByCode($code) !== null;
    }

    /**
     * Get sorted values by position
     * @return OptionValue[]
     */
    public function getSortedValues(): array
    {
        $values = $this->values;
        usort($values, fn(OptionValue $a, OptionValue $b) => $a->getPosition() <=> $b->getPosition());
        return $values;
    }

    // Getters
    public function getId(): OptionId
    {
        return $this->id;
    }

    public function getCode(): OptionCode
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

    /**
     * @return OptionValue[]
     */
    public function getValues(): array
    {
        return $this->values;
    }

    /**
     * Validate values array
     * @param OptionValue[] $values
     */
    private function validateValues(array $values): void
    {
        $codes = [];
        foreach ($values as $value) {
            if (!$value instanceof OptionValue) {
                throw new \InvalidArgumentException('All values must be OptionValue instances');
            }

            $code = $value->getCode()->toString();
            if (isset($codes[$code])) {
                throw new \DomainException(
                    sprintf('Duplicate value code "%s" in option "%s"', $code, $this->code->toString())
                );
            }
            $codes[$code] = true;
        }
    }

    public function equals(self $other): bool
    {
        return $this->id->equals($other->id);
    }
}

/**
 * Value object for Option ID
 */
final class OptionId
{
    private function __construct(
        private readonly string $value
    ) {
        if (empty($value)) {
            throw new \InvalidArgumentException('Option ID cannot be empty');
        }
    }

    public static function generate(): self
    {
        return new self(uniqid('opt_', true));
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
}