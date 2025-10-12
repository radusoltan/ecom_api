<?php

declare(strict_types=1);

namespace App\Tax\Infrastructure\Persistence\Doctrine\Entity;

use App\Shared\Domain\ValueObject\TenantId;
use App\Tax\Domain\Model\TaxRule;
use App\Tax\Domain\ValueObject\TaxJurisdiction;
use App\Tax\Domain\ValueObject\TaxRate;
use App\Tax\Domain\ValueObject\TaxRuleId;
use Doctrine\ORM\Mapping as ORM;

/**
 * Tax Rule Doctrine Entity
 *
 * ORM adapter for TaxRule aggregate.
 */
#[ORM\Entity]
#[ORM\Table(name: 'tax_rules')]
// Performance indexes for queries
#[ORM\Index(name: 'idx_tax_rules_tenant_id', columns: ['tenant_id'])]
#[ORM\Index(name: 'idx_tax_rules_jurisdiction', columns: ['country_code', 'region_code'])]
#[ORM\Index(name: 'idx_tax_rules_is_active', columns: ['is_active'])]
#[ORM\Index(name: 'idx_tax_rules_tenant_active', columns: ['tenant_id', 'is_active'])]
#[ORM\Index(name: 'idx_tax_rules_created_at', columns: ['created_at'])]
#[ORM\UniqueConstraint(name: 'tax_rules_jurisdiction_unique', columns: ['tenant_id', 'country_code', 'region_code'])]
class TaxRuleEntity
{
    #[ORM\Id]
    #[ORM\Column(type: 'string', length: 36)]
    private string $id;

    #[ORM\Column(type: 'string', length: 36, name: 'tenant_id')]
    private string $tenantId;

    #[ORM\Column(type: 'string', length: 100)]
    private string $name;

    #[ORM\Column(type: 'string', length: 2, name: 'country_code')]
    private string $countryCode;

    #[ORM\Column(type: 'string', length: 3, nullable: true, name: 'region_code')]
    private ?string $regionCode = null;

    #[ORM\Column(type: 'decimal', precision: 5, scale: 2, name: 'rate_percentage')]
    private string $ratePercentage;

    #[ORM\Column(type: 'boolean', name: 'is_active')]
    private bool $isActive;

    #[ORM\Column(type: 'datetime_immutable', name: 'created_at')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: 'datetime_immutable', name: 'updated_at')]
    private \DateTimeImmutable $updatedAt;

    public static function fromDomainModel(TaxRule $taxRule): self
    {
        $entity = new self();
        $entity->id = $taxRule->id()->toString();
        $entity->tenantId = $taxRule->tenantId()->toString();
        $entity->name = $taxRule->name();
        $entity->countryCode = $taxRule->jurisdiction()->getCountryCode();
        $entity->regionCode = $taxRule->jurisdiction()->getRegionCode();
        $entity->ratePercentage = (string) $taxRule->rate()->getPercentage();
        $entity->isActive = $taxRule->isActive();
        $entity->createdAt = $taxRule->createdAt();
        $entity->updatedAt = $taxRule->updatedAt();

        return $entity;
    }

    public function toDomainModel(): TaxRule
    {
        $jurisdiction = $this->regionCode !== null
            ? TaxJurisdiction::fromCountryAndRegion($this->countryCode, $this->regionCode)
            : TaxJurisdiction::fromCountry($this->countryCode);

        return TaxRule::reconstituteFromPersistence(
            id: TaxRuleId::fromString($this->id),
            tenantId: TenantId::fromString($this->tenantId),
            name: $this->name,
            jurisdiction: $jurisdiction,
            rate: TaxRate::fromPercentage((float) $this->ratePercentage),
            isActive: $this->isActive,
            createdAt: $this->createdAt,
            updatedAt: $this->updatedAt
        );
    }

    public function updateFromDomainModel(TaxRule $taxRule): void
    {
        $this->name = $taxRule->name();
        $this->ratePercentage = (string) $taxRule->rate()->getPercentage();
        $this->isActive = $taxRule->isActive();
        $this->updatedAt = $taxRule->updatedAt();
    }
}
