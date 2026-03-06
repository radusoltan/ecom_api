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
use Symfony\Component\Serializer\Attribute\MaxDepth;

/**
 * Doctrine entity for ConfigurableProduct
 * Maps the domain model to database.
 */
#[ORM\Entity]
#[ORM\Table(name: 'catalog_configurable_products')]
#[ORM\UniqueConstraint(name: 'uniq_configurable_products_product', columns: ['product_id'])]
#[ORM\Index(name: 'idx_configurable_products_tenant', columns: ['tenant_id'])]
#[ORM\Index(name: 'idx_configurable_products_tenant_product', columns: ['tenant_id', 'product_id'])]
class ConfigurableProductEntity
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid')]
    private string $id;

    #[ORM\Column(type: 'uuid', name: 'product_id')]
    private string $productId;

    #[ORM\Column(type: 'uuid', name: 'tenant_id')]
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
    #[MaxDepth(2)] // Limit recursion depth to prevent circular references (ConfigurableProduct -> Variants -> ConfigurableProduct)
    private Collection $variants;

    #[ORM\Column(type: 'datetimetz_immutable', name: 'created_at')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: 'datetimetz_immutable', name: 'updated_at')]
    private \DateTimeImmutable $updatedAt;

    public function __construct()
    {
        $this->options = new ArrayCollection();
        $this->variants = new ArrayCollection();
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
    }

    /**
     * Create entity from domain model.
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
     * Convert to domain model.
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
     * Update entity from domain model.
     *
     * Note: This method updates the existing entity collections by matching IDs
     * to avoid Doctrine identity map conflicts. It does NOT clear and recreate
     * all entities, which would cause "entity already exists in identity map" errors.
     */
    public function updateFromDomainModel(ConfigurableProduct $configurableProduct): void
    {
        $this->updatedAt = new \DateTimeImmutable();

        // Update options: match existing by ID or create new
        $newOptionIds = array_map(
            fn ($option) => $option->getId()->toString(),
            $configurableProduct->getOptions()
        );

        // Remove options that are no longer in the domain model
        foreach ($this->options as $existingOption) {
            if (!in_array($existingOption->getId(), $newOptionIds, true)) {
                $this->options->removeElement($existingOption);
            }
        }

        // Add or update options from domain model
        foreach ($configurableProduct->getOptions() as $option) {
            $optionId = $option->getId()->toString();
            $existingOption = null;

            // Find existing option by ID
            foreach ($this->options as $opt) {
                if ($opt->getId() === $optionId) {
                    $existingOption = $opt;

                    break;
                }
            }

            if ($existingOption) {
                // Update existing option (if needed, we can add an update method to OptionEntity)
                // For now, options are immutable in domain, so no update needed
            } else {
                // Create new option entity
                $optionEntity = OptionEntity::fromDomainModel($option);
                $optionEntity->setConfigurableProduct($this);
                $this->options->add($optionEntity);
            }
        }

        // Update variants: match existing by ID or create new
        $newVariantIds = array_map(
            fn ($variant) => $variant->getId()->toString(),
            $configurableProduct->getVariants()
        );

        // Remove variants that are no longer in the domain model
        foreach ($this->variants as $existingVariant) {
            if (!in_array($existingVariant->getId(), $newVariantIds, true)) {
                $this->variants->removeElement($existingVariant);
            }
        }

        // Add or update variants from domain model
        foreach ($configurableProduct->getVariants() as $variant) {
            $variantId = $variant->getId()->toString();
            $existingVariant = null;

            // Find existing variant by ID
            foreach ($this->variants as $var) {
                if ($var->getId() === $variantId) {
                    $existingVariant = $var;

                    break;
                }
            }

            if ($existingVariant) {
                // Update existing variant
                $existingVariant->updateFromDomainModel($variant);
            } else {
                // Create new variant entity
                $variantEntity = VariantEntity::fromDomainModel($variant);
                $variantEntity->setConfigurableProduct($this);
                $this->variants->add($variantEntity);
            }
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
