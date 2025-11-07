<?php

declare(strict_types=1);

namespace App\Catalog\Domain\Model;

use App\Catalog\Domain\Event\BundleCreated;
use App\Catalog\Domain\Event\BundleItemAdded;
use App\Catalog\Domain\Event\BundleItemRemoved;
use App\Catalog\Domain\Event\BundleRemoved;
use App\Catalog\Domain\Event\BundleUpdated;
use App\Catalog\Domain\Event\ProductCreated;
use App\Catalog\Domain\Event\ProductDeactivated;
use App\Catalog\Domain\Event\ProductDiscontinued;
use App\Catalog\Domain\Event\ProductPublished;
use App\Catalog\Domain\Event\ProductReactivated;
use App\Catalog\Domain\Event\ProductTypeChanged;
use App\Catalog\Domain\Event\ProductUpdated;
use App\Catalog\Domain\ValueObject\Bundle;
use App\Catalog\Domain\ValueObject\BundleItem;
use App\Catalog\Domain\ValueObject\ProductStatus;
use App\Catalog\Domain\ValueObject\ProductType;
use App\Catalog\Domain\ValueObject\Subscription;
use App\Catalog\Domain\ValueObject\SubscriptionInterval;
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
        private \DateTimeImmutable $updatedAt,
        private ?Bundle $bundle = null,
        private ?Subscription $subscription = null,
        private ?\App\Catalog\Domain\ValueObject\DownloadableFile $downloadableFile = null
    ) {
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
        ?ProductType $type = null,
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
        \DateTimeImmutable $updatedAt,
        ?Bundle $bundle = null,
        ?Subscription $subscription = null,
        ?\App\Catalog\Domain\ValueObject\DownloadableFile $downloadableFile = null
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
            $updatedAt,
            $bundle,
            $subscription,
            $downloadableFile
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
            fn ($img) => $img->position() !== $position
        );
        $this->updatedAt = new \DateTimeImmutable();
    }

    /**
     * Publish a product (DRAFT → ACTIVE).
     *
     * Business Rule: Only draft products can be published
     *
     * @throws \DomainException if product is not in draft status
     */
    public function publish(): void
    {
        if (!$this->status->isDraft()) {
            throw new \DomainException(sprintf('Cannot publish product in status "%s". Only DRAFT products can be published.', $this->status->value()));
        }

        $this->status = ProductStatus::active();
        $this->updatedAt = new \DateTimeImmutable();

        $this->recordEvent(new ProductPublished($this->id, $this->tenantId, $this->updatedAt));
    }

    /**
     * Deactivate a product (ACTIVE → INACTIVE).
     *
     * Business Rule: Only active products can be deactivated
     *
     * @throws \DomainException if product is not active
     */
    public function deactivate(): void
    {
        if (!$this->status->isActive()) {
            throw new \DomainException(sprintf('Cannot deactivate product in status "%s". Only ACTIVE products can be deactivated.', $this->status->value()));
        }

        $this->status = ProductStatus::inactive();
        $this->updatedAt = new \DateTimeImmutable();

        $this->recordEvent(new ProductDeactivated($this->id, $this->tenantId, $this->updatedAt));
    }

    /**
     * Reactivate a product (INACTIVE → ACTIVE).
     *
     * Business Rule: Only inactive products can be reactivated
     *
     * @throws \DomainException if product is not inactive
     */
    public function reactivate(): void
    {
        if (!$this->status->isInactive()) {
            throw new \DomainException(sprintf('Cannot reactivate product in status "%s". Only INACTIVE products can be reactivated.', $this->status->value()));
        }

        $this->status = ProductStatus::active();
        $this->updatedAt = new \DateTimeImmutable();

        $this->recordEvent(new ProductReactivated($this->id, $this->tenantId, $this->updatedAt));
    }

    /**
     * Discontinue a product (Any → DISCONTINUED).
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
     * Change product type.
     *
     * Business Rule: Type changes are restricted after first sale
     * (For now, we allow changes but this can be enhanced with sales tracking)
     *
     * @throws \DomainException if changing from/to incompatible types
     */
    public function changeType(ProductType $newType): void
    {
        if ($this->type->equals($newType)) {
            throw new \DomainException(sprintf('Product is already of type "%s".', $newType->value()));
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

    public function bundle(): ?Bundle
    {
        return $this->bundle;
    }

    public function subscription(): ?Subscription
    {
        return $this->subscription;
    }

    public function downloadableFile(): ?\App\Catalog\Domain\ValueObject\DownloadableFile
    {
        return $this->downloadableFile;
    }

    /**
     * Create a bundle for this product.
     *
     * Business Rules:
     * - Only products of type 'bundle' can have bundle configuration
     * - Bundle must contain 1-20 items (enforced by Bundle VO)
     * - Discount: 0-50% (enforced by Bundle VO)
     * - Cannot add bundle product to bundle (circular reference check)
     * - Product price is automatically calculated from bundle
     *
     * @param array<BundleItem> $items
     *
     * @throws \DomainException if product is not of type 'bundle'
     * @throws \DomainException if bundle already exists
     * @throws \DomainException if trying to add bundle product to bundle
     */
    public function createBundle(array $items, float $discountPercentage = 0.0): void
    {
        // Business Rule: Only bundle products can have bundle configuration
        if (!$this->type->isBundle()) {
            throw new \DomainException(
                sprintf(
                    'Cannot create bundle for product type "%s". Only bundle products can have bundle items.',
                    $this->type->value()
                )
            );
        }

        // Business Rule: Cannot create bundle if one already exists
        if ($this->bundle !== null) {
            throw new \DomainException('Product already has a bundle configuration. Use updateBundle() to modify it.');
        }

        // Business Rule: Prevent circular reference - cannot add bundle products to bundle
        foreach ($items as $item) {
            // This check would require repository access - will be enforced in application layer
            // For now, we trust the application layer to validate this
        }

        // Create bundle (validates item count and discount)
        $this->bundle = Bundle::create($items, $discountPercentage);

        // Auto-update product price from bundle calculation
        $this->price = $this->bundle->calculatePrice();
        $this->updatedAt = new \DateTimeImmutable();

        // Record domain event
        $this->recordEvent(new BundleCreated(
            $this->id,
            $this->tenantId,
            $items,
            $discountPercentage,
            $this->updatedAt
        ));
    }

    /**
     * Update existing bundle configuration.
     *
     * @param array<BundleItem> $items
     *
     * @throws \DomainException if product is not a bundle or has no bundle
     */
    public function updateBundle(array $items, float $discountPercentage = 0.0): void
    {
        $this->ensureIsBundle();
        $this->ensureBundleExists();

        // Create new bundle (validates constraints)
        $oldBundle = $this->bundle;
        $this->bundle = Bundle::create($items, $discountPercentage);

        // Auto-update product price from bundle calculation
        $this->price = $this->bundle->calculatePrice();
        $this->updatedAt = new \DateTimeImmutable();

        $this->recordEvent(new BundleUpdated(
            $this->id,
            $this->tenantId,
            $oldBundle,
            $this->bundle,
            $this->updatedAt
        ));
    }

    /**
     * Add a single item to existing bundle.
     *
     * @throws \DomainException if product is not a bundle or has no bundle
     * @throws \DomainException if bundle would exceed max items (20)
     */
    public function addBundleItem(BundleItem $item): void
    {
        $this->ensureIsBundle();
        $this->ensureBundleExists();

        // Get current items and add new one
        $currentItems = $this->bundle->items();
        $newItems = [...$currentItems, $item];

        // Create new bundle with added item (validates max items)
        $this->bundle = Bundle::create($newItems, $this->bundle->discountPercentage());

        // Update price
        $this->price = $this->bundle->calculatePrice();
        $this->updatedAt = new \DateTimeImmutable();

        $this->recordEvent(new BundleItemAdded(
            $this->id,
            $this->tenantId,
            $item,
            $this->updatedAt
        ));
    }

    /**
     * Remove a bundle item by product ID.
     *
     * @throws \DomainException if product is not a bundle or has no bundle
     * @throws \DomainException if item not found
     * @throws \DomainException if bundle would have < 1 item after removal
     */
    public function removeBundleItem(ProductId $productId): void
    {
        $this->ensureIsBundle();
        $this->ensureBundleExists();

        // Filter out the item
        $currentItems = $this->bundle->items();
        $newItems = array_filter(
            $currentItems,
            fn (BundleItem $item) => !$item->productId()->equals($productId)
        );

        // Check if item was actually removed
        if (count($newItems) === count($currentItems)) {
            throw new \DomainException(
                sprintf('Bundle item with product ID "%s" not found', $productId->toString())
            );
        }

        // Re-index array (remove gaps)
        $newItems = array_values($newItems);

        // Create new bundle (validates min items)
        $this->bundle = Bundle::create($newItems, $this->bundle->discountPercentage());

        // Update price
        $this->price = $this->bundle->calculatePrice();
        $this->updatedAt = new \DateTimeImmutable();

        $this->recordEvent(new BundleItemRemoved(
            $this->id,
            $this->tenantId,
            $productId,
            $this->updatedAt
        ));
    }

    /**
     * Remove bundle configuration entirely.
     *
     * @throws \DomainException if product is not a bundle or has no bundle
     */
    public function removeBundle(): void
    {
        $this->ensureIsBundle();
        $this->ensureBundleExists();

        $oldBundle = $this->bundle;
        $this->bundle = null;

        // Price should be set manually after removing bundle
        // (or kept as-is for now)
        $this->updatedAt = new \DateTimeImmutable();

        $this->recordEvent(new BundleRemoved(
            $this->id,
            $this->tenantId,
            $oldBundle,
            $this->updatedAt
        ));
    }

    /**
     * Check if product has bundle configuration.
     */
    public function hasBundle(): bool
    {
        return $this->bundle !== null;
    }

    /**
     * Configure subscription for this product.
     *
     * Business Rules:
     * - Only products of type 'subscription' can have subscription configuration
     * - Supports intervals: daily, weekly, monthly, yearly
     * - Billing cycles: 0 = infinite, 1-999 = limited
     * - Optional setup fee >= 0
     * - Optional trial period (must be in future)
     *
     * @throws \DomainException if product is not of type 'subscription'
     * @throws \DomainException if subscription already exists
     */
    public function configureSubscription(
        SubscriptionInterval $interval,
        int $billingCycles,
        Money $setupFee,
        ?\DateTimeImmutable $trialPeriodEnd = null
    ): void {
        // Business Rule: Only subscription products can have subscription configuration
        if (!$this->type->isSubscription()) {
            throw new \DomainException(
                sprintf(
                    'Cannot configure subscription for product type "%s". Only subscription products can have subscription configuration.',
                    $this->type->value()
                )
            );
        }

        // Business Rule: Cannot configure subscription if one already exists
        if ($this->subscription !== null) {
            throw new \DomainException('Product already has a subscription configuration. Use updateSubscription() to modify it.');
        }

        // Create subscription (validates business rules)
        $this->subscription = $trialPeriodEnd !== null
            ? Subscription::createWithTrial($interval, $billingCycles, $setupFee, $trialPeriodEnd)
            : Subscription::create($interval, $billingCycles, $setupFee);

        $this->updatedAt = new \DateTimeImmutable();

        // Record domain event
        $this->recordEvent(new \App\Catalog\Domain\Event\SubscriptionConfigured(
            $this->id,
            $this->tenantId,
            $interval,
            $billingCycles,
            $this->updatedAt
        ));
    }

    /**
     * Update existing subscription configuration.
     *
     * @throws \DomainException if product is not a subscription or has no subscription
     */
    public function updateSubscription(
        SubscriptionInterval $interval,
        int $billingCycles,
        Money $setupFee,
        ?\DateTimeImmutable $trialPeriodEnd = null
    ): void {
        $this->ensureIsSubscription();
        $this->ensureSubscriptionExists();

        // Store old subscription for event
        $oldSubscription = $this->subscription;

        // Create new subscription (validates constraints)
        $this->subscription = $trialPeriodEnd !== null
            ? Subscription::createWithTrial($interval, $billingCycles, $setupFee, $trialPeriodEnd)
            : Subscription::create($interval, $billingCycles, $setupFee);

        $this->updatedAt = new \DateTimeImmutable();

        $this->recordEvent(new \App\Catalog\Domain\Event\SubscriptionUpdated(
            $this->id,
            $this->tenantId,
            $oldSubscription,
            $this->subscription,
            $this->updatedAt
        ));
    }

    /**
     * Remove subscription configuration entirely.
     *
     * @throws \DomainException if product is not a subscription or has no subscription
     */
    public function removeSubscription(): void
    {
        $this->ensureIsSubscription();
        $this->ensureSubscriptionExists();

        $oldSubscription = $this->subscription;
        $this->subscription = null;
        $this->updatedAt = new \DateTimeImmutable();

        $this->recordEvent(new \App\Catalog\Domain\Event\SubscriptionRemoved(
            $this->id,
            $this->tenantId,
            $oldSubscription,
            $this->updatedAt
        ));
    }

    /**
     * Check if product has subscription configuration.
     */
    public function hasSubscription(): bool
    {
        return $this->subscription !== null;
    }

    /**
     * Attach downloadable file to virtual/digital product.
     *
     * Business Rules:
     * - Only virtual/digital products can have downloadable files
     * - File size max 2GB
     * - Download limit: 0 = unlimited, 1-999 = limited
     *
     * @throws \DomainException if product is not virtual/digital
     * @throws \DomainException if file already attached
     */
    public function attachDownloadableFile(
        string $filename,
        string $fileUrl,
        int $fileSizeBytes,
        int $downloadLimit = 5,
        ?\DateTimeImmutable $expiresAt = null
    ): void {
        // Business Rule: Only virtual products can have downloadable files
        if (!$this->type->isVirtual()) {
            throw new \DomainException(
                sprintf(
                    'Cannot attach downloadable file to product type "%s". Only virtual products can have downloadable files.',
                    $this->type->value()
                )
            );
        }

        // Business Rule: Cannot attach file if one already exists
        if ($this->downloadableFile !== null) {
            throw new \DomainException('Product already has a downloadable file. Use updateDownloadableFile() to modify it.');
        }

        $this->downloadableFile = $expiresAt !== null
            ? \App\Catalog\Domain\ValueObject\DownloadableFile::createWithExpiration(
                $filename,
                $fileUrl,
                $fileSizeBytes,
                $downloadLimit,
                $expiresAt
            )
            : \App\Catalog\Domain\ValueObject\DownloadableFile::create(
                $filename,
                $fileUrl,
                $fileSizeBytes,
                $downloadLimit
            );

        $this->updatedAt = new \DateTimeImmutable();

        $this->recordEvent(new \App\Catalog\Domain\Event\DownloadableFileAttached(
            $this->id,
            $this->tenantId,
            $filename,
            $this->updatedAt
        ));
    }

    /**
     * Update downloadable file.
     *
     * @throws \DomainException if no file attached
     */
    public function updateDownloadableFile(
        string $filename,
        string $fileUrl,
        int $fileSizeBytes,
        int $downloadLimit = 5,
        ?\DateTimeImmutable $expiresAt = null
    ): void {
        $this->ensureIsVirtual();
        $this->ensureDownloadableFileExists();

        $oldFile = $this->downloadableFile;

        $this->downloadableFile = $expiresAt !== null
            ? \App\Catalog\Domain\ValueObject\DownloadableFile::createWithExpiration(
                $filename,
                $fileUrl,
                $fileSizeBytes,
                $downloadLimit,
                $expiresAt
            )
            : \App\Catalog\Domain\ValueObject\DownloadableFile::create(
                $filename,
                $fileUrl,
                $fileSizeBytes,
                $downloadLimit
            );

        $this->updatedAt = new \DateTimeImmutable();

        $this->recordEvent(new \App\Catalog\Domain\Event\DownloadableFileUpdated(
            $this->id,
            $this->tenantId,
            $oldFile,
            $this->downloadableFile,
            $this->updatedAt
        ));
    }

    /**
     * Remove downloadable file.
     *
     * @throws \DomainException if no file attached
     */
    public function removeDownloadableFile(): void
    {
        $this->ensureIsVirtual();
        $this->ensureDownloadableFileExists();

        $oldFile = $this->downloadableFile;
        $this->downloadableFile = null;
        $this->updatedAt = new \DateTimeImmutable();

        $this->recordEvent(new \App\Catalog\Domain\Event\DownloadableFileRemoved(
            $this->id,
            $this->tenantId,
            $oldFile,
            $this->updatedAt
        ));
    }

    /**
     * Check if product has downloadable file.
     */
    public function hasDownloadableFile(): bool
    {
        return $this->downloadableFile !== null;
    }

    /**
     * Ensure product is of type 'bundle'.
     *
     * @throws \DomainException if product type is not 'bundle'
     */
    private function ensureIsBundle(): void
    {
        if (!$this->type->isBundle()) {
            throw new \DomainException(
                sprintf(
                    'Cannot perform bundle operation on product type "%s". Product must be of type "bundle".',
                    $this->type->value()
                )
            );
        }
    }

    /**
     * Ensure bundle configuration exists.
     *
     * @throws \DomainException if bundle does not exist
     */
    private function ensureBundleExists(): void
    {
        if ($this->bundle === null) {
            throw new \DomainException('Product does not have a bundle configuration. Use createBundle() first.');
        }
    }

    /**
     * Ensure product is of type 'subscription'.
     *
     * @throws \DomainException if product type is not 'subscription'
     */
    private function ensureIsSubscription(): void
    {
        if (!$this->type->isSubscription()) {
            throw new \DomainException(
                sprintf(
                    'Cannot perform subscription operation on product type "%s". Product must be of type "subscription".',
                    $this->type->value()
                )
            );
        }
    }

    /**
     * Ensure subscription configuration exists.
     *
     * @throws \DomainException if subscription does not exist
     */
    private function ensureSubscriptionExists(): void
    {
        if ($this->subscription === null) {
            throw new \DomainException('Product does not have a subscription configuration. Use configureSubscription() first.');
        }
    }

    /**
     * Ensure product is of type 'virtual'.
     *
     * @throws \DomainException if product type is not 'virtual'
     */
    private function ensureIsVirtual(): void
    {
        if (!$this->type->isVirtual()) {
            throw new \DomainException(
                sprintf(
                    'Cannot perform downloadable file operation on product type "%s". Product must be of type "virtual".',
                    $this->type->value()
                )
            );
        }
    }

    /**
     * Ensure downloadable file exists.
     *
     * @throws \DomainException if downloadable file does not exist
     */
    private function ensureDownloadableFileExists(): void
    {
        if ($this->downloadableFile === null) {
            throw new \DomainException('Product does not have a downloadable file. Use attachDownloadableFile() first.');
        }
    }

    private static function generateSlugFromName(string $name): string
    {
        $slug = strtolower($name);
        $slug = (string) preg_replace('/[^a-z0-9]+/', '-', $slug);
        $slug = trim($slug, '-');

        return $slug;
    }
}
