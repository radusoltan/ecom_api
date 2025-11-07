<?php

declare(strict_types=1);

namespace App\Catalog\Domain\Model;

use App\Catalog\Domain\Event\OptionDefined;
use App\Catalog\Domain\Event\OptionRemoved;
use App\Catalog\Domain\Event\VariantAdded;
use App\Catalog\Domain\Event\VariantRemoved;
use App\Catalog\Domain\ValueObject\OptionCode;
use App\Shared\Domain\Aggregate\AggregateRoot;
use App\Shared\Domain\ValueObject\TenantId;

/**
 * ConfigurableProduct Aggregate Root
 * Represents a product with configurable options and variants.
 *
 * Business Rules:
 * - Must be associated with a base Product
 * - Option codes must be unique within the product
 * - Each variant must have exactly one value per option
 * - No duplicate variants with the same option-value combination
 * - Maximum 10 options per product
 * - Maximum 1000 variants per product
 *
 * Invariants:
 * - Options collection is internally consistent (no duplicate codes)
 * - Variants collection is internally consistent (no duplicate combinations)
 * - All variants must match the current option structure
 */
final class ConfigurableProduct extends AggregateRoot
{
    private const MAX_OPTIONS = 10;
    private const MAX_VARIANTS = 1000;

    /**
     * @param Option[]  $options
     * @param Variant[] $variants
     */
    private function __construct(
        private ConfigurableProductId $id,
        private ProductId $productId,
        private TenantId $tenantId,
        private array $options,
        private array $variants,
        private \DateTimeImmutable $createdAt,
        private \DateTimeImmutable $updatedAt
    ) {
        $this->validateOptions($options);
        $this->validateVariants($variants);
    }

    /**
     * Create a new configurable product.
     */
    public static function create(
        ConfigurableProductId $id,
        ProductId $productId,
        TenantId $tenantId
    ): self {
        return new self(
            id: $id,
            productId: $productId,
            tenantId: $tenantId,
            options: [],
            variants: [],
            createdAt: new \DateTimeImmutable(),
            updatedAt: new \DateTimeImmutable()
        );
    }

    /**
     * Reconstitute from persistence.
     *
     * @param Option[]  $options
     * @param Variant[] $variants
     */
    public static function reconstituteFromPersistence(
        ConfigurableProductId $id,
        ProductId $productId,
        TenantId $tenantId,
        array $options,
        array $variants,
        \DateTimeImmutable $createdAt,
        \DateTimeImmutable $updatedAt
    ): self {
        return new self(
            $id,
            $productId,
            $tenantId,
            $options,
            $variants,
            $createdAt,
            $updatedAt
        );
    }

    /**
     * Define a new option for this product.
     */
    public function defineOption(Option $option): void
    {
        // Check if option code already exists
        if ($this->hasOption($option->getCode())) {
            throw new \DomainException(sprintf('Option with code "%s" already exists', $option->getCode()->toString()));
        }

        // Check max options limit
        if (count($this->options) >= self::MAX_OPTIONS) {
            throw new \DomainException(sprintf('Cannot add more than %d options', self::MAX_OPTIONS));
        }

        $this->options[] = $option;
        $this->updatedAt = new \DateTimeImmutable();

        $this->recordEvent(new OptionDefined(
            $this->id,
            $this->productId,
            $option->getCode(),
            $option->getNameTranslations()
        ));
    }

    /**
     * Remove an option from this product
     * Warning: This will also remove all variants that use this option.
     */
    public function removeOption(OptionCode $optionCode): void
    {
        if (!$this->hasOption($optionCode)) {
            throw new \DomainException(sprintf('Option with code "%s" does not exist', $optionCode->toString()));
        }

        // Remove the option
        $this->options = array_filter(
            $this->options,
            fn (Option $opt) => !$opt->getCode()->equals($optionCode)
        );

        // Remove all variants that use this option
        $this->variants = array_filter(
            $this->variants,
            fn (Variant $variant) => !array_key_exists(
                $optionCode->value(),
                $variant->getOptionValueMap()
            )
        );

        $this->updatedAt = new \DateTimeImmutable();

        $this->recordEvent(new OptionRemoved($this->id, $this->productId, $optionCode));
    }

    /**
     * Add a variant to this product.
     */
    public function addVariant(Variant $variant): void
    {
        // Check max variants limit
        if (count($this->variants) >= self::MAX_VARIANTS) {
            throw new \DomainException(sprintf('Cannot add more than %d variants', self::MAX_VARIANTS));
        }

        // Validate variant matches current options
        $this->validateVariantMatchesOptions($variant);

        // Check for duplicate combination
        if ($this->hasDuplicateVariant($variant)) {
            throw new \DomainException('Variant with this option combination already exists');
        }

        $this->variants[] = $variant;
        $this->updatedAt = new \DateTimeImmutable();

        $this->recordEvent(new VariantAdded(
            $this->id,
            $this->productId,
            $variant->getId(),
            $variant->getSku(),
            $variant->getOptionValueMap()
        ));
    }

