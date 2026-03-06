<?php

declare(strict_types=1);

namespace App\Catalog\Infrastructure\Persistence\Doctrine\Entity;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use App\Catalog\Domain\Model\OptionValue;
use App\Catalog\Domain\Model\OptionValueId;
use App\Catalog\Domain\ValueObject\LocalizedString;
use App\Catalog\Domain\ValueObject\OptionValueCode;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;

/**
 * Doctrine entity for OptionValue.
 */
#[ORM\Entity]
#[ORM\Table(name: 'catalog_product_option_values')]
#[ORM\UniqueConstraint(name: 'uniq_option_values_option_code', columns: ['option_id', 'code'])]
#[ORM\Index(name: 'idx_option_values_position', columns: ['option_id', 'position'])]
#[ORM\Index(name: 'idx_option_values_tenant', columns: ['tenant_id'])]
#[ApiResource(
    operations: [
        new GetCollection(
            uriTemplate: '/product-option-values'
        ),
        new Get(
            uriTemplate: '/product-option-values/{id}'
        ),
    ]
)]
class OptionValueEntity
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid')]
    #[Groups(['option:read'])]
    private string $id;

    #[ORM\ManyToOne(targetEntity: OptionEntity::class, inversedBy: 'values')]
    #[ORM\JoinColumn(name: 'option_id', nullable: false, onDelete: 'CASCADE')]
    #[ApiProperty(readable: false, writable: false)]
    private ?OptionEntity $option = null;

    #[ORM\Column(type: 'string', length: 32)]
    #[Groups(['option:read'])]
    private string $code;

    #[ORM\Column(type: 'jsonb', name: 'name_translations')]
    #[Groups(['option:read'])]
    private array $nameTranslations = [];

    #[ORM\Column(type: 'integer')]
    #[Groups(['option:read'])]
    private int $position = 0;

    #[ORM\Column(type: 'uuid', name: 'tenant_id')]
    private string $tenantId;

    #[ORM\Column(type: 'datetimetz_immutable', name: 'created_at')]
    private \DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    /**
     * Create entity from domain model.
     */
    public static function fromDomainModel(OptionValue $value): self
    {
        $entity = new self();
        $entity->id = $value->getId()->toString();
        $entity->code = $value->getCode()->toString();
        $entity->nameTranslations = $value->getNameTranslations()->toArray();
        $entity->position = $value->getPosition();

        return $entity;
    }

    /**
     * Convert to domain model.
     */
    public function toDomainModel(): OptionValue
    {
        return OptionValue::reconstituteFromPersistence(
            OptionValueId::fromString($this->id),
            OptionValueCode::fromString($this->code),
            LocalizedString::fromArray($this->nameTranslations),
            $this->position
        );
    }

    // Setters for associations
    public function setOption(?OptionEntity $option): void
    {
        $this->option = $option;
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

    public function getOption(): ?OptionEntity
    {
        return $this->option;
    }

    public function getTenantId(): string
    {
        return $this->tenantId;
    }

    public function setTenantId(string $tenantId): void
    {
        $this->tenantId = $tenantId;
    }
}
