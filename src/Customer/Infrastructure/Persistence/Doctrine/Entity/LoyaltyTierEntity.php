<?php

declare(strict_types=1);

namespace App\Customer\Infrastructure\Persistence\Doctrine\Entity;

use App\Customer\Domain\Model\LoyaltyTier;
use App\Customer\Domain\ValueObject\LoyaltyTierId;
use App\Shared\Domain\ValueObject\Money;
use Doctrine\ORM\Mapping as ORM;

/**
 * Loyalty Tier Doctrine Entity.
 *
 * Infrastructure layer entity with ORM attributes.
 * Maps to loyalty_tiers table.
 * Converts to/from LoyaltyTier domain model.
 */
#[ORM\Entity]
#[ORM\Table(name: 'loyalty_tiers')]
#[ORM\Index(columns: ['program_id', 'threshold'], name: 'idx_loyalty_tiers_program_threshold')]
#[ORM\Index(columns: ['tenant_id'], name: 'idx_loyalty_tiers_tenant')]
class LoyaltyTierEntity
{
    #[ORM\Id]
    #[ORM\Column(type: 'string', length: 36)]
    private string $id = '';

    #[ORM\Column(type: 'string', length: 36, nullable: false)]
    private string $programId = '';

    #[ORM\Column(type: 'string', length: 36, nullable: false)]
    private string $tenantId = '';

    #[ORM\ManyToOne(targetEntity: LoyaltyProgramEntity::class, inversedBy: 'tierEntities')]
    #[ORM\JoinColumn(name: 'program_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private ?LoyaltyProgramEntity $program = null;

    #[ORM\Column(type: 'string', length: 50, nullable: false)]
    private string $name = '';

    #[ORM\Column(type: 'integer', nullable: false)]
    private int $threshold = 0;

    #[ORM\Column(type: 'integer', nullable: false)]
    private int $discountPercentage = 0;

    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $freeShippingMinOrder = null;

    #[ORM\Column(type: 'string', length: 3, nullable: true)]
    private ?string $freeShippingCurrency = null;

    #[ORM\Column(type: 'integer', nullable: false)]
    private int $sortOrder = 0;

    #[ORM\Column(type: 'datetimetz_immutable', nullable: false)]
    private \DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public static function fromDomainModel(
        LoyaltyTier $tier,
        string $programId,
        string $tenantId,
    ): self {
        $entity = new self();
        $entity->id = $tier->id()->toString();
        $entity->programId = $programId;
        $entity->tenantId = $tenantId;
        $entity->name = $tier->name();
        $entity->threshold = $tier->threshold();
        $entity->discountPercentage = $tier->discountPercentage();
        $entity->sortOrder = $tier->sortOrder();
        $entity->createdAt = new \DateTimeImmutable();

        // Handle free shipping benefit
        if ($tier->hasFreeShippingBenefit()) {
            $freeShipping = $tier->freeShippingMinOrder();
            if (null !== $freeShipping) {
                $entity->freeShippingMinOrder = $freeShipping->getAmount();
                $entity->freeShippingCurrency = $freeShipping->getCurrency()->getCurrencyCode();
            }
        }

        return $entity;
    }

    public function updateFromDomainModel(LoyaltyTier $tier): void
    {
        // Note: id, programId, and tenantId should never change
        $this->name = $tier->name();
        $this->threshold = $tier->threshold();
        $this->discountPercentage = $tier->discountPercentage();
        $this->sortOrder = $tier->sortOrder();

        // Update free shipping benefit
        if ($tier->hasFreeShippingBenefit()) {
            $freeShipping = $tier->freeShippingMinOrder();
            if (null !== $freeShipping) {
                $this->freeShippingMinOrder = $freeShipping->getAmount();
                $this->freeShippingCurrency = $freeShipping->getCurrency()->getCurrencyCode();
            }
        } else {
            $this->freeShippingMinOrder = null;
            $this->freeShippingCurrency = null;
        }
    }

    public function toDomainModel(): LoyaltyTier
    {
        $freeShippingMinOrder = null;

        if (null !== $this->freeShippingMinOrder && null !== $this->freeShippingCurrency) {
            $freeShippingMinOrder = Money::of(
                (string) ($this->freeShippingMinOrder / 100),
                $this->freeShippingCurrency
            );
        }

        return LoyaltyTier::create(
            LoyaltyTierId::fromString($this->id),
            $this->name,
            $this->threshold,
            $this->discountPercentage,
            $freeShippingMinOrder,
            $this->sortOrder
        );
    }

    // Getters
    public function getId(): string
    {
        return $this->id;
    }

    public function getProgramId(): string
    {
        return $this->programId;
    }

    public function getTenantId(): string
    {
        return $this->tenantId;
    }

    public function getProgram(): ?LoyaltyProgramEntity
    {
        return $this->program;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getThreshold(): int
    {
        return $this->threshold;
    }

    public function getDiscountPercentage(): int
    {
        return $this->discountPercentage;
    }

    public function getFreeShippingMinOrder(): ?int
    {
        return $this->freeShippingMinOrder;
    }

    public function getFreeShippingCurrency(): ?string
    {
        return $this->freeShippingCurrency;
    }

    public function getSortOrder(): int
    {
        return $this->sortOrder;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    // Setters
    public function setId(string $id): void
    {
        $this->id = $id;
    }

    public function setProgramId(string $programId): void
    {
        $this->programId = $programId;
    }

    public function setTenantId(string $tenantId): void
    {
        $this->tenantId = $tenantId;
    }

    public function setProgram(?LoyaltyProgramEntity $program): void
    {
        $this->program = $program;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function setThreshold(int $threshold): void
    {
        $this->threshold = $threshold;
    }

    public function setDiscountPercentage(int $discountPercentage): void
    {
        $this->discountPercentage = $discountPercentage;
    }

    public function setFreeShippingMinOrder(?int $freeShippingMinOrder): void
    {
        $this->freeShippingMinOrder = $freeShippingMinOrder;
    }

    public function setFreeShippingCurrency(?string $freeShippingCurrency): void
    {
        $this->freeShippingCurrency = $freeShippingCurrency;
    }

    public function setSortOrder(int $sortOrder): void
    {
        $this->sortOrder = $sortOrder;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): void
    {
        $this->createdAt = $createdAt;
    }
}
