<?php

declare(strict_types=1);

namespace App\Pricing\Domain\Model;

use App\Pricing\Domain\Event\PromotionActivated;
use App\Pricing\Domain\Event\PromotionCreated;
use App\Pricing\Domain\Event\PromotionDeactivated;
use App\Pricing\Domain\Event\PromotionUpdated;
use App\Pricing\Domain\ValueObject\CouponCode;
use App\Pricing\Domain\ValueObject\Discount;
use App\Pricing\Domain\ValueObject\PromotionId;
use App\Pricing\Domain\ValueObject\PromotionType;
use App\Shared\Domain\Aggregate\AggregateRoot;
use App\Shared\Domain\ValueObject\TenantId;

/**
 * Promotion Aggregate Root.
 *
 * Business Rules:
 * - name: 3-100 characters
 * - priority: 1-1000 (higher = applied first)
 * - max_stacking: max 3 promotions can be stacked
 * - valid_from/valid_to: optional date range
 * - coupon_code: required for coupon type promotions
 * - conditions: JSON conditions for applying promotion (e.g., min_cart_value, product_ids)
 */
final class Promotion extends AggregateRoot
{
    private const MIN_NAME_LENGTH = 3;
    private const MAX_NAME_LENGTH = 100;
    private const MIN_PRIORITY = 1;
    private const MAX_PRIORITY = 1000;
    private const DEFAULT_PRIORITY = 100;

    private function __construct(
        private readonly PromotionId $id,
        private readonly TenantId $tenantId,
        private string $name,
        private readonly PromotionType $type,
        private Discount $discount,
        private int $priority,
        private bool $isActive,
        private ?CouponCode $couponCode,
        private array $conditions,
        private ?\DateTimeImmutable $validFrom,
        private ?\DateTimeImmutable $validTo,
        private readonly \DateTimeImmutable $createdAt,
        private \DateTimeImmutable $updatedAt
    ) {
        $this->validateName($this->name);
        $this->validatePriority($this->priority);
        $this->validateCouponCode($this->type, $this->couponCode);
        $this->validateDateRange($this->validFrom, $this->validTo);
    }

    public static function create(
        PromotionId $id,
        TenantId $tenantId,
        string $name,
        PromotionType $type,
        Discount $discount,
        int $priority = self::DEFAULT_PRIORITY,
        ?CouponCode $couponCode = null,
        array $conditions = [],
        ?\DateTimeImmutable $validFrom = null,
        ?\DateTimeImmutable $validTo = null
    ): self {
        $now = new \DateTimeImmutable();

        $promotion = new self(
            id: $id,
            tenantId: $tenantId,
            name: $name,
            type: $type,
            discount: $discount,
            priority: $priority,
            isActive: false,
            couponCode: $couponCode,
            conditions: $conditions,
            validFrom: $validFrom,
            validTo: $validTo,
            createdAt: $now,
            updatedAt: $now
        );

        $promotion->recordEvent(new PromotionCreated(
            promotionId: $id,
            tenantId: $tenantId,
            name: $name,
            type: $type,
            occurredAt: $now
        ));

        return $promotion;
    }

    public static function reconstituteFromPersistence(
        PromotionId $id,
        TenantId $tenantId,
        string $name,
        PromotionType $type,
        Discount $discount,
        int $priority,
        bool $isActive,
        ?CouponCode $couponCode,
        array $conditions,
        ?\DateTimeImmutable $validFrom,
        ?\DateTimeImmutable $validTo,
        \DateTimeImmutable $createdAt,
        \DateTimeImmutable $updatedAt
    ): self {
        return new self(
            id: $id,
            tenantId: $tenantId,
            name: $name,
            type: $type,
            discount: $discount,
            priority: $priority,
            isActive: $isActive,
            couponCode: $couponCode,
            conditions: $conditions,
            validFrom: $validFrom,
            validTo: $validTo,
            createdAt: $createdAt,
            updatedAt: $updatedAt
        );
    }

