<?php

declare(strict_types=1);

namespace App\Catalog\Infrastructure\Persistence\Doctrine\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use App\Catalog\Domain\Model\CategoryId;
use App\Catalog\Domain\Model\Product;
use App\Catalog\Domain\Model\ProductId;
use App\Catalog\Domain\Model\ProductImage;
use App\Catalog\Domain\Model\ProductName;
use App\Catalog\Domain\Model\SKU;
use App\Catalog\Domain\Model\Slug;
use App\Catalog\Domain\Model\Stock;
use App\Internationalization\Infrastructure\Persistence\Doctrine\Entity\Translation;
use App\Shared\Domain\ValueObject\Money;
use App\Shared\Domain\ValueObject\TenantId;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;
use Gedmo\Translatable\Translatable;

#[Gedmo\TranslationEntity(class: Translation::class)]
#[ORM\Entity]
#[ORM\Table(
    name: 'catalog_products',
    uniqueConstraints: [
        new ORM\UniqueConstraint(name: 'uniq_catalog_products_tenant_sku', columns: ['tenant_id', 'sku'])
    ]
)]
// Performance indexes for queries
#[ORM\Index(name: 'idx_products_tenant_id', columns: ['tenant_id'])]
#[ORM\Index(name: 'idx_products_tenant_sku', columns: ['tenant_id', 'sku'])]
#[ORM\Index(name: 'idx_products_tenant_slug', columns: ['tenant_id', 'slug'])]
#[ORM\Index(name: 'idx_products_tenant_active', columns: ['tenant_id', 'active'])]
#[ORM\Index(name: 'idx_products_tenant_active_created', columns: ['tenant_id', 'active', 'created_at'])]
#[ORM\Index(name: 'idx_products_category_id', columns: ['category_id'])]
#[ORM\Index(name: 'idx_products_created_at', columns: ['created_at'])]
#[ORM\Index(name: 'idx_products_price_amount', columns: ['price_amount'])]
#[ApiResource(
    operations: [
        new GetCollection(
            provider: \App\Catalog\Infrastructure\ApiPlatform\State\ProductCollectionProvider::class
        ),
        new Post(
            processor: \App\Catalog\Infrastructure\ApiPlatform\State\CreateProductProcessor::class
        ),
        new Get(
            provider: \App\Catalog\Infrastructure\ApiPlatform\State\ProductItemProvider::class
        ),
        new Patch(),
        new Delete()
    ]
)]
class ProductEntity
{
    #[ORM\Id]
    #[ORM\Column(type: 'string', length: 36)]
    private string $id;

    #[ORM\Column(type: 'string', length: 36, name: 'tenant_id')]
    private string $tenantId;

    #[ORM\Column(type: 'string', length: 50)]
    private string $sku;

    #[ORM\Column(type: 'string', length: 255)]
    #[Gedmo\Translatable]
    private string $name;

    #[ORM\Column(type: 'text', nullable: true)]
    #[Gedmo\Translatable]
    private ?string $description = null;

    #[ORM\Column(type: 'string', length: 500, nullable: true)]
    #[Gedmo\Translatable]
    private ?string $shortDescription = null;

    #[ORM\Column(type: 'string', length: 255)]
    #[Gedmo\Translatable]
    #[Gedmo\Slug(fields: ['name'])]
    private string $slug;

    #[ORM\Column(type: 'integer', name: 'price_amount')]
    private int $priceAmount;

    #[ORM\Column(type: 'string', length: 3, name: 'price_currency')]
    private string $priceCurrency;

    #[ORM\Column(type: 'string', length: 36, nullable: true, name: 'category_id')]
    private ?string $categoryId = null;

    #[ORM\Column(type: 'integer', name: 'stock_quantity')]
    private int $stockQuantity = 0;

    #[ORM\Column(type: 'boolean', name: 'track_inventory')]
    private bool $trackInventory = true;

    #[ORM\Column(type: 'boolean', name: 'allow_backorder')]
    private bool $allowBackorder = false;

    /**
     * @var array<int, array{url: string, position: int, isPrimary: bool}>
     */
    #[ORM\Column(type: 'json')]
    private array $images = [];

    #[ORM\Column(type: 'boolean')]
    private bool $active = true;

    #[ORM\Column(type: 'boolean', name: 'is_featured')]
    private bool $isFeatured = false;

