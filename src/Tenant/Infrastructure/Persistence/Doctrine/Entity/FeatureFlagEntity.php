<?php

declare(strict_types=1);

namespace App\Tenant\Infrastructure\Persistence\Doctrine\Entity;

use App\Shared\Domain\ValueObject\TenantId;
use App\Tenant\Domain\Model\FeatureFlag;
use App\Tenant\Domain\Model\FeatureFlagId;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'tenant_feature_flags')]
#[ORM\UniqueConstraint(name: 'uniq_tenant_feature', columns: ['tenant_id', 'feature_name'])]
#[ORM\Index(name: 'idx_feature_flags_tenant', columns: ['tenant_id'])]
class FeatureFlagEntity
{
    #[ORM\Id]
    #[ORM\Column(type: 'string', length: 36)]
    private string $id;

    #[ORM\Column(type: 'string', length: 36, name: 'tenant_id')]
    private string $tenantId;

    #[ORM\Column(type: 'string', length: 100, name: 'feature_name')]
    private string $featureName;

    #[ORM\Column(type: 'boolean')]
    private bool $enabled = false;

    /** @var array<string, mixed> */
    #[ORM\Column(type: 'jsonb')]
    private array $configuration = [];

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $description = null;

    #[ORM\Column(type: 'datetimetz_immutable', name: 'created_at')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: 'datetimetz_immutable', nullable: true, name: 'updated_at')]
    private ?\DateTimeImmutable $updatedAt = null;

    public static function fromDomainModel(FeatureFlag $flag): self
    {
        $entity = new self();
        $entity->id = $flag->id()->toString();
        $entity->tenantId = $flag->tenantId()->toString();
        $entity->featureName = $flag->featureName();
        $entity->enabled = $flag->isEnabled();
        $entity->configuration = $flag->configuration();
        $entity->description = $flag->description();
        $entity->createdAt = $flag->createdAt();
        $entity->updatedAt = $flag->updatedAt();

        return $entity;
    }

    public function toDomainModel(): FeatureFlag
    {
        return FeatureFlag::reconstituteFromPersistence(
            FeatureFlagId::fromString($this->id),
            TenantId::fromString($this->tenantId),
            $this->featureName,
            $this->enabled,
            $this->configuration,
            $this->description,
            $this->createdAt,
            $this->updatedAt,
        );
    }

    public function updateFromDomainModel(FeatureFlag $flag): void
    {
        $this->enabled = $flag->isEnabled();
        $this->configuration = $flag->configuration();
        $this->description = $flag->description();
        $this->updatedAt = $flag->updatedAt();
    }
}
