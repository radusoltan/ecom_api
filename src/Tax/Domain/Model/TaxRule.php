<?php

declare(strict_types=1);

namespace App\Tax\Domain\Model;

use App\Shared\Domain\Aggregate\AggregateRoot;
use App\Shared\Domain\ValueObject\TenantId;
use App\Tax\Domain\Event\TaxRateChanged;
use App\Tax\Domain\Event\TaxRuleActivated;
use App\Tax\Domain\Event\TaxRuleCreated;
use App\Tax\Domain\Event\TaxRuleDeactivated;

/**
 * TaxRule Aggregate Root.
 *
 * Defines how tax is calculated for a specific jurisdiction and category.
 * Each rule belongs to a tenant (multi-tenancy) and can be time-limited.
 *
 * Business Rules:
 * - A TaxRule defines how tax is calculated for a specific jurisdiction and category
 * - Each rule belongs to a tenant (multi-tenancy)
 * - Rules have priority for conflict resolution (higher priority wins)
 * - Rules can be activated/deactivated
 * - Start and end dates for time-limited tax rules (e.g., COVID relief periods)
 * - B2B rules can specify reverse charge mechanism for EU VAT
 * - Priority must be >= 0 (default 0)
 * - validFrom must be <= validTo (if validTo is set)
 * - Cannot change rate on inactive rule
 * - Name must not be empty
 *
 * Examples:
 * - Standard VAT DE: 19% standard rate for Germany
 * - Reduced VAT FR: 5.5% reduced rate for France (books, food)
 * - COVID Relief DE: 16% temporary standard rate (Jul-Dec 2020)
 * - B2B Reverse Charge: 0% with reverse charge for EU B2B transactions
 */
final class TaxRule extends AggregateRoot
{
    private TaxRuleId $id;
    private TenantId $tenantId;
    private TaxJurisdiction $jurisdiction;
    private TaxCategory $category;
    private TaxRate $rate;
    private string $name;
    private ?string $description = null;
    private int $priority = 0;
    private bool $isActive = true;
    private \DateTimeImmutable $validFrom;
    private ?\DateTimeImmutable $validTo = null;
    private bool $isReverseCharge = false;
    private \DateTimeImmutable $createdAt;
    private \DateTimeImmutable $updatedAt;

    private function __construct()
    {
    }

    public static function create(
        TaxRuleId $id,
        TenantId $tenantId,
        TaxJurisdiction $jurisdiction,
        TaxCategory $category,
        TaxRate $rate,
        string $name,
        ?string $description = null,
        int $priority = 0,
        bool $isActive = true,
        ?\DateTimeImmutable $validFrom = null,
        ?\DateTimeImmutable $validTo = null,
        bool $isReverseCharge = false,
    ): self {
        // Validate name
        $trimmedName = trim($name);
        if (empty($trimmedName)) {
            throw new \InvalidArgumentException('Tax rule name cannot be empty');
        }

        // Validate priority
        if ($priority < 0) {
            throw new \InvalidArgumentException(sprintf('Priority must be >= 0, got %d', $priority));
        }

        // Validate date range
        $from = $validFrom ?? new \DateTimeImmutable();
        if (null !== $validTo && $from > $validTo) {
            throw new \InvalidArgumentException(sprintf('validFrom (%s) must be <= validTo (%s)', $from->format('Y-m-d H:i:s'), $validTo->format('Y-m-d H:i:s')));
        }

        // Validate reverse charge logic
        if ($isReverseCharge && !$jurisdiction->isEu()) {
            throw new \InvalidArgumentException('Reverse charge mechanism is only applicable to EU jurisdictions');
        }

        if ($isReverseCharge && TaxCategory::EXEMPT === $category) {
            throw new \InvalidArgumentException('Reverse charge cannot be applied to exempt category');
        }

        $rule = new self();
        $rule->id = $id;
        $rule->tenantId = $tenantId;
        $rule->jurisdiction = $jurisdiction;
        $rule->category = $category;
        $rule->rate = $rate;
        $rule->name = $trimmedName;
        $rule->description = $description;
        $rule->priority = $priority;
        $rule->isActive = $isActive;
        $rule->validFrom = $from;
        $rule->validTo = $validTo;
        $rule->isReverseCharge = $isReverseCharge;
        $rule->createdAt = new \DateTimeImmutable();
        $rule->updatedAt = new \DateTimeImmutable();

        $rule->recordEvent(new TaxRuleCreated(
            $rule->id,
            $rule->tenantId,
            $rule->jurisdiction,
            $rule->category,
            $rule->rate,
            $rule->name,
            $rule->priority,
            $rule->isActive
        ));

        return $rule;
    }

