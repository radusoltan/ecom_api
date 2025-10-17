<?php

declare(strict_types=1);

namespace App\Catalog\Infrastructure\Persistence\Doctrine\Entity;

use App\Catalog\Domain\Model\ConfigurableProduct;
use App\Catalog\Domain\Model\ConfigurableProductId;
use App\Catalog\Domain\Model\ProductId;
use App\Shared\Domain\ValueObject\TenantId;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * Doctrine entity for ConfigurableProduct
 * Maps the domain model to database
 */
#[ORM\Entity]
#[ORM\Table(name: 'catalog_configurable_products')]
#[ORM\UniqueConstraint(name: 'uniq_configurable_products_product', columns: ['product_id'])]
#[ORM\Index(name: 'idx_configurable_products_tenant', columns: ['tenant_id'])]
#[ORM\Index(name: 'idx_configurable_products_tenant_product', columns: ['tenant_id', 'product_id'])]
class ConfigurableProductEntity
{
    #[ORM\Id]
    #[ORM\Column(type: 'string', length: 36)]
    private string $id;

    #[ORM\Column(type: 'string', length: 36, name: 'product_id')]
    private string $productId;

    #[ORM\Column(type: 'string', length: 36, name: 'tenant_id')]
    private string $tenantId;

    /**
     * @var Collection<int, OptionEntity>
     */
    #[ORM\OneToMany(mappedBy: 'configurableProduct', targetEntity: OptionEntity::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['position' => 'ASC'])]
    private Collection $options;

    /**
     * @var Collection<int, VariantEntity>
     */
    #[ORM\OneToMany(mappedBy: 'configurableProduct', targetEntity: VariantEntity::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $variants;

    #[ORM\Column(type: 'datetime_immutable', name: 'created_at')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: 'datetime_immutable', name: 'updated_at')]
    private \DateTimeImmutable $updatedAt;

    public function __construct()
    {
        $this->options = new ArrayCollection();
        $this->variants = new ArrayCollection();
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
    }

    /**
     * Create entity from domain model
     */
    public static function fromDomainModel(ConfigurableProduct $configurableProduct): self
    {
        $entity = new self();
        $entity->id = $configurableProduct->getId()->toString();
        $entity->productId = $configurableProduct->getProductId()->toString();
        $entity->tenantId = $configurableProduct->getTenantId()->toString();

        // Map options
        foreach ($configurableProduct->getOptions() as $option) {
            $optionEntity = OptionEntity::fromDomainModel($option);
            $optionEntity->setConfigurableProduct($entity);
            $entity->options->add($optionEntity);
        }

        // Map variants
        foreach ($configurableProduct->getVariants() as $variant) {
            $variantEntity = VariantEntity::fromDomainModel($variant);
            $variantEntity->setConfigurableProduct($entity);
            $entity->variants->add($variantEntity);
        }

        return $entity;
    }

    /**
     * Convert to domain model
     */
    public function toDomainModel(): ConfigurableProduct
    {
        // Reconstitute options
        $options = [];
        foreach ($this->options as $optionEntity) {
            $options[] = $optionEntity->toDomainModel();
        }

        // Reconstitute variants
        $variants = [];
        foreach ($this->variants as $variantEntity) {
            $variants[] = $variantEntity->toDomainModel();
        }

        return ConfigurableProduct::reconstituteFromPersistence(
            ConfigurableProductId::fromString($this->id),
            ProductId::fromString($this->productId),
            TenantId::fromString($this->tenantId),
            $options,
            $variants,
            $this->createdAt,
            $this->updatedAt
        );
    }

    /**
     * Update entity from domain model
     */
    public function updateFromDomainModel(ConfigurableProduct $configurableProduct): void
    {
        $this->updatedAt = new \DateTimeImmutable();

        // Update options
        $this->options->clear();
        foreach ($configurableProduct->getOptions() as $option) {
            $optionEntity = OptionEntity::fromDomainModel($option);
            $optionEntity->setConfigurableProduct($this);
            $this->options->add($optionEntity);
        }

        // Update variants
        $this->variants->clear();
        foreach ($configurableProduct->getVariants() as $variant) {
            $variantEntity = VariantEntity::fromDomainModel($variant);
            $variantEntity->setConfigurableProduct($this);
            $this->variants->add($variantEntity);
        }
    }

    // Getters
    public function getId(): string
    {
        return $this->id;
    }

    public function getProductId(): string
    {
        return $this->productId;
    }

    public function getTenantId(): string
    {
        return $this->tenantId;
    }

    public function getOptions(): Collection
    {
        return $this->options;
    }

    public function getVariants(): Collection
    {
        return $this->variants;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }
}