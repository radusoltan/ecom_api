<?php

declare(strict_types=1);

namespace App\Catalog\Infrastructure\Persistence\Doctrine\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use App\Catalog\Domain\Model\ProductImage;
use App\Catalog\Domain\Model\Stock;
use App\Catalog\Domain\Model\Variant;
use App\Catalog\Domain\Model\VariantId;
use App\Catalog\Domain\ValueObject\VariantSKU;
use App\Shared\Domain\ValueObject\Money;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Ignore;
use Symfony\Component\Serializer\Attribute\MaxDepth;

/**
 * Doctrine entity for Variant.
 */
#[ORM\Entity]
#[ORM\Table(name: 'catalog_product_variants')]
#[ORM\UniqueConstraint(name: 'uniq_product_variants_sku', columns: ['sku'])]
#[ORM\Index(name: 'idx_product_variants_product', columns: ['configurable_product_id'])]
#[ORM\Index(name: 'idx_product_variants_product_active', columns: ['configurable_product_id', 'is_active'])]
#[ApiResource(
    operations: [
        new GetCollection(
            provider: \App\Catalog\Infrastructure\ApiPlatform\State\VariantCollectionProvider::class
        ),
        new Post(
            processor: \App\Catalog\Infrastructure\ApiPlatform\State\CreateVariantProcessor::class,
            security: "is_granted('ROLE_ADMIN') or is_granted('ROLE_MANAGER')"
        ),
        new Get(
            provider: \App\Catalog\Infrastructure\ApiPlatform\State\VariantItemProvider::class
        ),
        new Patch(
            provider: \App\Catalog\Infrastructure\ApiPlatform\State\VariantItemProvider::class,
            processor: \App\Catalog\Infrastructure\ApiPlatform\State\UpdateVariantProcessor::class,
            security: "is_granted('ROLE_ADMIN') or is_granted('ROLE_MANAGER')"
        ),
        new Delete(
            provider: \App\Catalog\Infrastructure\ApiPlatform\State\VariantItemProvider::class,
            processor: \App\Catalog\Infrastructure\ApiPlatform\State\DeleteVariantProcessor::class,
            security: "is_granted('ROLE_ADMIN') or is_granted('ROLE_MANAGER')"
        ),
    ]
)]
class VariantEntity
{
    #[ORM\Id]
    #[ORM\Column(type: 'string', length: 36)]
    private string $id;

    #[ORM\ManyToOne(targetEntity: ConfigurableProductEntity::class, inversedBy: 'variants')]
    #[ORM\JoinColumn(name: 'configurable_product_id', nullable: false, onDelete: 'CASCADE')]
    #[Ignore] // Prevent circular reference during serialization (Variant -> ConfigurableProduct -> Variants)
    #[MaxDepth(1)] // Additional safeguard to limit recursion depth
    private ?ConfigurableProductEntity $configurableProduct = null;

    #[ORM\Column(type: 'string', length: 64)]
    private string $sku;

    #[ORM\Column(type: 'json', name: 'option_value_map')]
    /** @var array<string, mixed> */
    private array $optionValueMap = [];

    #[ORM\Column(type: 'bigint', name: 'price_amount')]
    private int $priceAmount;

    #[ORM\Column(type: 'string', length: 3, name: 'price_currency')]
    private string $priceCurrency;

    #[ORM\Column(type: 'integer', name: 'stock_on_hand')]
    private int $stockOnHand = 0;

    #[ORM\Column(type: 'integer', name: 'stock_reserved')]
    private int $stockReserved = 0;

    #[ORM\Column(type: 'boolean', name: 'track_inventory')]
    private bool $trackInventory = true;

    #[ORM\Column(type: 'boolean', name: 'allow_backorder')]
    private bool $allowBackorder = false;

    #[ORM\Column(type: 'boolean', name: 'is_active')]
    private bool $isActive = true;

    #[ORM\Column(type: 'json')]
    private array $images = [];

    #[ORM\Column(type: 'datetime_immutable', name: 'created_at')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: 'datetime_immutable', name: 'updated_at')]
    private \DateTimeImmutable $updatedAt;

