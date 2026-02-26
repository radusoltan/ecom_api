<?php

declare(strict_types=1);

namespace App\Tax\Infrastructure\Persistence\Doctrine\Entity;

use App\Shared\Domain\ValueObject\TenantId;
use App\Tax\Domain\Model\TaxCategory;
use App\Tax\Domain\Model\TaxJurisdiction;
use App\Tax\Domain\Model\TaxRate;
use App\Tax\Domain\Model\TaxRule;
use App\Tax\Domain\Model\TaxRuleId;
use Doctrine\ORM\Mapping as ORM;

/**
 * TaxRule Doctrine Entity.
 *
 * Infrastructure adapter for TaxRule aggregate.
 * Provides persistence for tax rules with multi-tenant Row-Level Security.
 *
 * Database Table: tax_rules
 * Migration: Version20251128120000_CreateTaxTables
 */
#[ORM\Entity]
#[ORM\Table(name: 'tax_rules')]
#[ORM\Index(name: 'idx_tax_rules_tenant', columns: ['tenant_id'])]
#[ORM\Index(name: 'idx_tax_rules_jurisdiction_lookup', columns: ['tenant_id', 'country_code', 'region_code', 'category', 'is_active'])]
#[ORM\Index(name: 'idx_tax_rules_country', columns: ['country_code'])]
#[ORM\Index(name: 'idx_tax_rules_category', columns: ['category'])]
#[ORM\Index(name: 'idx_tax_rules_active', columns: ['is_active'])]
#[ORM\Index(name: 'idx_tax_rules_validity', columns: ['valid_from', 'valid_until'])]
final class TaxRuleEntity
{
    #[ORM\Id]
    #[ORM\Column(type: 'string', length: 36)]
    private string $id;

    #[ORM\Column(type: 'string', length: 36, name: 'tenant_id')]
    private string $tenantId;

    #[ORM\Column(type: 'string', length: 2, name: 'country_code')]
    private string $countryCode;

    #[ORM\Column(type: 'string', length: 10, nullable: true, name: 'region_code')]
    private ?string $regionCode = null;

    #[ORM\Column(type: 'string', length: 20)]
    private string $category;

    #[ORM\Column(type: 'decimal', precision: 5, scale: 2)]
    private string $rate;

    #[ORM\Column(type: 'string', length: 100)]
    private string $name;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $description = null;

    #[ORM\Column(type: 'boolean', name: 'is_active')]
    private bool $isActive = true;

    #[ORM\Column(type: 'datetimetz_immutable', nullable: true, name: 'valid_from')]
    private ?\DateTimeImmutable $validFrom = null;

    #[ORM\Column(type: 'datetimetz_immutable', nullable: true, name: 'valid_until')]
    private ?\DateTimeImmutable $validUntil = null;

    #[ORM\Column(type: 'integer')]
    private int $priority = 0;

    #[ORM\Column(type: 'boolean', name: 'is_reverse_charge')]
    private bool $isReverseCharge = false;

    #[ORM\Column(type: 'datetimetz_immutable', name: 'created_at')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: 'datetimetz_immutable', name: 'updated_at')]
    private \DateTimeImmutable $updatedAt;

    /**
     * Convert domain model to entity (for persistence).
     */
    public static function fromDomainModel(TaxRule $taxRule): self
    {
        $entity = new self();
        $entity->id = $taxRule->id()->toString();
        $entity->tenantId = $taxRule->tenantId()->toString();
        $entity->countryCode = $taxRule->jurisdiction()->countryCode();
        $entity->regionCode = $taxRule->jurisdiction()->regionCode();
        $entity->category = $taxRule->category()->value;
        $entity->rate = (string) $taxRule->rate()->percentage();
        $entity->name = $taxRule->name();
        $entity->description = $taxRule->description();
        $entity->isActive = $taxRule->isActive();
        $entity->validFrom = $taxRule->validFrom();
        $entity->validUntil = $taxRule->validTo();
        $entity->priority = $taxRule->priority();
        $entity->isReverseCharge = $taxRule->isReverseCharge();
        $entity->createdAt = $taxRule->createdAt();
        $entity->updatedAt = $taxRule->updatedAt();

        return $entity;
    }

