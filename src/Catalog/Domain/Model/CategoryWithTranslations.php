<?php

declare(strict_types=1);

namespace App\Catalog\Domain\Model;

use App\Catalog\Domain\Event\CategoryCreated;
use App\Catalog\Domain\Event\CategoryTranslationsUpdated;
use App\Catalog\Domain\Event\CategoryUpdated;
use App\Catalog\Domain\Service\LocalizationPolicy;
use App\Catalog\Domain\ValueObject\Locale;
use App\Catalog\Domain\ValueObject\LocalizedString;
use App\Shared\Domain\Aggregate\AggregateRoot;
use App\Shared\Domain\ValueObject\TenantId;

/**
 * Category aggregate with JSONB-based translations support
 */
final class CategoryWithTranslations extends AggregateRoot
{
    private LocalizedString $nameTranslations;
    private LocalizedString $descriptionTranslations;

    private function __construct(
        private CategoryId $id,
        private TenantId $tenantId,
        private CategoryName $defaultName,
        private ?string $defaultDescription,
        private Slug $slug,
        private ?CategoryId $parentId,
        private int $position,
        private bool $active,
        private bool $showOnFront,
        private \DateTimeImmutable $createdAt,
        private \DateTimeImmutable $updatedAt,
        ?LocalizedString $nameTranslations = null,
        ?LocalizedString $descriptionTranslations = null
    ) {
        $this->nameTranslations = $nameTranslations ?? LocalizedString::empty();
        $this->descriptionTranslations = $descriptionTranslations ?? LocalizedString::empty();
    }

    public static function create(
        CategoryId $id,
        TenantId $tenantId,
        CategoryName $name,
        ?string $description,
        ?CategoryId $parentId,
        int $position = 0,
        bool $showOnFront = false,
        ?Locale $initialLocale = null
    ): self {
        $nameTranslations = LocalizedString::empty();
        $descriptionTranslations = LocalizedString::empty();

        // If locale provided, set initial translations
        if ($initialLocale !== null) {
            $nameTranslations = LocalizedString::fromSingleTranslation(
                $initialLocale->toString(),
                $name->value()
            );

            if ($description !== null) {
                $descriptionTranslations = LocalizedString::fromSingleTranslation(
                    $initialLocale->toString(),
                    $description
                );
            }
        }

        $category = new self(
            id: $id,
            tenantId: $tenantId,
            defaultName: $name,
            defaultDescription: $description,
            slug: Slug::fromString(self::generateSlugFromName($name->value())),
            parentId: $parentId,
            position: $position,
            active: true,
            showOnFront: $showOnFront,
            createdAt: new \DateTimeImmutable(),
            updatedAt: new \DateTimeImmutable(),
            nameTranslations: $nameTranslations,
            descriptionTranslations: $descriptionTranslations
        );

        $category->recordEvent(new CategoryCreated($id, $tenantId, $name->value()));

        return $category;
    }

    public static function reconstituteFromPersistence(
        CategoryId $id,
        TenantId $tenantId,
        CategoryName $defaultName,
        ?string $defaultDescription,
        Slug $slug,
        ?CategoryId $parentId,
        int $position,
        bool $active,
        bool $showOnFront,
        \DateTimeImmutable $createdAt,
        \DateTimeImmutable $updatedAt,
        LocalizedString $nameTranslations,
        LocalizedString $descriptionTranslations
    ): self {
        return new self(
            $id,
            $tenantId,
            $defaultName,
            $defaultDescription,
            $slug,
            $parentId,
            $position,
            $active,
            $showOnFront,
            $createdAt,
            $updatedAt,
            $nameTranslations,
            $descriptionTranslations
        );
    }

    /**
     * Update translations for a specific locale
     */
    public function updateTranslations(
        Locale $locale,
        ?string $name,
        ?string $description
    ): void {
        $changed = false;

        if ($name !== null) {
            $this->nameTranslations = $this->nameTranslations->withTranslation($locale, $name);
            $changed = true;
        }

        if ($description !== null) {
            $this->descriptionTranslations = $this->descriptionTranslations->withTranslation($locale, $description);
            $changed = true;
        }

        if ($changed) {
            $this->updatedAt = new \DateTimeImmutable();
            $this->recordEvent(new CategoryTranslationsUpdated($this->id, $this->tenantId, $locale));
        }
    }

    /**
     * Remove translations for a specific locale
     */
    public function removeTranslations(Locale $locale): void
    {
        $this->nameTranslations = $this->nameTranslations->withoutTranslation($locale);
        $this->descriptionTranslations = $this->descriptionTranslations->withoutTranslation($locale);

        $this->updatedAt = new \DateTimeImmutable();
        $this->recordEvent(new CategoryTranslationsUpdated($this->id, $this->tenantId, $locale));
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

    /**
     * Get slug for a specific locale
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

    /**
     * Get all translations as array for persistence
     */
    public function getTranslationsArray(): array
    {
        return [
            'name' => $this->nameTranslations->toArray(),
            'description' => $this->descriptionTranslations->toArray(),
        ];
    }

    // Keep existing methods for backward compatibility
    public function update(
        CategoryName $name,
        ?string $description,
        ?CategoryId $parentId,
        int $position,
        bool $showOnFront
    ): void {
        $this->defaultName = $name;
        $this->defaultDescription = $description;
        $this->parentId = $parentId;
        $this->position = $position;
        $this->showOnFront = $showOnFront;
        $this->updatedAt = new \DateTimeImmutable();

        $this->recordEvent(new CategoryUpdated($this->id, $this->tenantId));
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

    // Standard getters (backward compatible)
    public function id(): CategoryId
    {
        return $this->id;
    }

    public function tenantId(): TenantId
    {
        return $this->tenantId;
    }

    public function name(): CategoryName
    {
        return $this->defaultName;
    }

    public function description(): ?string
    {
        return $this->defaultDescription;
    }

    public function slug(): Slug
    {
        return $this->slug;
    }

    public function parentId(): ?CategoryId
    {
        return $this->parentId;
    }

    public function position(): int
    {
        return $this->position;
    }

    public function isActive(): bool
    {
        return $this->active;
    }

    public function showOnFront(): bool
    {
        return $this->showOnFront;
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