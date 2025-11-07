<?php

declare(strict_types=1);

namespace App\Catalog\Domain\Model;

use App\Catalog\Domain\Event\ProductCreated;
use App\Catalog\Domain\Event\ProductDeactivated;
use App\Catalog\Domain\Event\ProductDiscontinued;
use App\Catalog\Domain\Event\ProductPublished;
use App\Catalog\Domain\Event\ProductReactivated;
use App\Catalog\Domain\Event\ProductTypeChanged;
use App\Catalog\Domain\Event\ProductUpdated;
use App\Catalog\Domain\ValueObject\ProductStatus;
use App\Catalog\Domain\ValueObject\ProductType;
use App\Shared\Domain\Aggregate\AggregateRoot;
use App\Shared\Domain\ValueObject\Money;
use App\Shared\Domain\ValueObject\TenantId;

final class Product extends AggregateRoot
{
    /**
     * @param ProductImage[] $images
     */
    private function __construct(
        private ProductId $id,
        private TenantId $tenantId,
        private SKU $sku,
        private ProductName $name,
        private ?string $description,
        private ?string $shortDescription,
        private Slug $slug,
        private Money $price,
        private ?CategoryId $categoryId,
        private Stock $stock,
        private array $images,
        private ProductStatus $status,
        private ProductType $type,
        private bool $isFeatured,
        private \DateTimeImmutable $createdAt,
        private \DateTimeImmutable $updatedAt
    ) {}

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
        ProductType $type = null,
        bool $isFeatured = false
    ): self {
        $product = new self(
            id: $id,
            tenantId: $tenantId,
            sku: $sku,
            name: $name,
            description: $description,
            shortDescription: $shortDescription,
            slug: Slug::fromString(self::generateSlugFromName($name->value())),
            price: $price,
            categoryId: $categoryId,
            stock: $stock,
            images: [],
            status: ProductStatus::draft(), // New products start as DRAFT
            type: $type ?? ProductType::simple(), // Default to SIMPLE type
            isFeatured: $isFeatured,
            createdAt: new \DateTimeImmutable(),
            updatedAt: new \DateTimeImmutable()
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
        ProductName $name,
        ?string $description,
        ?string $shortDescription,
        Slug $slug,
        Money $price,
        ?CategoryId $categoryId,
        Stock $stock,
        array $images,
        ProductStatus $status,
        ProductType $type,
        bool $isFeatured,
        \DateTimeImmutable $createdAt,
        \DateTimeImmutable $updatedAt
    ): self {
        return new self(
            $id,
            $tenantId,
            $sku,
            $name,
            $description,
            $shortDescription,
            $slug,
            $price,
            $categoryId,
            $stock,
            $images,
            $status,
            $type,
            $isFeatured,
            $createdAt,
            $updatedAt
        );
    }

    public function update(
        ProductName $name,
        ?string $description,
        ?string $shortDescription,
        Money $price,
        ?CategoryId $categoryId,
        bool $isFeatured
    ): void {
        $this->name = $name;
        $this->description = $description;
        $this->shortDescription = $shortDescription;
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
            fn($img) => $img->position() !== $position
        );
        $this->updatedAt = new \DateTimeImmutable();
    }

    /**
     * Publish a product (DRAFT → ACTIVE)
     *
     * Business Rule: Only draft products can be published
     *
     * @throws \DomainException if product is not in draft status
     */
    public function publish(): void
    {
        if (!$this->status->isDraft()) {
            throw new \DomainException(
                sprintf('Cannot publish product in status "%s". Only DRAFT products can be published.', $this->status->value())
            );
        }

        $this->status = ProductStatus::active();
        $this->updatedAt = new \DateTimeImmutable();

        $this->recordEvent(new ProductPublished($this->id, $this->tenantId, $this->updatedAt));
    }

    /**
     * Deactivate a product (ACTIVE → INACTIVE)
     *
     * Business Rule: Only active products can be deactivated
     *
     * @throws \DomainException if product is not active
     */
    public function deactivate(): void
    {
        if (!$this->status->isActive()) {
            throw new \DomainException(
                sprintf('Cannot deactivate product in status "%s". Only ACTIVE products can be deactivated.', $this->status->value())
            );
        }

        $this->status = ProductStatus::inactive();
        $this->updatedAt = new \DateTimeImmutable();

        $this->recordEvent(new ProductDeactivated($this->id, $this->tenantId, $this->updatedAt));
    }

    /**
     * Reactivate a product (INACTIVE → ACTIVE)
     *
     * Business Rule: Only inactive products can be reactivated
     *
     * @throws \DomainException if product is not inactive
     */
    public function reactivate(): void
    {
        if (!$this->status->isInactive()) {
            throw new \DomainException(
                sprintf('Cannot reactivate product in status "%s". Only INACTIVE products can be reactivated.', $this->status->value())
            );
        }

        $this->status = ProductStatus::active();
        $this->updatedAt = new \DateTimeImmutable();

        $this->recordEvent(new ProductReactivated($this->id, $this->tenantId, $this->updatedAt));
    }

    /**
     * Discontinue a product (Any → DISCONTINUED)
     *
     * Business Rule: Any product can be discontinued, but it's permanent
     *
     * @throws \DomainException if product is already discontinued
     */
    public function discontinue(): void
    {
        if ($this->status->isDiscontinued()) {
            throw new \DomainException('Product is already discontinued.');
        }

        $this->status = ProductStatus::discontinued();
        $this->updatedAt = new \DateTimeImmutable();

        $this->recordEvent(new ProductDiscontinued($this->id, $this->tenantId, $this->updatedAt));
    }

    /**
     * @deprecated Use status()->isActive() instead
     */
    public function activate(): void
    {
        // Backward compatibility - delegate to reactivate if inactive, otherwise publish
        if ($this->status->isInactive()) {
            $this->reactivate();
        } elseif ($this->status->isDraft()) {
            $this->publish();
        }
    }

    public function isAvailable(int $quantity = 1): bool
    {
        return $this->status->isActive() && $this->stock->isAvailable($quantity);
    }

    /**
     * Change product type
     *
     * Business Rule: Type changes are restricted after first sale
     * (For now, we allow changes but this can be enhanced with sales tracking)
     *
     * @throws \DomainException if changing from/to incompatible types
     */
    public function changeType(ProductType $newType): void
    {
        if ($this->type->equals($newType)) {
            throw new \DomainException(
                sprintf('Product is already of type "%s".', $newType->value())
            );
        }

        $oldType = $this->type;
        $this->type = $newType;
        $this->updatedAt = new \DateTimeImmutable();

        $this->recordEvent(new ProductTypeChanged(
            $this->id,
            $this->tenantId,
            $oldType,
            $newType,
            $this->updatedAt
        ));
    }

    // Getters
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
        return $this->name;
    }

    public function description(): ?string
    {
        return $this->description;
    }

    public function shortDescription(): ?string
    {
        return $this->shortDescription;
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

    public function status(): ProductStatus
    {
        return $this->status;
    }

    public function type(): ProductType
    {
        return $this->type;
    }

    /**
     * @deprecated Use status()->isActive() instead
     */
    public function isActive(): bool
    {
        return $this->status->isActive();
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
