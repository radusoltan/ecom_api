<?php

declare(strict_types=1);

namespace App\Customer\Infrastructure\Persistence\Doctrine\Entity;

use App\Customer\Domain\Model\LoyaltyProgram;
use App\Customer\Domain\Model\LoyaltyTier;
use App\Customer\Domain\ValueObject\EarningRate;
use App\Customer\Domain\ValueObject\LoyaltyProgramId;
use App\Customer\Domain\ValueObject\RedemptionRule;
use App\Shared\Domain\ValueObject\Money;
use App\Shared\Domain\ValueObject\TenantId;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * Loyalty Program Doctrine Entity.
 *
 * Infrastructure layer entity with ORM attributes.
 * Maps to loyalty_programs table.
 * Converts to/from LoyaltyProgram domain model.
 */
#[ORM\Entity]
#[ORM\Table(name: 'loyalty_programs')]
#[ORM\Index(columns: ['tenant_id'], name: 'idx_loyalty_programs_tenant')]
#[ORM\UniqueConstraint(name: 'unique_program_per_tenant', columns: ['tenant_id'])]
class LoyaltyProgramEntity
{
    #[ORM\Id]
    #[ORM\Column(type: 'string', length: 36)]
    private string $id = '';

    #[ORM\Column(type: 'string', length: 36, nullable: false)]
    private string $tenantId = '';

