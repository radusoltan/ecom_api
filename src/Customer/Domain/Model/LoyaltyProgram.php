<?php

declare(strict_types=1);

namespace App\Customer\Domain\Model;

use App\Customer\Domain\Event\LoyaltyProgramActivated;
use App\Customer\Domain\Event\LoyaltyProgramCreated;
use App\Customer\Domain\Event\LoyaltyProgramDeactivated;
use App\Customer\Domain\Event\LoyaltyProgramUpdated;
use App\Customer\Domain\Event\LoyaltyTierAdded;
use App\Customer\Domain\Event\LoyaltyTierRemoved;
use App\Customer\Domain\Event\LoyaltyTierUpdated;
use App\Customer\Domain\ValueObject\EarningRate;
use App\Customer\Domain\ValueObject\LoyaltyProgramId;
use App\Customer\Domain\ValueObject\LoyaltyTierId;
use App\Customer\Domain\ValueObject\RedemptionRule;
use App\Shared\Domain\Aggregate\AggregateRoot;
use App\Shared\Domain\ValueObject\Money;
use App\Shared\Domain\ValueObject\TenantId;

/**
 * Loyalty Program Aggregate Root.
 *
 * Business Rules:
 * - One loyalty program per tenant (enforced at repository level)
 * - Name must be between 3 and 100 characters
 * - Description max 500 characters (optional)
 * - Earning rate must be > 0 and <= 100
 * - Min order value must be >= 0
 * - Validity days can be null (points never expire)
 * - Tiers must be sorted by threshold ascending
 * - Cannot have duplicate tier names
 * - Cannot have duplicate tier thresholds
 * - Program must be active for customers to earn/redeem points
 */
final class LoyaltyProgram extends AggregateRoot
{
    private LoyaltyProgramId $id;
    private TenantId $tenantId;
    private string $name;
    private ?string $description;
    private EarningRate $earningRate;
    private Money $minOrderValue;
    private RedemptionRule $redemptionRule;
    private ?int $validityDays;
    private bool $isActive;
    /** @var array<string, LoyaltyTier> */
    private array $tiers = [];
    private \DateTimeImmutable $createdAt;
    private \DateTimeImmutable $updatedAt;

    private function __construct()
    {
    }

    public static function create(
        TenantId $tenantId,
        string $name,
        EarningRate $earningRate,
        Money $minOrderValue,
        RedemptionRule $redemptionRule,
    ): self {
        self::validateName($name);

        $program = new self();
        $program->id = LoyaltyProgramId::generate();
        $program->tenantId = $tenantId;
        $program->name = trim($name);
        $program->description = null;
        $program->earningRate = $earningRate;
        $program->minOrderValue = $minOrderValue;
        $program->redemptionRule = $redemptionRule;
        $program->validityDays = null;
        $program->isActive = true;
        $program->createdAt = new \DateTimeImmutable();
        $program->updatedAt = new \DateTimeImmutable();

        $program->recordEvent(new LoyaltyProgramCreated(
            $program->id,
            $program->tenantId,
            $program->name,
            $program->earningRate,
            new \DateTimeImmutable()
        ));

        return $program;
    }

    /**
     * @param array<LoyaltyTier> $tiers
     */
    public static function reconstituteFromPersistence(
        LoyaltyProgramId $id,
        TenantId $tenantId,
        string $name,
        ?string $description,
        EarningRate $earningRate,
        Money $minOrderValue,
        RedemptionRule $redemptionRule,
        ?int $validityDays,
        bool $isActive,
        \DateTimeImmutable $createdAt,
        \DateTimeImmutable $updatedAt,
        array $tiers = [],
    ): self {
        $program = new self();
        $program->id = $id;
        $program->tenantId = $tenantId;
        $program->name = $name;
        $program->description = $description;
        $program->earningRate = $earningRate;
        $program->minOrderValue = $minOrderValue;
        $program->redemptionRule = $redemptionRule;
        $program->validityDays = $validityDays;
        $program->isActive = $isActive;
        $program->createdAt = $createdAt;
        $program->updatedAt = $updatedAt;

        // Index tiers by ID
        foreach ($tiers as $tier) {
            $program->tiers[$tier->id()->toString()] = $tier;
        }

        return $program;
    }