    public static function reconstituteFromPersistence(
        TaxRuleId $id,
        TenantId $tenantId,
        TaxJurisdiction $jurisdiction,
        TaxCategory $category,
        TaxRate $rate,
        string $name,
        ?string $description,
        int $priority,
        bool $isActive,
        \DateTimeImmutable $validFrom,
        ?\DateTimeImmutable $validTo,
        bool $isReverseCharge,
        \DateTimeImmutable $createdAt,
        \DateTimeImmutable $updatedAt,
    ): self {
        $rule = new self();
        $rule->id = $id;
        $rule->tenantId = $tenantId;
        $rule->jurisdiction = $jurisdiction;
        $rule->category = $category;
        $rule->rate = $rate;
        $rule->name = $name;
        $rule->description = $description;
        $rule->priority = $priority;
        $rule->isActive = $isActive;
        $rule->validFrom = $validFrom;
        $rule->validTo = $validTo;
        $rule->isReverseCharge = $isReverseCharge;
        $rule->createdAt = $createdAt;
        $rule->updatedAt = $updatedAt;

        return $rule;
    }

    public function activate(): void
    {
        if ($this->isActive) {
            throw new \InvalidArgumentException('Tax rule is already active');
        }

        $this->isActive = true;
        $this->updatedAt = new \DateTimeImmutable();

        $this->recordEvent(new TaxRuleActivated(
            $this->id,
            $this->tenantId,
            $this->jurisdiction,
            $this->category
        ));
    }

    public function deactivate(): void
    {
        if (!$this->isActive) {
            throw new \InvalidArgumentException('Tax rule is already inactive');
        }

        $this->isActive = false;
        $this->updatedAt = new \DateTimeImmutable();

        $this->recordEvent(new TaxRuleDeactivated(
            $this->id,
            $this->tenantId,
            $this->jurisdiction,
            $this->category
        ));
    }

    public function updateRate(TaxRate $rate): void
    {
        if (!$this->isActive) {
            throw new \InvalidArgumentException('Cannot change tax rate on inactive rule. Activate the rule first.');
        }

        if ($this->rate->equals($rate)) {
            return; // No change needed
        }

        $oldRate = $this->rate;
        $this->rate = $rate;
        $this->updatedAt = new \DateTimeImmutable();

        $this->recordEvent(new TaxRateChanged(
            $this->id,
            $this->tenantId,
            $this->jurisdiction,
            $this->category,
            $oldRate,
            $rate
        ));
    }

    public function updateValidity(
        \DateTimeImmutable $from,
        ?\DateTimeImmutable $to = null,
    ): void {
        if (null !== $to && $from > $to) {
            throw new \InvalidArgumentException(sprintf('validFrom (%s) must be <= validTo (%s)', $from->format('Y-m-d H:i:s'), $to->format('Y-m-d H:i:s')));
        }

        $this->validFrom = $from;
        $this->validTo = $to;
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function isValidAt(\DateTimeImmutable $date): bool
    {
        if (!$this->isActive) {
            return false;
        }

        // Check if date is after validFrom
        if ($date < $this->validFrom) {
            return false;
        }

        // Check if date is before validTo (if set)
        if (null !== $this->validTo && $date > $this->validTo) {
            return false;
        }

        return true;
    }

    public function calculateTax(int $amountInCents): int
    {
        return $this->rate->calculateTax($amountInCents);
    }

    public function appliesTo(
        TaxJurisdiction $jurisdiction,
        TaxCategory $category,
    ): bool {
        return $this->jurisdiction->equals($jurisdiction)
            && $this->category === $category;
    }

    public function id(): TaxRuleId
    {
        return $this->id;
    }

    public function tenantId(): TenantId
    {
        return $this->tenantId;
    }

    public function jurisdiction(): TaxJurisdiction
    {
        return $this->jurisdiction;
    }

    public function category(): TaxCategory
    {
        return $this->category;
    }

    public function rate(): TaxRate
    {
        return $this->rate;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function description(): ?string
    {
        return $this->description;
    }

    public function priority(): int
    {
        return $this->priority;
    }

    public function isActive(): bool
    {
        return $this->isActive;
    }

    public function validFrom(): \DateTimeImmutable
    {
        return $this->validFrom;
    }

    public function validTo(): ?\DateTimeImmutable
    {
        return $this->validTo;
    }

    public function isReverseCharge(): bool
    {
        return $this->isReverseCharge;
    }

    public function createdAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function updatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }
}