    #[ORM\Column(type: 'string', length: 100, nullable: false)]
    private string $name = '';

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $description = null;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 4, nullable: false)]
    private string $earningRate = '1.0000';

    #[ORM\Column(type: 'integer', nullable: false)]
    private int $minOrderValue = 0;

    #[ORM\Column(type: 'string', length: 3, nullable: false)]
    private string $minOrderCurrency = 'USD';

    #[ORM\Column(type: 'decimal', precision: 10, scale: 4, nullable: false)]
    private string $conversionRate = '100.0000';

    #[ORM\Column(type: 'integer', nullable: false)]
    private int $minPointsToRedeem = 0;

    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $maxPointsPerOrder = null;

    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $validityDays = null;

    #[ORM\Column(type: 'boolean', nullable: false, options: ['default' => true])]
    private bool $isActive = true;

    #[ORM\Column(type: 'datetime_immutable', nullable: false)]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: 'datetime_immutable', nullable: false)]
    private \DateTimeImmutable $updatedAt;

    /**
     * @var Collection<int, LoyaltyTierEntity>
     */
    #[ORM\OneToMany(
        mappedBy: 'program',
        targetEntity: LoyaltyTierEntity::class,
        cascade: ['persist', 'remove'],
        orphanRemoval: true
    )]
    #[ORM\OrderBy(['threshold' => 'ASC'])]
    private Collection $tierEntities;

    public function __construct()
    {
        $this->tierEntities = new ArrayCollection();
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
    }

    public static function fromDomainModel(LoyaltyProgram $program): self
    {
        $entity = new self();
        $entity->id = $program->id()->toString();
        $entity->tenantId = $program->tenantId()->toString();
        $entity->name = $program->name();
        $entity->description = $program->description();
        $entity->earningRate = number_format($program->earningRate()->toFloat(), 4, '.', '');
        $entity->minOrderValue = $program->minOrderValue()->getAmount();
        $entity->minOrderCurrency = $program->minOrderValue()->getCurrency()->getCurrencyCode();
        $entity->conversionRate = number_format($program->redemptionRule()->conversionRate(), 4, '.', '');
        $entity->minPointsToRedeem = $program->redemptionRule()->minPointsToRedeem();
        $entity->maxPointsPerOrder = $program->redemptionRule()->maxPointsPerOrder();
        $entity->validityDays = $program->validityDays();
        $entity->isActive = $program->isActive();
        $entity->createdAt = $program->createdAt();
        $entity->updatedAt = $program->updatedAt();

        // Convert tiers
        foreach ($program->getTiers() as $tier) {
            $tierEntity = LoyaltyTierEntity::fromDomainModel(
                $tier,
                $entity->id,
                $entity->tenantId
            );
            $entity->addTierEntity($tierEntity);
        }

        return $entity;
    }

    public function updateFromDomainModel(LoyaltyProgram $program): void
    {
        // Note: id and tenantId should never change
        $this->name = $program->name();
        $this->description = $program->description();
        $this->earningRate = number_format($program->earningRate()->toFloat(), 4, '.', '');
        $this->minOrderValue = $program->minOrderValue()->getAmount();
        $this->minOrderCurrency = $program->minOrderValue()->getCurrency()->getCurrencyCode();
        $this->conversionRate = number_format($program->redemptionRule()->conversionRate(), 4, '.', '');
        $this->minPointsToRedeem = $program->redemptionRule()->minPointsToRedeem();
        $this->maxPointsPerOrder = $program->redemptionRule()->maxPointsPerOrder();
        $this->validityDays = $program->validityDays();
        $this->isActive = $program->isActive();
        $this->updatedAt = $program->updatedAt();

        // Sync tiers
        $this->syncTiers($program->getTiers());
    }

    /**
     * @param LoyaltyTier[] $domainTiers
     */
    private function syncTiers(array $domainTiers): void
    {
        // Remove tiers that no longer exist
        $tierIds = array_map(fn (LoyaltyTier $tier) => $tier->id()->toString(), $domainTiers);

        foreach ($this->tierEntities as $tierEntity) {
            if (!in_array($tierEntity->getId(), $tierIds, true)) {
                $this->removeTierEntity($tierEntity);
            }
        }

        // Update or add tiers
        foreach ($domainTiers as $domainTier) {
            $existingTier = $this->findTierById($domainTier->id()->toString());

            if (null !== $existingTier) {
                $existingTier->updateFromDomainModel($domainTier);
            } else {
                $newTierEntity = LoyaltyTierEntity::fromDomainModel(
                    $domainTier,
                    $this->id,
                    $this->tenantId
                );
                $this->addTierEntity($newTierEntity);
            }
        }
    }

    private function findTierById(string $tierId): ?LoyaltyTierEntity
    {
        foreach ($this->tierEntities as $tierEntity) {
            if ($tierEntity->getId() === $tierId) {
                return $tierEntity;
            }
        }

        return null;
    }

    public function toDomainModel(): LoyaltyProgram
    {
        $earningRate = EarningRate::fromFloat((float) $this->earningRate);
        $minOrderValue = Money::of((string) ($this->minOrderValue / 100), $this->minOrderCurrency);

        $redemptionRule = RedemptionRule::create(
            (float) $this->conversionRate,
            $this->minPointsToRedeem,
            $this->maxPointsPerOrder
        );

        $tiers = array_map(
            fn (LoyaltyTierEntity $tierEntity) => $tierEntity->toDomainModel(),
            $this->tierEntities->toArray()
        );

        return LoyaltyProgram::reconstituteFromPersistence(
            LoyaltyProgramId::fromString($this->id),
            TenantId::fromString($this->tenantId),
            $this->name,
            $this->description ?? '',
            $earningRate,
            $minOrderValue,
            $redemptionRule,
            $this->validityDays,
            $this->isActive,
            $this->createdAt,
            $this->updatedAt,
            $tiers
        );
    }

    // Collection management
    public function addTierEntity(LoyaltyTierEntity $tier): void
    {
        if (!$this->tierEntities->contains($tier)) {
            $this->tierEntities->add($tier);
            $tier->setProgram($this);
        }
    }

    public function removeTierEntity(LoyaltyTierEntity $tier): void
    {
        if ($this->tierEntities->contains($tier)) {
            $this->tierEntities->removeElement($tier);
        }
    }

    // Getters
    public function getId(): string
    {
        return $this->id;
    }

    public function getTenantId(): string
    {
        return $this->tenantId;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function getEarningRate(): string
    {
        return $this->earningRate;
    }

    public function getMinOrderValue(): int
    {
        return $this->minOrderValue;
    }

    public function getMinOrderCurrency(): string
    {
        return $this->minOrderCurrency;
    }

    public function getConversionRate(): string
    {
        return $this->conversionRate;
    }

    public function getMinPointsToRedeem(): int
    {
        return $this->minPointsToRedeem;
    }

    public function getMaxPointsPerOrder(): ?int
    {
        return $this->maxPointsPerOrder;
    }

    public function getValidityDays(): ?int
    {
        return $this->validityDays;
    }

    public function isActive(): bool
    {
        return $this->isActive;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    /**
     * @return Collection<int, LoyaltyTierEntity>
     */
    public function getTierEntities(): Collection
    {
        return $this->tierEntities;
    }

    // Setters (for API Platform deserialization if needed)
    public function setId(string $id): void
    {
        $this->id = $id;
    }

    public function setTenantId(string $tenantId): void
    {
        $this->tenantId = $tenantId;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function setDescription(?string $description): void
    {
        $this->description = $description;
    }

    public function setEarningRate(string $earningRate): void
    {
        $this->earningRate = $earningRate;
    }

    public function setMinOrderValue(int $minOrderValue): void
    {
        $this->minOrderValue = $minOrderValue;
    }

    public function setMinOrderCurrency(string $minOrderCurrency): void
    {
        $this->minOrderCurrency = $minOrderCurrency;
    }

    public function setConversionRate(string $conversionRate): void
    {
        $this->conversionRate = $conversionRate;
    }

    public function setMinPointsToRedeem(int $minPointsToRedeem): void
    {
        $this->minPointsToRedeem = $minPointsToRedeem;
    }

    public function setMaxPointsPerOrder(?int $maxPointsPerOrder): void
    {
        $this->maxPointsPerOrder = $maxPointsPerOrder;
    }

    public function setValidityDays(?int $validityDays): void
    {
        $this->validityDays = $validityDays;
    }

    public function setIsActive(bool $isActive): void
    {
        $this->isActive = $isActive;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): void
    {
        $this->createdAt = $createdAt;
    }

    public function setUpdatedAt(\DateTimeImmutable $updatedAt): void
    {
        $this->updatedAt = $updatedAt;
    }
}
