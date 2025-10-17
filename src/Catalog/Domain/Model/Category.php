<?php

declare(strict_types=1);

namespace App\Catalog\Domain\Model;

use App\Catalog\Domain\Event\CategoryCreated;
use App\Catalog\Domain\Event\CategoryUpdated;
use App\Shared\Domain\Aggregate\AggregateRoot;
use App\Shared\Domain\ValueObject\TenantId;

final class Category extends AggregateRoot
{
    private function __construct(
        private CategoryId $id,
        private TenantId $tenantId,
        private CategoryName $name,
        private ?string $description,
        private Slug $slug,
        private ?CategoryId $parentId,
        private int $position,
        private bool $active,
        private bool $showOnFront,
        private ?string $coverImage,
        private \DateTimeImmutable $createdAt,
        private \DateTimeImmutable $updatedAt
    ) {}

    /**
     * Factory method for creating new category
     */
    public static function create(
        CategoryId $id,
        TenantId $tenantId,
        CategoryName $name,
        ?string $description,
        ?CategoryId $parentId,
        int $position = 0,
        bool $showOnFront = false,
        ?string $coverImage = null
    ): self {
        $category = new self(
            id: $id,
            tenantId: $tenantId,
            name: $name,
            description: $description,
            slug: Slug::fromString(self::generateSlugFromName($name->value())),
            parentId: $parentId,
            position: $position,
            active: true,
            showOnFront: $showOnFront,
            coverImage: $coverImage,
            createdAt: new \DateTimeImmutable(),
            updatedAt: new \DateTimeImmutable()
        );

        $category->recordEvent(new CategoryCreated($id, $tenantId, $name->value()));

        return $category;
    }

    /**
     * Reconstitute from persistence
     */
    public static function reconstituteFromPersistence(
        CategoryId $id,
        TenantId $tenantId,
        CategoryName $name,
        ?string $description,
        Slug $slug,
        ?CategoryId $parentId,
        int $position,
        bool $active,
        bool $showOnFront,
        ?string $coverImage,
        \DateTimeImmutable $createdAt,
        \DateTimeImmutable $updatedAt
    ): self {
        return new self(
            $id,
            $tenantId,
            $name,
            $description,
            $slug,
            $parentId,
            $position,
            $active,
            $showOnFront,
            $coverImage,
            $createdAt,
            $updatedAt
        );
    }

    public function update(
        CategoryName $name,
        ?string $description,
        ?CategoryId $parentId,
        int $position,
        bool $showOnFront
    ): void {
        $this->name = $name;
        $this->description = $description;
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

    // Getters
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
        return $this->name;
    }

    public function description(): ?string
    {
        return $this->description;
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

    public function coverImage(): ?string
    {
        return $this->coverImage;
    }

    public function assignCoverImage(string $path): void
    {
        $this->coverImage = $path;
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function removeCoverImage(): void
    {
        $this->coverImage = null;
        $this->updatedAt = new \DateTimeImmutable();
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