    public function update(
        string $name,
        ?string $description,
        EarningRate $earningRate,
        Money $minOrderValue,
        ?int $validityDays,
    ): void {
        self::validateName($name);

        if (null !== $description) {
            self::validateDescription($description);
        }

        if (null !== $validityDays && $validityDays <= 0) {
            throw new \InvalidArgumentException('Validity days must be greater than 0 or null');
        }

        $changes = [];

        if ($this->name !== trim($name)) {
            $changes['name'] = ['old' => $this->name, 'new' => trim($name)];
            $this->name = trim($name);
        }

        if ($this->description !== $description) {
            $changes['description'] = ['old' => $this->description, 'new' => $description];
            $this->description = $description;
        }

        if (!$this->earningRate->equals($earningRate)) {
            $changes['earningRate'] = ['old' => $this->earningRate->toFloat(), 'new' => $earningRate->toFloat()];
            $this->earningRate = $earningRate;
        }

        if (!$this->minOrderValue->equals($minOrderValue)) {
            $changes['minOrderValue'] = ['old' => $this->minOrderValue->toArray(), 'new' => $minOrderValue->toArray()];
            $this->minOrderValue = $minOrderValue;
        }

        if ($this->validityDays !== $validityDays) {
            $changes['validityDays'] = ['old' => $this->validityDays, 'new' => $validityDays];
            $this->validityDays = $validityDays;
        }

        if (!empty($changes)) {
            $this->updatedAt = new \DateTimeImmutable();
            $this->recordEvent(new LoyaltyProgramUpdated(
                $this->id,
                $this->tenantId,
                $changes,
                new \DateTimeImmutable()
            ));
        }
    }

    public function setRedemptionRule(RedemptionRule $rule): void
    {
        if (!$this->redemptionRule->equals($rule)) {
            $this->redemptionRule = $rule;
            $this->updatedAt = new \DateTimeImmutable();

            $this->recordEvent(new LoyaltyProgramUpdated(
                $this->id,
                $this->tenantId,
                ['redemptionRule' => ['old' => $this->redemptionRule->toArray(), 'new' => $rule->toArray()]],
                new \DateTimeImmutable()
            ));
        }
    }

    public function activate(): void
    {
        if ($this->isActive) {
            throw new \DomainException('Loyalty program is already active');
        }

        $this->isActive = true;
        $this->updatedAt = new \DateTimeImmutable();

        $this->recordEvent(new LoyaltyProgramActivated(
            $this->id,
            $this->tenantId,
            new \DateTimeImmutable()
        ));
    }

    public function deactivate(): void
    {
        if (!$this->isActive) {
            throw new \DomainException('Loyalty program is already inactive');
        }

        $this->isActive = false;
        $this->updatedAt = new \DateTimeImmutable();

        $this->recordEvent(new LoyaltyProgramDeactivated(
            $this->id,
            $this->tenantId,
            new \DateTimeImmutable()
        ));
    }

    public function addTier(LoyaltyTier $tier): void
    {
        $tierId = $tier->id()->toString();

        if (isset($this->tiers[$tierId])) {
            throw new \DomainException(sprintf('Tier with ID "%s" already exists', $tierId));
        }

        // Check for duplicate tier name
        foreach ($this->tiers as $existingTier) {
            if (0 === strcasecmp($existingTier->name(), $tier->name())) {
                throw new \DomainException(sprintf('Tier with name "%s" already exists', $tier->name()));
            }
        }

        // Check for duplicate threshold
        foreach ($this->tiers as $existingTier) {
            if ($existingTier->threshold() === $tier->threshold()) {
                throw new \DomainException(sprintf('Tier with threshold %d already exists', $tier->threshold()));
            }
        }

        $this->tiers[$tierId] = $tier;
        $this->updatedAt = new \DateTimeImmutable();

        $this->recordEvent(new LoyaltyTierAdded(
            $this->id,
            $tier->id(),
            $tier->name(),
            $tier->threshold(),
            new \DateTimeImmutable()
        ));
    }