    #[ORM\Column(type: 'datetime_immutable', name: 'created_at')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: 'datetime_immutable', name: 'updated_at')]
    private \DateTimeImmutable $updatedAt;

    #[Gedmo\Locale]
    private ?string $locale = null;

    public static function fromDomainModel(Product $product): self
    {
        $entity = new self();
        $entity->id = $product->id()->toString();
        $entity->tenantId = $product->tenantId()->toString();
        $entity->sku = $product->sku()->value();
        $entity->name = $product->name()->value();
        $entity->description = $product->description();
        $entity->shortDescription = $product->shortDescription();
        $entity->slug = $product->slug()->value();
        $entity->priceAmount = $product->price()->getAmount();
        $entity->priceCurrency = $product->price()->getCurrency()->getCurrencyCode();
        $entity->categoryId = $product->categoryId()?->toString();
        $entity->stockQuantity = $product->stock()->quantity();
        $entity->trackInventory = $product->stock()->trackInventory();
        $entity->allowBackorder = $product->stock()->allowBackorder();
        $entity->images = array_map(fn($img) => $img->toArray(), $product->images());
        $entity->active = $product->isActive();
        $entity->isFeatured = $product->isFeatured();
        $entity->createdAt = $product->createdAt();
        $entity->updatedAt = $product->updatedAt();

        return $entity;
    }

    public function toDomainModel(): Product
    {
        return Product::reconstituteFromPersistence(
            id: ProductId::fromString($this->id),
            tenantId: TenantId::fromString($this->tenantId),
            sku: SKU::fromString($this->sku),
            name: ProductName::fromString($this->name),
            description: $this->description,
            shortDescription: $this->shortDescription,
            slug: Slug::fromString($this->slug),
            price: Money::fromScalars(
                $this->priceAmount,
                $this->priceCurrency
            ),
            categoryId: $this->categoryId !== null
                ? CategoryId::fromString($this->categoryId)
                : null,
            stock: Stock::create(
                $this->stockQuantity,
                $this->trackInventory,
                $this->allowBackorder
            ),
            images: array_map(
                fn($data) => ProductImage::fromArray($data),
                $this->images
            ),
            active: $this->active,
            isFeatured: $this->isFeatured,
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

    public function getSku(): string
    {
        return $this->sku;
    }

    public function setSku(string $sku): void
    {
        $this->sku = $sku;
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

    public function getShortDescription(): ?string
    {
        return $this->shortDescription;
    }

    public function setShortDescription(?string $shortDescription): void
    {
        $this->shortDescription = $shortDescription;
    }

    public function getSlug(): string
    {
        return $this->slug;
    }

    public function getPriceAmount(): int
    {
        return $this->priceAmount;
    }

    public function setPriceAmount(int $priceAmount): void
    {
        $this->priceAmount = $priceAmount;
    }

    public function getPriceCurrency(): string
    {
        return $this->priceCurrency;
    }

    public function setPriceCurrency(string $priceCurrency): void
    {
        $this->priceCurrency = $priceCurrency;
    }

    public function getCategoryId(): ?string
    {
        return $this->categoryId;
    }

    public function setCategoryId(?string $categoryId): void
    {
        $this->categoryId = $categoryId;
    }

    public function getStockQuantity(): int
    {
        return $this->stockQuantity;
    }

    public function setStockQuantity(int $stockQuantity): void
    {
        $this->stockQuantity = $stockQuantity;
    }

    public function isTrackInventory(): bool
    {
        return $this->trackInventory;
    }

    public function setTrackInventory(bool $trackInventory): void
    {
        $this->trackInventory = $trackInventory;
    }

    public function isAllowBackorder(): bool
    {
        return $this->allowBackorder;
    }

    public function setAllowBackorder(bool $allowBackorder): void
    {
        $this->allowBackorder = $allowBackorder;
    }

    /**
     * @return array<int, array{url: string, position: int, isPrimary: bool}>
     */
    public function getImages(): array
    {
        return $this->images;
    }

    /**
     * @param array<int, array{url: string, position: int, isPrimary: bool}> $images
     */
    public function setImages(array $images): void
    {
        $this->images = $images;
    }

    public function isActive(): bool
    {
        return $this->active;
    }

    public function setActive(bool $active): void
    {
        $this->active = $active;
    }

    public function isFeatured(): bool
    {
        return $this->isFeatured;
    }

    public function setIsFeatured(bool $isFeatured): void
    {
        $this->isFeatured = $isFeatured;
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
