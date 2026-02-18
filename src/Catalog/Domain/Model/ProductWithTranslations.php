<?php

declare(strict_types=1);

namespace App\Catalog\Domain\Model;

use App\Catalog\Domain\Event\ProductCreated;
use App\Catalog\Domain\Event\ProductTranslationsUpdated;
use App\Catalog\Domain\Event\ProductUpdated;
use App\Catalog\Domain\Service\LocalizationPolicy;
use App\Catalog\Domain\ValueObject\Locale;
use App\Catalog\Domain\ValueObject\LocalizedString;
use App\Shared\Domain\Aggregate\AggregateRoot;
use App\Shared\Domain\ValueObject\Money;
use App\Shared\Domain\ValueObject\TenantId;

/**
 * Product aggregate with JSONB-based translations support
 * This extends the base Product with locale-aware functionality.
 */
final class ProductWithTranslations extends AggregateRoot
{
    private LocalizedString $nameTranslations;
    private LocalizedString $descriptionTranslations;
    private LocalizedString $shortDescriptionTranslations;

    /**
     * @param ProductImage[] $images
     */
    private function __construct(
        private ProductId $id,
        private TenantId $tenantId,
        private SKU $sku,
        private ProductName $defaultName,
        private ?string $defaultDescription,
        private ?string $defaultShortDescription,
        private Slug $slug,
        private Money $price,
        private ?CategoryId $categoryId,
        private Stock $stock,
        private array $images,
        private bool $active,
        private bool $isFeatured,
        private \DateTimeImmutable $createdAt,
        private \DateTimeImmutable $updatedAt,
        ?LocalizedString $nameTranslations = null,
        ?LocalizedString $descriptionTranslations = null,
        ?LocalizedString $shortDescriptionTranslations = null,
    ) {
        $this->nameTranslations = $nameTranslations ?? LocalizedString::empty();
        $this->descriptionTranslations = $descriptionTranslations ?? LocalizedString::empty();
        $this->shortDescriptionTranslations = $shortDescriptionTranslations ?? LocalizedString::empty();
    }

    public static function create(
        ProductId $id,
        TenantId $tenantId,
        SKU $sku,
        ProductName $name,
        ?string $description,
        ?string $shortDescription,
        Money $price,
        ?CategoryId $categoryId,
        Stock $stock,
        bool $isFeatured = false,
        ?Locale $initialLocale = null,
    ): self {
        $nameTranslations = LocalizedString::empty();
        $descriptionTranslations = LocalizedString::empty();
        $shortDescriptionTranslations = LocalizedString::empty();

        // If locale provided, set initial translations
        if (null !== $initialLocale) {
            $nameTranslations = LocalizedString::fromSingleTranslation(
                $initialLocale->toString(),
                $name->value()
            );

            if (null !== $description) {
                $descriptionTranslations = LocalizedString::fromSingleTranslation(
                    $initialLocale->toString(),
                    $description
                );
            }

            if (null !== $shortDescription) {
                $shortDescriptionTranslations = LocalizedString::fromSingleTranslation(
                    $initialLocale->toString(),
                    $shortDescription
                );
            }
        }

        $product = new self(
            id: $id,
            tenantId: $tenantId,
            sku: $sku,
            defaultName: $name,
            defaultDescription: $description,
            defaultShortDescription: $shortDescription,
            slug: Slug::fromString(self::generateSlugFromName($name->value())),
            price: $price,
            categoryId: $categoryId,
            stock: $stock,
            images: [],
            active: true,
            isFeatured: $isFeatured,
            createdAt: new \DateTimeImmutable(),
            updatedAt: new \DateTimeImmutable(),
            nameTranslations: $nameTranslations,
            descriptionTranslations: $descriptionTranslations,
            shortDescriptionTranslations: $shortDescriptionTranslations
        );

        $product->recordEvent(new ProductCreated($id, $tenantId, $sku, $name->value()));

        return $product;
    }

    /**
     * @param ProductImage[] $images
     */
    public static function reconstituteFromPersistence(
        ProductId $id,
        TenantId $tenantId,
        SKU $sku,
        ProductName $defaultName,
        ?string $defaultDescription,
        ?string $defaultShortDescription,
        Slug $slug,
        Money $price,
        ?CategoryId $categoryId,
        Stock $stock,
        array $images,
        bool $active,
        bool $isFeatured,
        \DateTimeImmutable $createdAt,
        \DateTimeImmutable $updatedAt,
        LocalizedString $nameTranslations,
        LocalizedString $descriptionTranslations,
        LocalizedString $shortDescriptionTranslations,
    ): self {
        return new self(
            $id,
            $tenantId,
            $sku,
            $defaultName,
            $defaultDescription,
            $defaultShortDescription,
            $slug,
            $price,
            $categoryId,
            $stock,
            $images,
            $active,
            $isFeatured,
            $createdAt,
            $updatedAt,
            $nameTranslations,
            $descriptionTranslations,
            $shortDescriptionTranslations
        );
    }

    /**
     * Update translations for a specific locale.
     */
    public function updateTranslations(
        Locale $locale,
        ?string $name,
        ?string $description,
        ?string $shortDescription,
    ): void {
        $changed = false;

        if (null !== $name) {
            $this->nameTranslations = $this->nameTranslations->withTranslation($locale, $name);
            $changed = true;
        }

        if (null !== $description) {
            $this->descriptionTranslations = $this->descriptionTranslations->withTranslation($locale, $description);
            $changed = true;
        }

        if (null !== $shortDescription) {
            $this->shortDescriptionTranslations = $this->shortDescriptionTranslations->withTranslation($locale, $shortDescription);
            $changed = true;
        }

        if ($changed) {
            $this->updatedAt = new \DateTimeImmutable();
            $this->recordEvent(new ProductTranslationsUpdated($this->id, $this->tenantId, $locale));
        }
    }