    public function update(
        string $name,
        Discount $discount,
        int $priority,
        array $conditions,
        ?\DateTimeImmutable $validFrom,
        ?\DateTimeImmutable $validTo
    ): void {
        $this->validateName($name);
        $this->validatePriority($priority);
        $this->validateDateRange($validFrom, $validTo);

        $this->name = $name;
        $this->discount = $discount;
        $this->priority = $priority;
        $this->conditions = $conditions;
        $this->validFrom = $validFrom;
        $this->validTo = $validTo;
        $this->updatedAt = new \DateTimeImmutable();

        $this->recordEvent(new PromotionUpdated(
            promotionId: $this->id,
            tenantId: $this->tenantId,
            occurredAt: $this->updatedAt
        ));
    }

    public function activate(): void
    {
        if ($this->isActive) {
            throw new \DomainException(sprintf('Promotion "%s" is already active', $this->id->toString()));
        }

        $this->isActive = true;
        $this->updatedAt = new \DateTimeImmutable();

        $this->recordEvent(new PromotionActivated(
            promotionId: $this->id,
            tenantId: $this->tenantId,
            occurredAt: $this->updatedAt
        ));
    }

    public function deactivate(): void
    {
        if (!$this->isActive) {
            throw new \DomainException(sprintf('Promotion "%s" is already inactive', $this->id->toString()));
        }

        $this->isActive = false;
        $this->updatedAt = new \DateTimeImmutable();

        $this->recordEvent(new PromotionDeactivated(
            promotionId: $this->id,
            tenantId: $this->tenantId,
            occurredAt: $this->updatedAt
        ));
    }

    public function isValidAt(\DateTimeImmutable $date): bool
    {
        if (null !== $this->validFrom && $date < $this->validFrom) {
            return false;
        }

        if (null !== $this->validTo && $date > $this->validTo) {
            return false;
        }

        return true;
    }

    public function isApplicable(\DateTimeImmutable $date): bool
    {
        return $this->isActive && $this->isValidAt($date);
    }

    public function requiresCoupon(): bool
    {
        return $this->type->isCoupon();
    }

    private function validateName(string $name): void
    {
        $length = mb_strlen($name);

        if ($length < self::MIN_NAME_LENGTH || $length > self::MAX_NAME_LENGTH) {
            throw new \InvalidArgumentException(sprintf('Promotion name must be between %d and %d characters, got: %d', self::MIN_NAME_LENGTH, self::MAX_NAME_LENGTH, $length));
        }
    }

    private function validatePriority(int $priority): void
    {
        if ($priority < self::MIN_PRIORITY || $priority > self::MAX_PRIORITY) {
            throw new \InvalidArgumentException(sprintf('Promotion priority must be between %d and %d, got: %d', self::MIN_PRIORITY, self::MAX_PRIORITY, $priority));
        }
    }

    private function validateCouponCode(PromotionType $type, ?CouponCode $couponCode): void
    {
        if ($type->isCoupon() && null === $couponCode) {
            throw new \InvalidArgumentException('Coupon code is required for coupon type promotions');
        }

        if (!$type->isCoupon() && null !== $couponCode) {
            throw new \InvalidArgumentException('Coupon code should not be provided for non-coupon promotions');
        }
    }

    private function validateDateRange(?\DateTimeImmutable $validFrom, ?\DateTimeImmutable $validTo): void
    {
        if (null !== $validFrom && null !== $validTo && $validFrom >= $validTo) {
            throw new \InvalidArgumentException('validFrom must be before validTo');
        }
    }

    // Getters
    public function id(): PromotionId
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

    public function type(): PromotionType
    {
        return $this->type;
    }

    public function discount(): Discount
    {
        return $this->discount;
    }

    public function priority(): int
    {
        return $this->priority;
    }

    public function isActive(): bool
    {
        return $this->isActive;
    }

    public function couponCode(): ?CouponCode
    {
        return $this->couponCode;
    }

    public function conditions(): array
    {
        return $this->conditions;
    }

    public function validFrom(): ?\DateTimeImmutable
    {
        return $this->validFrom;
    }

    public function validTo(): ?\DateTimeImmutable
    {
        return $this->validTo;
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
