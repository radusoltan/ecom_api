<?php

declare(strict_types=1);

namespace App\Catalog\Domain\Model;

use App\Catalog\Domain\Event\ProductCreated;
use App\Catalog\Domain\Event\ProductUpdated;
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
        private bool $active,
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
        Stock $stock
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
            active: true,
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
        bool $active,
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
            $active,
            $createdAt,
            $updatedAt
        );
    }

    public function update(
        ProductName $name,
        ?string $description,
        ?string $shortDescription,
        Money $price,
        ?CategoryId $categoryId
    ): void {
        $this->name = $name;
        $this->description = $description;
        $this->shortDescription = $shortDescription;
        $this->price = $price;
        $this->categoryId = $categoryId;
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

    public function isActive(): bool
    {
        return $this->active;
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