    /**
     * Remove translations for a specific locale.
     */
    public function removeTranslations(Locale $locale): void
    {
        $this->nameTranslations = $this->nameTranslations->withoutTranslation($locale);
        $this->descriptionTranslations = $this->descriptionTranslations->withoutTranslation($locale);
        $this->shortDescriptionTranslations = $this->shortDescriptionTranslations->withoutTranslation($locale);

        $this->updatedAt = new \DateTimeImmutable();
        $this->recordEvent(new ProductTranslationsUpdated($this->id, $this->tenantId, $locale));
    }

    // Locale-aware getters
    public function getName(Locale $locale, LocalizationPolicy $policy, ?Locale $tenantDefault = null): string
    {
        $resolved = $policy->resolveString($this->nameTranslations, $locale, $tenantDefault);

        return $resolved ?? $this->defaultName->value();
    }

    public function getDescription(Locale $locale, LocalizationPolicy $policy, ?Locale $tenantDefault = null): ?string
    {
        $resolved = $policy->resolveString($this->descriptionTranslations, $locale, $tenantDefault);

        return $resolved ?? $this->defaultDescription;
    }

    public function getShortDescription(Locale $locale, LocalizationPolicy $policy, ?Locale $tenantDefault = null): ?string
    {
        $resolved = $policy->resolveString($this->shortDescriptionTranslations, $locale, $tenantDefault);

        return $resolved ?? $this->defaultShortDescription;
    }

    /**
     * Get slug for a specific locale
     * Generated from localized name if available.
     */
    public function getSlug(Locale $locale, LocalizationPolicy $policy, ?Locale $tenantDefault = null): Slug
    {
        $localizedName = $this->getName($locale, $policy, $tenantDefault);

        // If we have a localized name different from default, generate localized slug
        if ($localizedName !== $this->defaultName->value()) {
            return Slug::fromString(self::generateSlugFromName($localizedName));
        }

        return $this->slug;
    }

    // Translation accessors
    public function getNameTranslations(): LocalizedString
    {
        return $this->nameTranslations;
    }

    public function getDescriptionTranslations(): LocalizedString
    {
        return $this->descriptionTranslations;
    }

    public function getShortDescriptionTranslations(): LocalizedString
    {
        return $this->shortDescriptionTranslations;
    }

    /**
     * Get all translations as array for persistence.
     */
    public function getTranslationsArray(): array
    {
        return [
            'name' => $this->nameTranslations->toArray(),
            'description' => $this->descriptionTranslations->toArray(),
            'short_description' => $this->shortDescriptionTranslations->toArray(),
        ];
    }

    // Keep existing methods for backward compatibility
    public function update(
        ProductName $name,
        ?string $description,
        ?string $shortDescription,
        Money $price,
        ?CategoryId $categoryId,
        bool $isFeatured,
    ): void {
        $this->defaultName = $name;
        $this->defaultDescription = $description;
        $this->defaultShortDescription = $shortDescription;
        $this->price = $price;
        $this->categoryId = $categoryId;
        $this->isFeatured = $isFeatured;
        $this->updatedAt = new \DateTimeImmutable();

        $this->recordEvent(new ProductUpdated($this->id, $this->tenantId));
    }

    public function updateStock(Stock $stock): void
    {
        $this->stock = $stock;
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function addImage(ProductImage $image): void
    {
        $this->images[] = $image;
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function removeImage(int $position): void
    {
        $this->images = array_filter(
            $this->images,
            fn ($img) => $img->position() !== $position
        );
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function activate(): void
    {
        $this->active = true;
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function deactivate(): void
    {
        $this->active = false;
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function isAvailable(int $quantity = 1): bool
    {
        return $this->active && $this->stock->isAvailable($quantity);
    }

    // Standard getters (backward compatible)
    public function id(): ProductId
    {
        return $this->id;
    }

    public function tenantId(): TenantId
    {
        return $this->tenantId;
    }

    public function sku(): SKU
    {
        return $this->sku;
    }

    public function name(): ProductName
    {
        return $this->defaultName;
    }

    public function description(): ?string
    {
        return $this->defaultDescription;
    }

    public function shortDescription(): ?string
    {
        return $this->defaultShortDescription;
    }

    public function slug(): Slug
    {
        return $this->slug;
    }

    public function price(): Money
    {
        return $this->price;
    }

    public function categoryId(): ?CategoryId
    {
        return $this->categoryId;
    }

    public function stock(): Stock
    {
        return $this->stock;
    }

    /**
     * @return ProductImage[]
     */
    public function images(): array
    {
        return $this->images;
    }

    public function isActive(): bool
    {
        return $this->active;
    }

    public function isFeatured(): bool
    {
        return $this->isFeatured;
    }

    public function createdAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function updatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    private static function generateSlugFromName(string $name): string
    {
        $slug = strtolower($name);
        $slug = (string) preg_replace('/[^a-z0-9]+/', '-', $slug);
        $slug = trim($slug, '-');

        return $slug;
    }
}
