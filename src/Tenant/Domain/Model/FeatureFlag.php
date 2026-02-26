<?php

declare(strict_types=1);

namespace App\Tenant\Domain\Model;

use App\Shared\Domain\Aggregate\AggregateRoot;
use App\Shared\Domain\ValueObject\TenantId;
use App\Tenant\Domain\Event\FeatureFlagConfigurationUpdated;
use App\Tenant\Domain\Event\FeatureFlagCreated;
use App\Tenant\Domain\Event\FeatureFlagToggled;

final class FeatureFlag extends AggregateRoot
{
    /**
     * @param array<string, mixed> $configuration
     */
    private function __construct(
        private readonly FeatureFlagId $id,
        private readonly TenantId $tenantId,
        private readonly string $featureName,
        private bool $enabled,
        private array $configuration,
        private ?string $description,
        private readonly \DateTimeImmutable $createdAt,
        private ?\DateTimeImmutable $updatedAt,
    ) {
    }

    /**
     * @param array<string, mixed> $configuration
     */
    public static function create(
        FeatureFlagId $id,
        TenantId $tenantId,
        string $featureName,
        bool $enabled,
        array $configuration = [],
        ?string $description = null,
    ): self {
        if ('' === trim($featureName)) {
            throw new \InvalidArgumentException('Feature name cannot be empty');
        }

        $flag = new self(
            id: $id,
            tenantId: $tenantId,
            featureName: $featureName,
            enabled: $enabled,
            configuration: $configuration,
            description: $description,
            createdAt: new \DateTimeImmutable(),
            updatedAt: null,
        );

        $flag->recordEvent(new FeatureFlagCreated(
            flagId: $id,
            tenantId: $tenantId,
            featureName: $featureName,
            enabled: $enabled,
        ));

        return $flag;
    }

    /**
     * @param array<string, mixed> $configuration
     */
    public static function reconstituteFromPersistence(
        FeatureFlagId $id,
        TenantId $tenantId,
        string $featureName,
        bool $enabled,
        array $configuration,
        ?string $description,
        \DateTimeImmutable $createdAt,
        ?\DateTimeImmutable $updatedAt,
    ): self {
        return new self(
            id: $id,
            tenantId: $tenantId,
            featureName: $featureName,
            enabled: $enabled,
            configuration: $configuration,
            description: $description,
            createdAt: $createdAt,
            updatedAt: $updatedAt,
        );
    }

    public function enable(): void
    {
        if ($this->enabled) {
            throw new \DomainException(sprintf('Feature flag %s is already enabled', $this->featureName));
        }

        $this->enabled = true;
        $this->updatedAt = new \DateTimeImmutable();

        $this->recordEvent(new FeatureFlagToggled(
            flagId: $this->id,
            tenantId: $this->tenantId,
            featureName: $this->featureName,
            enabled: true,
        ));
    }

    public function disable(): void
    {
        if (!$this->enabled) {
            throw new \DomainException(sprintf('Feature flag %s is already disabled', $this->featureName));
        }

        $this->enabled = false;
        $this->updatedAt = new \DateTimeImmutable();

        $this->recordEvent(new FeatureFlagToggled(
            flagId: $this->id,
            tenantId: $this->tenantId,
            featureName: $this->featureName,
            enabled: false,
        ));
    }

    /**
     * @param array<string, mixed> $configuration
     */
    public function updateConfiguration(array $configuration): void
    {
        $this->configuration = $configuration;
        $this->updatedAt = new \DateTimeImmutable();

        $this->recordEvent(new FeatureFlagConfigurationUpdated(
            flagId: $this->id,
            tenantId: $this->tenantId,
            featureName: $this->featureName,
        ));
    }

    public function id(): FeatureFlagId
    {
        return $this->id;
    }

    public function tenantId(): TenantId
    {
        return $this->tenantId;
    }

    public function featureName(): string
    {
        return $this->featureName;
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    /**
     * @return array<string, mixed>
     */
    public function configuration(): array
    {
        return $this->configuration;
    }

    public function getConfigValue(string $key, mixed $default = null): mixed
    {
        return $this->configuration[$key] ?? $default;
    }

    public function description(): ?string
    {
        return $this->description;
    }

    public function createdAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function updatedAt(): ?\DateTimeImmutable
    {
        return $this->updatedAt;
    }
}