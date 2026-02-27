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
use App\Catalog\Domain\ValueObject\ProductStatus;
use App\Catalog\Domain\ValueObject\ProductType;
use App\Internationalization\Infrastructure\Persistence\Doctrine\Entity\Translation;
use App\Shared\Domain\ValueObject\Money;
use App\Shared\Domain\ValueObject\TenantId;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;

#[Gedmo\TranslationEntity(class: Translation::class)]
#[ORM\Entity]
#[ORM\Table(name: 'catalog_products')]
#[ORM\UniqueConstraint(name: 'uniq_catalog_products_tenant_sku', columns: ['tenant_id', 'sku'])]
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
    shortName: 'Product',
    operations: [
        new GetCollection(
            uriTemplate: '/products',
            provider: \App\Catalog\Infrastructure\ApiPlatform\State\ProductCollectionProvider::class
        ),
        new Post(
            uriTemplate: '/products',
            processor: \App\Catalog\Infrastructure\ApiPlatform\State\CreateProductProcessor::class,
            security: "is_granted('ROLE_ADMIN') or is_granted('ROLE_MANAGER')"
        ),
        new Get(
            uriTemplate: '/products/{id}',
            provider: \App\Catalog\Infrastructure\ApiPlatform\State\ProductItemProvider::class
        ),
        new Patch(
            uriTemplate: '/products/{id}',
            security: "is_granted('ROLE_ADMIN') or is_granted('ROLE_MANAGER')"
        ),
        new Delete(
            uriTemplate: '/products/{id}',
            security: "is_granted('ROLE_ADMIN') or is_granted('ROLE_MANAGER')"
        ),
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

    #[ORM\Column(type: 'decimal', precision: 5, scale: 2, nullable: true, name: 'bundle_discount_percentage')]
    /** @phpstan-ignore property.unusedType, property.onlyWritten (DB column exists but unused - bundle data stored in bundle_items table) */
    private ?float $bundleDiscountPercentage = null;

    // Subscription fields
    #[ORM\Column(type: 'string', length: 20, nullable: true, name: 'subscription_interval')]
    private ?string $subscriptionInterval = null;

    #[ORM\Column(type: 'integer', nullable: true, name: 'subscription_billing_cycles')]
    private ?int $subscriptionBillingCycles = null;

    #[ORM\Column(type: 'integer', nullable: true, name: 'subscription_setup_fee_amount')]
    private ?int $subscriptionSetupFeeAmount = null;

    #[ORM\Column(type: 'string', length: 3, nullable: true, name: 'subscription_setup_fee_currency')]
    private ?string $subscriptionSetupFeeCurrency = null;

    #[ORM\Column(type: 'datetimetz_immutable', nullable: true, name: 'subscription_trial_end')]
    private ?\DateTimeImmutable $subscriptionTrialEnd = null;

    // Downloadable file fields (for virtual products)
    #[ORM\Column(type: 'string', length: 255, nullable: true, name: 'downloadable_filename')]
    private ?string $downloadableFilename = null;

    #[ORM\Column(type: 'string', length: 500, nullable: true, name: 'downloadable_url')]
    private ?string $downloadableUrl = null;

    #[ORM\Column(type: 'bigint', nullable: true, name: 'downloadable_size_bytes')]
    private ?int $downloadableSizeBytes = null;

    #[ORM\Column(type: 'integer', nullable: true, name: 'downloadable_limit')]
    private ?int $downloadableLimit = null;

    #[ORM\Column(type: 'datetimetz_immutable', nullable: true, name: 'downloadable_expires_at')]
    private ?\DateTimeImmutable $downloadableExpiresAt = null;

    #[ORM\Column(type: 'datetimetz_immutable', name: 'created_at')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: 'datetimetz_immutable', name: 'updated_at')]
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
        $entity->images = array_map(fn ($img) => $img->toArray(), $product->images());
        $entity->active = $product->isActive();
        $entity->isFeatured = $product->isFeatured();
        $entity->createdAt = $product->createdAt();
        $entity->updatedAt = $product->updatedAt();

        // Map subscription if present
        if ($product->hasSubscription()) {
            $subscription = $product->subscription();
            if (null !== $subscription) {
                $entity->subscriptionInterval = $subscription->interval()->value;
                $entity->subscriptionBillingCycles = $subscription->billingCycles();
                $entity->subscriptionSetupFeeAmount = $subscription->setupFee()->getAmount();
                $entity->subscriptionSetupFeeCurrency = $subscription->setupFee()->getCurrency()->getCurrencyCode();
                $entity->subscriptionTrialEnd = $subscription->trialPeriodEnd();
            }
        }

        // Map downloadable file if present
        if ($product->hasDownloadableFile()) {
            $file = $product->downloadableFile();
            if (null !== $file) {
                $entity->downloadableFilename = $file->filename();
                $entity->downloadableUrl = $file->fileUrl();
                $entity->downloadableSizeBytes = $file->fileSizeBytes();
                $entity->downloadableLimit = $file->downloadLimit();
                $entity->downloadableExpiresAt = $file->expiresAt();
            }
        }

        return $entity;
    }

    public function toDomainModel(): Product
    {
        // Reconstitute subscription if data exists
        $subscription = null;
        if (null !== $this->subscriptionInterval && null !== $this->subscriptionBillingCycles) {
            $setupFee = Money::fromScalars(
                $this->subscriptionSetupFeeAmount ?? 0,
                $this->subscriptionSetupFeeCurrency ?? 'USD'
            );

            $subscription = null !== $this->subscriptionTrialEnd
                ? \App\Catalog\Domain\ValueObject\Subscription::createWithTrial(
                    \App\Catalog\Domain\ValueObject\SubscriptionInterval::fromString($this->subscriptionInterval),
                    $this->subscriptionBillingCycles,
                    $setupFee,
                    $this->subscriptionTrialEnd
                )
                : \App\Catalog\Domain\ValueObject\Subscription::create(
                    \App\Catalog\Domain\ValueObject\SubscriptionInterval::fromString($this->subscriptionInterval),
                    $this->subscriptionBillingCycles,
                    $setupFee
                );
        }

        // Reconstitute downloadable file if data exists
        $downloadableFile = null;
        if (null !== $this->downloadableFilename && null !== $this->downloadableUrl && null !== $this->downloadableSizeBytes) {
            $downloadableFile = null !== $this->downloadableExpiresAt
                ? \App\Catalog\Domain\ValueObject\DownloadableFile::createWithExpiration(
                    $this->downloadableFilename,
                    $this->downloadableUrl,
                    $this->downloadableSizeBytes,
                    $this->downloadableLimit ?? 5,
                    $this->downloadableExpiresAt
                )
                : \App\Catalog\Domain\ValueObject\DownloadableFile::create(
                    $this->downloadableFilename,
                    $this->downloadableUrl,
                    $this->downloadableSizeBytes,
                    $this->downloadableLimit ?? 5
                );
        }

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
            categoryId: null !== $this->categoryId
                ? CategoryId::fromString($this->categoryId)
                : null,
            stock: Stock::create(
                $this->stockQuantity,
                $this->trackInventory,
                $this->allowBackorder
            ),
            images: array_map(
                fn ($data) => ProductImage::fromArray($data),
                $this->images
            ),
            status: $this->active ? ProductStatus::active() : ProductStatus::inactive(),
            type: ProductType::simple(), // TODO: Add product_type column to persist type
            isFeatured: $this->isFeatured,
            createdAt: $this->createdAt,
            updatedAt: $this->updatedAt,
            bundle: null, // TODO: Add bundle reconstitution when bundle_items table is populated
            subscription: $subscription,
            downloadableFile: $downloadableFile
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

    /**
     * HATEOAS navigation links for related resources.
     *
     * @return array<string, array{href: string}>
     */
    public function getLinks(): array
    {
        $links = [
            'self' => ['href' => '/api/v1/product_entities/' . $this->id],
            'variants' => ['href' => '/api/v1/variant_entities?productId=' . $this->id],
            'translations' => ['href' => '/api/v1/products/' . $this->id . '/translations'],
        ];

        if (null !== $this->categoryId) {
            $links['category'] = ['href' => '/api/v1/categories/' . $this->categoryId];
        }

        return $links;
    }
}
