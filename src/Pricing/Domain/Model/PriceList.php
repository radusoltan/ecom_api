<?php

declare(strict_types=1);

namespace App\Pricing\Domain\Model;

use App\Catalog\Domain\Model\ProductId;
use App\Customer\Domain\ValueObject\CustomerSegment;
use App\Pricing\Domain\Event\PriceListActivated;
use App\Pricing\Domain\Event\PriceListCreated;
use App\Pricing\Domain\Event\PriceListDeactivated;
use App\Pricing\Domain\Event\PricingRuleAdded;
use App\Pricing\Domain\Event\PricingRuleRemoved;
use App\Pricing\Domain\ValueObject\SegmentPricingRule;
use App\Shared\Domain\Aggregate\AggregateRoot;
use App\Shared\Domain\ValueObject\Money;
use App\Shared\Domain\ValueObject\TenantId;

/**
 * PriceList Aggregate Root.
 *
 * Manages dynamic pricing rules for products with priority and validity periods
 *
 * Business Rules:
 * - non_overlapping_rules_per_scope: true
 * - priority_order: higher priority takes precedence
 * - max_rules: 100
 * - valid_from < valid_to
 * - only_active_price_lists_apply: true
 * - segment_rules_priority: segment rules take precedence over general rules
 */
final class PriceList extends AggregateRoot
{
    private const MAX_RULES = 100;
    private const MAX_SEGMENT_RULES = 20;

    /**
     * @param array<PricingRule>        $rules
     * @param array<SegmentPricingRule> $segmentRules
     */
    private function __construct(
        private readonly PriceListId $id,
        private readonly TenantId $tenantId,
        private PriceListName $name,
        private int $priority,
        private array $rules,
        private array $segmentRules,
        private ?\DateTimeImmutable $validFrom,
        private ?\DateTimeImmutable $validTo,
        private bool $isActive,
        private readonly \DateTimeImmutable $createdAt,
        private \DateTimeImmutable $updatedAt,
    ) {
        $this->validatePriority();
        $this->validateDateRange();
        $this->validateRulesCount();
        $this->validateSegmentRulesCount();
    }

    /**
     * Create a new PriceList.
     */
    public static function create(
        PriceListId $id,
        TenantId $tenantId,
        PriceListName $name,
        int $priority = 100,
        ?\DateTimeImmutable $validFrom = null,
        ?\DateTimeImmutable $validTo = null,
    ): self {
        $now = new \DateTimeImmutable();

        $priceList = new self(
            $id,
            $tenantId,
            $name,
            $priority,
            [],
            [], // Empty segment rules
            $validFrom,
            $validTo,
            false, // Created as inactive
            $now,
            $now
        );

        $priceList->recordEvent(new PriceListCreated(
            $id->toString(),
            $tenantId->toString(),
            $name->value(),
            $priority,
            $now
        ));

        return $priceList;
    }

    /**
     * Reconstitute from persistence.
     *
     * @param array<PricingRule>        $rules
     * @param array<SegmentPricingRule> $segmentRules
     */
    public static function reconstituteFromPersistence(
        PriceListId $id,
        TenantId $tenantId,
        PriceListName $name,
        int $priority,
        array $rules,
        array $segmentRules,
        ?\DateTimeImmutable $validFrom,
        ?\DateTimeImmutable $validTo,
        bool $isActive,
        \DateTimeImmutable $createdAt,
        \DateTimeImmutable $updatedAt,
    ): self {
        return new self(
            $id,
            $tenantId,
            $name,
            $priority,
            $rules,
            $segmentRules,
            $validFrom,
            $validTo,
            $isActive,
            $createdAt,
            $updatedAt
        );
    }

