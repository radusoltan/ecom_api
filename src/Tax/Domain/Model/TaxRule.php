<?php

declare(strict_types=1);

namespace App\Tax\Domain\Model;

use App\Shared\Domain\ValueObject\TenantId;
use App\Tax\Domain\Event\TaxRuleCreated;
use App\Tax\Domain\Event\TaxRuleDeactivated;
use App\Tax\Domain\Event\TaxRuleUpdated;
use App\Tax\Domain\ValueObject\TaxJurisdiction;
use App\Tax\Domain\ValueObject\TaxRate;
use App\Tax\Domain\ValueObject\TaxRuleId;

/**
 * Tax Rule Aggregate Root
 *
 * Represents a tax rule for a specific jurisdiction.
 * Supports multi-jurisdiction tax calculation with destination-based logic.
 *
 * Business Rules:
 * - Each tax rule applies to one jurisdiction (country or country+region)
 * - Tax rate must be between 0-100%
 * - Multiple tax rules can exist per tenant for different jurisdictions
 * - Tax rules can be active/inactive
 * - Tax name is for display purposes (e.g., "VAT", "Sales Tax", "GST")
 */
final class TaxRule
{
    private array $domainEvents = [];

    private function __construct(
        private TaxRuleId $id,
        private TenantId $tenantId,
        private string $name,
        private TaxJurisdiction $jurisdiction,
        private TaxRate $rate,
        private bool $isActive,
        private \DateTimeImmutable $createdAt,
        private \DateTimeImmutable $updatedAt
    ) {
    }

    /**
     * Create new tax rule
     */
    public static function create(
        TaxRuleId $id,
        TenantId $tenantId,
        string $name,
        TaxJurisdiction $jurisdiction,
        TaxRate $rate
    ): self {
        $name = trim($name);
        if (strlen($name) < 2 || strlen($name) > 100) {
            throw new \InvalidArgumentException('Tax rule name must be between 2 and 100 characters');
        }

        $now = new \DateTimeImmutable();

        $taxRule = new self(
            id: $id,
            tenantId: $tenantId,
            name: $name,
            jurisdiction: $jurisdiction,
            rate: $rate,
            isActive: true,
            createdAt: $now,
            updatedAt: $now
        );

        $taxRule->recordEvent(new TaxRuleCreated(
            taxRuleId: $id,
            tenantId: $tenantId,
            name: $name,
            jurisdiction: $jurisdiction,
            rate: $rate
        ));

        return $taxRule;
    }

    /**
     * Update tax rule details
     */
    public function update(
        string $name,
        TaxRate $rate
    ): void {
        $name = trim($name);
        if (strlen($name) < 2 || strlen($name) > 100) {
            throw new \InvalidArgumentException('Tax rule name must be between 2 and 100 characters');
        }

        $this->name = $name;
        $this->rate = $rate;
        $this->updatedAt = new \DateTimeImmutable();

        $this->recordEvent(new TaxRuleUpdated(
            taxRuleId: $this->id,
            tenantId: $this->tenantId,
            name: $this->name,
            rate: $this->rate
        ));
    }

    /**
     * Deactivate tax rule
     */
    public function deactivate(): void
    {
        if (!$this->isActive) {
            throw new \DomainException(
                sprintf('Tax rule "%s" is already inactive', $this->id->toString())
            );
        }

        $this->isActive = false;
        $this->updatedAt = new \DateTimeImmutable();

        $this->recordEvent(new TaxRuleDeactivated(
            taxRuleId: $this->id,
            tenantId: $this->tenantId
        ));
    }

    /**
     * Activate tax rule
     */
    public function activate(): void
    {
        if ($this->isActive) {
            throw new \DomainException(
                sprintf('Tax rule "%s" is already active', $this->id->toString())
            );
        }

        $this->isActive = true;
        $this->updatedAt = new \DateTimeImmutable();
    }

    /**
     * Check if this rule applies to given jurisdiction
     */
    public function appliesTo(TaxJurisdiction $jurisdiction): bool
    {
        if (!$this->isActive) {
            return false;
        }

        return $this->jurisdiction->matches($jurisdiction);
    }

    /**
     * Calculate tax amount for given price
     */
    public function calculateTax(int $priceInCents): int
    {
        return $this->rate->calculateTaxAmount($priceInCents);
    }

    /**
     * Reconstitute from persistence
     */
    public static function reconstituteFromPersistence(
        TaxRuleId $id,
        TenantId $tenantId,
        string $name,
        TaxJurisdiction $jurisdiction,
        TaxRate $rate,
        bool $isActive,
        \DateTimeImmutable $createdAt,
        \DateTimeImmutable $updatedAt
    ): self {
        return new self(
            id: $id,
            tenantId: $tenantId,
            name: $name,
            jurisdiction: $jurisdiction,
            rate: $rate,
            isActive: $isActive,
            createdAt: $createdAt,
            updatedAt: $updatedAt
        );
    }

    // Getters

    public function id(): TaxRuleId
    {
        return $this->id;
    }

    public function tenantId(): TenantId
    {
        return $this->tenantId;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function jurisdiction(): TaxJurisdiction
    {
        return $this->jurisdiction;
    }

    public function rate(): TaxRate
    {
        return $this->rate;
    }

    public function isActive(): bool
    {
        return $this->isActive;
    }

    public function createdAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function updatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    // Domain events

    private function recordEvent(object $event): void
    {
        $this->domainEvents[] = $event;
    }

    public function pullDomainEvents(): array
    {
        $events = $this->domainEvents;
        $this->domainEvents = [];

        return $events;
    }
}
