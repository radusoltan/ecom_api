<?php

declare(strict_types=1);

namespace App\Catalog\Infrastructure\Persistence\Doctrine\Entity;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use App\Catalog\Domain\Model\Option;
use App\Catalog\Domain\Model\OptionId;
use App\Catalog\Domain\ValueObject\LocalizedString;
use App\Catalog\Domain\ValueObject\OptionCode;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Serializer\Annotation\MaxDepth;

/**
 * Doctrine entity for Option.
 */
#[ORM\Entity]
#[ORM\Table(name: 'catalog_product_options')]
#[ORM\UniqueConstraint(name: 'uniq_product_options_product_code', columns: ['configurable_product_id', 'code'])]
#[ORM\Index(name: 'idx_product_options_position', columns: ['configurable_product_id', 'position'])]
// Disabled ApiResource to prevent conflicts with ProductOptionsResource
// #[ApiResource(
//     operations: [
//         new GetCollection(
//             uriTemplate: '/product-options',
//             normalizationContext: ['groups' => ['option:read'], 'enable_max_depth' => true]
//         ),
//         new Get(
//             uriTemplate: '/product-options/{id}',
//             normalizationContext: ['groups' => ['option:read'], 'enable_max_depth' => true]
//         ),
//         new Post(
//             uriTemplate: '/product-options'
//         )
//     ],
//     normalizationContext: ['groups' => ['option:read'], 'skip_null_values' => false]
// )]
class OptionEntity
{
    #[ORM\Id]
    #[ORM\Column(type: 'string', length: 36)]
    #[Groups(['option:read'])]
    private string $id;

    #[ORM\ManyToOne(targetEntity: ConfigurableProductEntity::class, inversedBy: 'options')]
    #[ORM\JoinColumn(name: 'configurable_product_id', nullable: false, onDelete: 'CASCADE')]
    #[ApiProperty(readable: false, writable: false)]
    private ?ConfigurableProductEntity $configurableProduct = null;

    #[ORM\Column(type: 'string', length: 32)]
    #[Groups(['option:read'])]
    private string $code;

    #[ORM\Column(type: 'json', name: 'name_translations')]
    #[Groups(['option:read'])]
    /** @var array<string, mixed> */
    private array $nameTranslations = [];

    #[ORM\Column(type: 'integer')]
    #[Groups(['option:read'])]
    private int $position = 0;

    /**
     * @var Collection<int, OptionValueEntity>
     */
    #[ORM\OneToMany(mappedBy: 'option', targetEntity: OptionValueEntity::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['position' => 'ASC'])]
    #[ApiProperty(readableLink: false)]
    #[Groups(['option:read'])]
    #[MaxDepth(1)]
    private Collection $values;

    #[ORM\Column(type: 'datetime_immutable', name: 'created_at')]
    private \DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->values = new ArrayCollection();
        $this->createdAt = new \DateTimeImmutable();
    }

    /**
     * Create entity from domain model.
     */
    public static function fromDomainModel(Option $option): self
    {
        $entity = new self();
        $entity->id = $option->getId()->toString();
        $entity->code = $option->getCode()->toString();
        $entity->nameTranslations = $option->getNameTranslations()->toArray();
        $entity->position = $option->getPosition();

        // Map values
        foreach ($option->getValues() as $value) {
            $valueEntity = OptionValueEntity::fromDomainModel($value);
            $valueEntity->setOption($entity);
            $entity->values->add($valueEntity);
        }

        return $entity;
    }

    /**
     * Convert to domain model.
     */
    public function toDomainModel(): Option
    {
        // Reconstitute values first
        $values = [];
        foreach ($this->values as $valueEntity) {
            $values[] = $valueEntity->toDomainModel();
        }

        return Option::create(
            OptionId::fromString($this->id),
            OptionCode::fromString($this->code),
            LocalizedString::fromArray($this->nameTranslations),
            $this->position,
            $values
        );
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

    public function getCode(): string
    {
        return $this->code;
    }

    public function getNameTranslations(): array
    {
        return $this->nameTranslations;
    }

    public function getPosition(): int
    {
        return $this->position;
    }

    public function getValues(): Collection
    {
        return $this->values;
    }

    public function getConfigurableProduct(): ?ConfigurableProductEntity
    {
        return $this->configurableProduct;
    }
}
