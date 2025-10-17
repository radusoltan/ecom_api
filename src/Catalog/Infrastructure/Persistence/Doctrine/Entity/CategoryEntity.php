<?php

declare(strict_types=1);

namespace App\Catalog\Infrastructure\Persistence\Doctrine\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use App\Catalog\Domain\Model\Category;
use App\Catalog\Domain\Model\CategoryId;
use App\Catalog\Domain\Model\CategoryName;
use App\Catalog\Domain\Model\Slug;
use App\Catalog\Infrastructure\ApiPlatform\State\CreateCategoryProcessor;
use App\Catalog\Presentation\Api\Provider\CategoryCollectionProvider;
use App\Internationalization\Infrastructure\Persistence\Doctrine\Entity\Translation;
use App\Shared\Domain\ValueObject\TenantId;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;
use Gedmo\Translatable\Translatable;

#[Gedmo\TranslationEntity(class: Translation::class)]
#[ORM\Entity]
#[ORM\Table(name: 'catalog_categories')]
// Performance indexes for queries
#[ORM\Index(name: 'idx_categories_tenant_id', columns: ['tenant_id'])]
#[ORM\Index(name: 'idx_categories_tenant_active', columns: ['tenant_id', 'active'])]
#[ORM\Index(name: 'idx_categories_parent_position', columns: ['parent_id', 'position'])]
#[ORM\Index(name: 'idx_categories_slug', columns: ['slug'])]
#[ApiResource(
    shortName: 'Category',
    operations: [
        new GetCollection(
            provider: CategoryCollectionProvider::class,

        ),
        new Post(
            processor: CreateCategoryProcessor::class
        ),
        new Get(),
        new Patch(),
        new Delete()
    ]
)]
class CategoryEntity
{
    #[ORM\Id]
    #[ORM\Column(type: 'string', length: 36)]
    private string $id;

    #[ORM\Column(type: 'string', length: 36, name: 'tenant_id')]
    private string $tenantId;

    #[ORM\Column(type: 'string', length: 255)]
    #[Gedmo\Translatable]
    private string $name;

    #[ORM\Column(type: 'text', nullable: true)]
    #[Gedmo\Translatable]
    private ?string $description;

    #[ORM\Column(type: 'string', length: 255, unique: true)]
    #[Gedmo\Translatable]
    #[Gedmo\Slug(fields: ['name'])]
    private string $slug;

    #[ORM\Column(type: 'string', length: 36, nullable: true, name: 'parent_id')]
    private ?string $parentId;

    #[ORM\Column(type: 'integer')]
    private int $position;

    #[ORM\Column(type: 'boolean')]
    private bool $active;

    #[ORM\Column(type: 'boolean', name: 'show_on_front')]
    private bool $showOnFront = false;

    #[ORM\Column(type: 'string', length: 512, name: 'cover_image', nullable: true)]
    private ?string $coverImage = null;

    #[ORM\Column(type: 'datetime_immutable', name: 'created_at')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: 'datetime_immutable', name: 'updated_at')]
    private \DateTimeImmutable $updatedAt;

    #[Gedmo\Locale]
    private ?string $locale = null;

    public static function fromDomainModel(Category $category): self
    {
        $entity = new self();
        $entity->id = $category->id()->toString();
        $entity->tenantId = $category->tenantId()->toString();
        $entity->name = $category->name()->value();
        $entity->description = $category->description();
        $entity->slug = $category->slug()->value();
        $entity->parentId = $category->parentId()?->toString();
        $entity->position = $category->position();
        $entity->active = $category->isActive();
        $entity->showOnFront = $category->showOnFront();
        $entity->coverImage = $category->coverImage();
        $entity->createdAt = $category->createdAt();
        $entity->updatedAt = $category->updatedAt();

        return $entity;
    }

    public function toDomainModel(): Category
    {
        return Category::reconstituteFromPersistence(
            id: CategoryId::fromString($this->id),
            tenantId: TenantId::fromString($this->tenantId),
            name: CategoryName::fromString($this->name),
            description: $this->description,
            slug: Slug::fromString($this->slug),
            parentId: $this->parentId !== null ? CategoryId::fromString($this->parentId) : null,
            position: $this->position,
            active: $this->active,
            showOnFront: $this->showOnFront,
            coverImage: $this->coverImage,
            createdAt: $this->createdAt,
            updatedAt: $this->updatedAt
        );
    }

    // Getters and setters for API Platform
    public function getId(): string
    {
        return $this->id;
    }

    public function getTenantId(): string
    {
        return $this->tenantId;
    }

    public function setTenantId(string $tenantId): void
    {
        $this->tenantId = $tenantId;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): void
    {
        $this->description = $description;
    }

    public function getSlug(): string
    {
        return $this->slug;
    }

    public function getParentId(): ?string
    {
        return $this->parentId;
    }

    public function setParentId(?string $parentId): void
    {
        $this->parentId = $parentId;
    }

    public function getPosition(): int
    {
        return $this->position;
    }

    public function setPosition(int $position): void
    {
        $this->position = $position;
    }

    public function isActive(): bool
    {
        return $this->active;
    }

    public function setActive(bool $active): void
    {
        $this->active = $active;
    }

    public function getCoverImage(): ?string
    {
        return $this->coverImage;
    }

    public function setCoverImage(?string $coverImage): void
    {
        $this->coverImage = $coverImage;
    }

    public function isShowOnFront(): bool
    {
        return $this->showOnFront;
    }

    public function setShowOnFront(bool $showOnFront): void
    {
        $this->showOnFront = $showOnFront;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function setTranslatableLocale(?string $locale): void
    {
        $this->locale = $locale;
    }
}