    /**
     * Temporary field to receive productId in API requests
     * Not persisted to database.
     */
    public ?string $productId = null;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
    }

    /**
     * Create entity from domain model.
     */
    public static function fromDomainModel(Variant $variant): self
    {
        $entity = new self();
        $entity->id = $variant->getId()->toString();
        $entity->sku = $variant->getSku()->toString();
        $entity->optionValueMap = $variant->getOptionValueMap();
        $entity->priceAmount = $variant->getPrice()->getAmount();
        $entity->priceCurrency = $variant->getPrice()->getCurrency()->getCurrencyCode();
        $entity->stockOnHand = $variant->getStock()->quantity();
        $entity->stockReserved = 0; // Reserved stock is managed separately in inventory context
        $entity->trackInventory = $variant->getStock()->trackInventory();
        $entity->allowBackorder = $variant->getStock()->allowBackorder();
        $entity->isActive = $variant->isActive();
        $entity->images = array_map(fn ($img) => $img->toArray(), $variant->getImages());

        return $entity;
    }

    /**
     * Convert to domain model.
     */
    public function toDomainModel(): Variant
    {
        // Calculate available stock (on_hand - reserved)
        $availableStock = max(0, $this->stockOnHand - $this->stockReserved);

        return Variant::reconstituteFromPersistence(
            VariantId::fromString($this->id),
            VariantSKU::fromString($this->sku),
            $this->optionValueMap,
            Money::fromScalars($this->priceAmount, $this->priceCurrency),
            Stock::create($availableStock, $this->trackInventory, $this->allowBackorder),
            array_map(fn ($data) => ProductImage::fromArray($data), $this->images),
            $this->isActive,
            $this->createdAt,
            $this->updatedAt
        );
    }

    /**
     * Update entity from domain model.
     */
    public function updateFromDomainModel(Variant $variant): void
    {
        $this->sku = $variant->getSku()->toString();
        $this->optionValueMap = $variant->getOptionValueMap();
        $this->priceAmount = $variant->getPrice()->getAmount();
        $this->priceCurrency = $variant->getPrice()->getCurrency()->getCurrencyCode();
        $this->stockOnHand = $variant->getStock()->quantity();
        // Keep existing reserved value - it's managed separately
        $this->trackInventory = $variant->getStock()->trackInventory();
        $this->allowBackorder = $variant->getStock()->allowBackorder();
        $this->isActive = $variant->isActive();
        $this->images = array_map(fn ($img) => $img->toArray(), $variant->getImages());
        $this->updatedAt = new \DateTimeImmutable();
    }

    // Setters for associations
    public function setConfigurableProduct(?ConfigurableProductEntity $configurableProduct): void
    {
        $this->configurableProduct = $configurableProduct;
    }

    // Getters
    public function getId(): string
    {
        return $this->id;
    }

    public function getSku(): string
    {
        return $this->sku;
    }

    public function getOptionValueMap(): array
    {
        return $this->optionValueMap;
    }

    public function getPriceAmount(): int
    {
        return $this->priceAmount;
    }

    public function getPriceCurrency(): string
    {
        return $this->priceCurrency;
    }

    public function getStockOnHand(): int
    {
        return $this->stockOnHand;
    }

    public function isActive(): bool
    {
        return $this->isActive;
    }

    public function getImages(): array
    {
        return $this->images;
    }

    public function getConfigurableProduct(): ?ConfigurableProductEntity
    {
        return $this->configurableProduct;
    }

    // Setters for API Platform deserialization
    public function setSku(string $sku): void
    {
        $this->sku = $sku;
    }

    public function setOptionValueMap(array $optionValueMap): void
    {
        $this->optionValueMap = $optionValueMap;
    }

    public function setPriceAmount(int|string $priceAmount): void
    {
        $this->priceAmount = is_string($priceAmount) ? (int) $priceAmount : $priceAmount;
    }

    public function setPriceCurrency(string $priceCurrency): void
    {
        $this->priceCurrency = $priceCurrency;
    }

    public function setStockOnHand(int $stockOnHand): void
    {
        $this->stockOnHand = $stockOnHand;
    }

    public function setIsActive(bool $isActive): void
    {
        $this->isActive = $isActive;
    }
}