    /**
     * Add a pricing rule to this price list.
     */
    public function addRule(PricingRule $rule): void
    {
        if (count($this->rules) >= self::MAX_RULES) {
            throw new \InvalidArgumentException(sprintf('Cannot add more than %d rules to a price list', self::MAX_RULES));
        }

        // Check for overlapping rules (same scope + same product/category)
        foreach ($this->rules as $existingRule) {
            if ($this->rulesOverlap($existingRule, $rule)) {
                throw new \InvalidArgumentException('Cannot add overlapping pricing rule');
            }
        }

        $this->rules[] = $rule;
        $this->updatedAt = new \DateTimeImmutable();

        $this->recordEvent(new PricingRuleAdded(
            $this->id->toString(),
            $rule->toArray()
        ));
    }

    /**
     * Remove a pricing rule by index.
     */
    public function removeRule(int $index): void
    {
        if (!isset($this->rules[$index])) {
            throw new \InvalidArgumentException(sprintf('Rule at index %d does not exist', $index));
        }

        $removedRule = $this->rules[$index];
        unset($this->rules[$index]);
        $this->rules = array_values($this->rules); // Re-index array
        $this->updatedAt = new \DateTimeImmutable();

        $this->recordEvent(new PricingRuleRemoved(
            $this->id->toString(),
            $removedRule->toArray()
        ));
    }

    /**
     * Add a segment-based pricing rule.
     *
     * Segment rules take priority over general rules when calculating prices.
     */
    public function addSegmentRule(SegmentPricingRule $rule): void
    {
        if (count($this->segmentRules) >= self::MAX_SEGMENT_RULES) {
            throw new \InvalidArgumentException(sprintf('Cannot add more than %d segment rules to a price list', self::MAX_SEGMENT_RULES));
        }

        // Check for duplicate segment rules (same segment)
        foreach ($this->segmentRules as $existingRule) {
            if ($existingRule->segment()->equals($rule->segment())) {
                throw new \InvalidArgumentException(sprintf('Segment rule for %s already exists', $rule->segment()->value()));
            }
        }

        $this->segmentRules[] = $rule;
        $this->updatedAt = new \DateTimeImmutable();
    }

    /**
     * Remove a segment pricing rule by segment.
     */
    public function removeSegmentRule(CustomerSegment $segment): void
    {
        $found = false;
        foreach ($this->segmentRules as $index => $rule) {
            if ($rule->segment()->equals($segment)) {
                unset($this->segmentRules[$index]);
                $found = true;
                break;
            }
        }

        if (!$found) {
            throw new \InvalidArgumentException(sprintf('Segment rule for %s does not exist', $segment->value()));
        }

        $this->segmentRules = array_values($this->segmentRules); // Re-index
        $this->updatedAt = new \DateTimeImmutable();
    }

    /**
     * Get applicable segment discount for a customer segment.
     *
     * Returns null if no segment rule applies.
     */
    public function getSegmentDiscount(CustomerSegment $segment): ?SegmentPricingRule
    {
        foreach ($this->segmentRules as $rule) {
            if ($rule->appliesTo($segment)) {
                return $rule;
            }
        }

        return null;
    }

    /**
     * Activate the price list.
     */
    public function activate(): void
    {
        if ($this->isActive) {
            throw new \InvalidArgumentException('PriceList is already active');
        }

        $this->isActive = true;
        $this->updatedAt = new \DateTimeImmutable();

        $this->recordEvent(new PriceListActivated(
            $this->id->toString(),
            $this->updatedAt
        ));
    }

    /**
     * Deactivate the price list.
     */
    public function deactivate(): void
    {
        if (!$this->isActive) {
            throw new \InvalidArgumentException('PriceList is already inactive');
        }

        $this->isActive = false;
        $this->updatedAt = new \DateTimeImmutable();

        $this->recordEvent(new PriceListDeactivated(
            $this->id->toString(),
            $this->updatedAt
        ));
    }

    /**
     * Update basic properties.
     */
    public function update(
        PriceListName $name,
        int $priority,
        ?\DateTimeImmutable $validFrom,
        ?\DateTimeImmutable $validTo,
    ): void {
        $this->name = $name;
        $this->priority = $priority;
        $this->validFrom = $validFrom;
        $this->validTo = $validTo;
        $this->updatedAt = new \DateTimeImmutable();

        $this->validatePriority();
        $this->validateDateRange();
    }