    /**
     * Convert entity to domain model (for hydration).
     */
    public function toDomainModel(): TaxRule
    {
        $jurisdiction = null !== $this->regionCode
            ? TaxJurisdiction::fromCountryAndRegion($this->countryCode, $this->regionCode)
            : TaxJurisdiction::fromCountryCode($this->countryCode);

        return TaxRule::reconstituteFromPersistence(
            id: TaxRuleId::fromString($this->id),
            tenantId: TenantId::fromString($this->tenantId),
            jurisdiction: $jurisdiction,
            category: TaxCategory::from($this->category),
            rate: TaxRate::fromPercentage((float) $this->rate),
            name: $this->name,
            description: $this->description,
            priority: $this->priority,
            isActive: $this->isActive,
            validFrom: $this->validFrom ?? new \DateTimeImmutable(),
            validTo: $this->validUntil,
            isReverseCharge: $this->isReverseCharge,
            createdAt: $this->createdAt,
            updatedAt: $this->updatedAt
        );
    }

    /**
     * Update entity from domain model (for updates).
     *
     * This method updates only mutable fields from the domain model.
     * Immutable fields (id, tenantId, createdAt) are NOT updated.
     */
    public function updateFromDomainModel(TaxRule $taxRule): void
    {
        $this->countryCode = $taxRule->jurisdiction()->countryCode();
        $this->regionCode = $taxRule->jurisdiction()->regionCode();
        $this->category = $taxRule->category()->value;
        $this->rate = (string) $taxRule->rate()->percentage();
        $this->name = $taxRule->name();
        $this->description = $taxRule->description();
        $this->isActive = $taxRule->isActive();
        $this->validFrom = $taxRule->validFrom();
        $this->validUntil = $taxRule->validTo();
        $this->priority = $taxRule->priority();
        $this->isReverseCharge = $taxRule->isReverseCharge();
        $this->updatedAt = $taxRule->updatedAt();
    }

    // =========================================================================
    // Getters and Setters for API Platform
    // =========================================================================

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

    public function getCountryCode(): string
    {
        return $this->countryCode;
    }

    public function setCountryCode(string $countryCode): void
    {
        $this->countryCode = $countryCode;
    }

    public function getRegionCode(): ?string
    {
        return $this->regionCode;
    }

    public function setRegionCode(?string $regionCode): void
    {
        $this->regionCode = $regionCode;
    }

    public function getCategory(): string
    {
        return $this->category;
    }

    public function setCategory(string $category): void
    {
        $this->category = $category;
    }

    public function getRate(): string
    {
        return $this->rate;
    }

    public function setRate(string $rate): void
    {
        $this->rate = $rate;
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

    public function isActive(): bool
    {
        return $this->isActive;
    }

    public function setIsActive(bool $isActive): void
    {
        $this->isActive = $isActive;
    }

    public function getValidFrom(): ?\DateTimeImmutable
    {
        return $this->validFrom;
    }

    public function setValidFrom(?\DateTimeImmutable $validFrom): void
    {
        $this->validFrom = $validFrom;
    }

    public function getValidUntil(): ?\DateTimeImmutable
    {
        return $this->validUntil;
    }

    public function setValidUntil(?\DateTimeImmutable $validUntil): void
    {
        $this->validUntil = $validUntil;
    }

    public function getPriority(): int
    {
        return $this->priority;
    }

    public function setPriority(int $priority): void
    {
        $this->priority = $priority;
    }

    public function isReverseCharge(): bool
    {
        return $this->isReverseCharge;
    }

    public function setIsReverseCharge(bool $isReverseCharge): void
    {
        $this->isReverseCharge = $isReverseCharge;
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