    public function updateTier(
        LoyaltyTierId $id,
        string $name,
        int $threshold,
        int $discountPercentage,
        ?Money $freeShippingMinOrder,
        int $sortOrder,
    ): void {
        $tierId = $id->toString();

        if (!isset($this->tiers[$tierId])) {
            throw new \DomainException(sprintf('Tier with ID "%s" not found', $tierId));
        }

        // Check for duplicate tier name (excluding current tier)
        foreach ($this->tiers as $existingTier) {
            if (!$existingTier->id()->equals($id) && 0 === strcasecmp($existingTier->name(), trim($name))) {
                throw new \DomainException(sprintf('Tier with name "%s" already exists', trim($name)));
            }
        }

        // Check for duplicate threshold (excluding current tier)
        foreach ($this->tiers as $existingTier) {
            if (!$existingTier->id()->equals($id) && $existingTier->threshold() === $threshold) {
                throw new \DomainException(sprintf('Tier with threshold %d already exists', $threshold));
            }
        }

        $oldTier = $this->tiers[$tierId];
        $newTier = LoyaltyTier::create($id, $name, $threshold, $discountPercentage, $freeShippingMinOrder, $sortOrder);

        $this->tiers[$tierId] = $newTier;
        $this->updatedAt = new \DateTimeImmutable();

        $changes = [];
        if ($oldTier->name() !== $newTier->name()) {
            $changes['name'] = ['old' => $oldTier->name(), 'new' => $newTier->name()];
        }
        if ($oldTier->threshold() !== $newTier->threshold()) {
            $changes['threshold'] = ['old' => $oldTier->threshold(), 'new' => $newTier->threshold()];
        }
        if ($oldTier->discountPercentage() !== $newTier->discountPercentage()) {
            $changes['discountPercentage'] = ['old' => $oldTier->discountPercentage(), 'new' => $newTier->discountPercentage()];
        }
        if ($oldTier->sortOrder() !== $newTier->sortOrder()) {
            $changes['sortOrder'] = ['old' => $oldTier->sortOrder(), 'new' => $newTier->sortOrder()];
        }

        $this->recordEvent(new LoyaltyTierUpdated(
            $this->id,
            $id,
            $changes,
            new \DateTimeImmutable()
        ));
    }

    public function removeTier(LoyaltyTierId $tierId): void
    {
        $tierIdString = $tierId->toString();

        if (!isset($this->tiers[$tierIdString])) {
            throw new \DomainException(sprintf('Tier with ID "%s" not found', $tierIdString));
        }

        unset($this->tiers[$tierIdString]);
        $this->updatedAt = new \DateTimeImmutable();

        $this->recordEvent(new LoyaltyTierRemoved(
            $this->id,
            $tierId,
            new \DateTimeImmutable()
        ));
    }

    public function calculatePointsForOrder(Money $orderTotal): int
    {
        if (!$this->isActive) {
            return 0;
        }

        if ($orderTotal->isLessThan($this->minOrderValue)) {
            return 0;
        }

        return $this->earningRate->calculatePoints($orderTotal);
    }

    public function calculateDiscountForPoints(int $points): Money
    {
        return $this->redemptionRule->calculateDiscount($points);
    }

    public function getTierForPoints(int $points): ?LoyaltyTier
    {
        if (empty($this->tiers)) {
            return null;
        }

        // Sort tiers by threshold descending to find highest qualifying tier
        $sortedTiers = $this->getTiers();
        usort($sortedTiers, fn (LoyaltyTier $a, LoyaltyTier $b) => $b->threshold() <=> $a->threshold());

        foreach ($sortedTiers as $tier) {
            if ($points >= $tier->threshold()) {
                return $tier;
            }
        }

        return null;
    }

    /**
     * @return array<LoyaltyTier>
     */
    public function getTiers(): array
    {
        $tiers = array_values($this->tiers);

        // Sort by sortOrder ascending, then by threshold ascending
        usort($tiers, function (LoyaltyTier $a, LoyaltyTier $b) {
            if ($a->sortOrder() !== $b->sortOrder()) {
                return $a->sortOrder() <=> $b->sortOrder();
            }

            return $a->threshold() <=> $b->threshold();
        });

        return $tiers;
    }

    private static function validateName(string $name): void
    {
        $trimmed = trim($name);
        $length = mb_strlen($trimmed);

        if ($length < 3 || $length > 100) {
            throw new \InvalidArgumentException(sprintf('Loyalty program name must be between 3 and 100 characters. Got: %d', $length));
        }
    }

    private static function validateDescription(string $description): void
    {
        $length = mb_strlen($description);

        if ($length > 500) {
            throw new \InvalidArgumentException(sprintf('Loyalty program description cannot exceed 500 characters. Got: %d', $length));
        }
    }

    // Getters
    public function id(): LoyaltyProgramId
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

    public function description(): ?string
    {
        return $this->description;
    }

    public function earningRate(): EarningRate
    {
        return $this->earningRate;
    }

    public function minOrderValue(): Money
    {
        return $this->minOrderValue;
    }

    public function redemptionRule(): RedemptionRule
    {
        return $this->redemptionRule;
    }

    public function validityDays(): ?int
    {
        return $this->validityDays;
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