    /**
     * Remove a variant from this product.
     */
    public function removeVariant(VariantId $variantId): void
    {
        $initialCount = count($this->variants);

        $this->variants = array_filter(
            $this->variants,
            fn (Variant $v) => !$v->getId()->equals($variantId)
        );

        if (count($this->variants) === $initialCount) {
            throw new \DomainException('Variant not found');
        }

        $this->updatedAt = new \DateTimeImmutable();

        $this->recordEvent(new VariantRemoved($this->id, $this->productId, $variantId));
    }

    /**
     * Get option by code.
     */
    public function getOption(OptionCode $code): ?Option
    {
        foreach ($this->options as $option) {
            if ($option->getCode()->equals($code)) {
                return $option;
            }
        }

        return null;
    }

    /**
     * Check if option exists.
     */
    public function hasOption(OptionCode $code): bool
    {
        return null !== $this->getOption($code);
    }

    /**
     * Get variant by ID.
     */
    public function getVariant(VariantId $id): ?Variant
    {
        foreach ($this->variants as $variant) {
            if ($variant->getId()->equals($id)) {
                return $variant;
            }
        }

        return null;
    }

    /**
     * Find variant by option value combination.
     *
     * @param array<string, string> $optionValueMap
     */
    public function findVariantByCombination(array $optionValueMap): ?Variant
    {
        foreach ($this->variants as $variant) {
            if ($variant->matchesCombination($optionValueMap)) {
                return $variant;
            }
        }

        return null;
    }

    /**
     * Get all active variants.
     *
     * @return Variant[]
     */
    public function getActiveVariants(): array
    {
        return array_filter(
            $this->variants,
            fn (Variant $variant) => $variant->isActive()
        );
    }

    /**
     * Get variants sorted by position.
     *
     * @return Option[]
     */
    public function getSortedOptions(): array
    {
        $options = $this->options;
        usort($options, fn (Option $a, Option $b) => $a->getPosition() <=> $b->getPosition());

        return $options;
    }

    // Getters
    public function getId(): ConfigurableProductId
    {
        return $this->id;
    }

    public function getProductId(): ProductId
    {
        return $this->productId;
    }

    public function getTenantId(): TenantId
    {
        return $this->tenantId;
    }

    /**
     * @return Option[]
     */
    public function getOptions(): array
    {
        return $this->options;
    }

    /**
     * @return Variant[]
     */
    public function getVariants(): array
    {
        return $this->variants;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    /**
     * Validate options array.
     *
     * @param Option[] $options
     */
    private function validateOptions(array $options): void
    {
        $codes = [];
        foreach ($options as $option) {
            if (!$option instanceof Option) {
                throw new \InvalidArgumentException('All options must be Option instances');
            }

            $code = $option->getCode()->toString();
            if (isset($codes[$code])) {
                throw new \DomainException(sprintf('Duplicate option code "%s"', $code));
            }
            $codes[$code] = true;
        }
    }

    /**
     * Validate variants array.
     *
     * @param Variant[] $variants
     */
    private function validateVariants(array $variants): void
    {
        foreach ($variants as $variant) {
            if (!$variant instanceof Variant) {
                throw new \InvalidArgumentException('All variants must be Variant instances');
            }
        }
    }

    /**
     * Check if variant matches current option structure.
     */
    private function validateVariantMatchesOptions(Variant $variant): void
    {
        $variantOptions = array_keys($variant->getOptionValueMap());
        $productOptions = array_map(
            fn (Option $opt) => $opt->getCode()->value(),
            $this->options
        );

        sort($variantOptions);
        sort($productOptions);

        if ($variantOptions !== $productOptions) {
            throw new \DomainException('Variant option structure does not match product options');
        }
    }

    /**
     * Check if a duplicate variant exists.
     */
    private function hasDuplicateVariant(Variant $newVariant): bool
    {
        foreach ($this->variants as $existingVariant) {
            if ($existingVariant->matchesCombination($newVariant->getOptionValueMap())) {
                return true;
            }
        }

        return false;
    }
}

/**
 * Value object for ConfigurableProduct ID.
 */
final readonly class ConfigurableProductId
{
    private function __construct(
        private string $value
    ) {
        if (empty($value)) {
            throw new \InvalidArgumentException('ConfigurableProduct ID cannot be empty');
        }
    }

    public static function generate(): self
    {
        return new self(uniqid('cfgprod_', true));
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