    /**
     * Calculate price for a product considering all applicable rules.
     *
     * Returns the original price if no rules apply
     */
    public function calculatePrice(
        Money $basePrice,
        ProductId $productId,
        ?string $categoryId,
        int $quantity = 1,
    ): Money {
        if (!$this->isValidNow()) {
            return $basePrice;
        }

        $applicableRules = [];
        $currentAmount = $basePrice->multiplyBy($quantity);

        // Find all applicable rules
        foreach ($this->rules as $rule) {
            if ($rule->appliesTo($productId, $categoryId, $quantity, $currentAmount)) {
                $applicableRules[] = $rule;
            }
        }

        if (empty($applicableRules)) {
            return $basePrice;
        }

        // Apply discounts (first applicable rule)
        // In future: could implement stacking logic here
        $finalPrice = $applicableRules[0]->getDiscount()->apply($basePrice);

        return $finalPrice;
    }

    /**
     * Check if this price list is currently valid.
     */
    public function isValidNow(): bool
    {
        if (!$this->isActive) {
            return false;
        }

        $now = new \DateTimeImmutable();

        if (null !== $this->validFrom && $now < $this->validFrom) {
            return false;
        }

        if (null !== $this->validTo && $now > $this->validTo) {
            return false;
        }

        return true;
    }

    /**
     * Check if two rules overlap (same scope + same target).
     */
    private function rulesOverlap(PricingRule $rule1, PricingRule $rule2): bool
    {
        if ($rule1->getScope() !== $rule2->getScope()) {
            return false;
        }

        if (PricingRule::SCOPE_PRODUCT === $rule1->getScope()) {
            return $rule1->getProductId()?->equals($rule2->getProductId()) ?? false;
        }

        if (PricingRule::SCOPE_CATEGORY === $rule1->getScope()) {
            return $rule1->getCategoryId() === $rule2->getCategoryId();
        }

        // SCOPE_ALL always overlaps with another SCOPE_ALL
        return PricingRule::SCOPE_ALL === $rule1->getScope();
    }

    private function validatePriority(): void
    {
        if ($this->priority < 0 || $this->priority > 1000) {
            throw new \InvalidArgumentException(sprintf('Priority must be between 0 and 1000, got %d', $this->priority));
        }
    }

    private function validateDateRange(): void
    {
        if (null !== $this->validFrom && null !== $this->validTo) {
            if ($this->validFrom >= $this->validTo) {
                throw new \InvalidArgumentException('validFrom must be before validTo');
            }
        }
    }

    private function validateRulesCount(): void
    {
        if (count($this->rules) > self::MAX_RULES) {
            throw new \InvalidArgumentException(sprintf('PriceList cannot have more than %d rules', self::MAX_RULES));
        }
    }

    private function validateSegmentRulesCount(): void
    {
        if (count($this->segmentRules) > self::MAX_SEGMENT_RULES) {
            throw new \InvalidArgumentException(sprintf('PriceList cannot have more than %d segment rules', self::MAX_SEGMENT_RULES));
        }
    }

    // Getters
    public function id(): PriceListId
    {
        return $this->id;
    }

    public function tenantId(): TenantId
    {
        return $this->tenantId;
    }

    public function name(): PriceListName
    {
        return $this->name;
    }

    public function priority(): int
    {
        return $this->priority;
    }

    /** @return array<PricingRule> */
    public function rules(): array
    {
        return $this->rules;
    }

    /** @return array<SegmentPricingRule> */
    public function segmentRules(): array
    {
        return $this->segmentRules;
    }

    public function validFrom(): ?\DateTimeImmutable
    {
        return $this->validFrom;
    }

    public function validTo(): ?\DateTimeImmutable
    {
        return $this->validTo;
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
}
