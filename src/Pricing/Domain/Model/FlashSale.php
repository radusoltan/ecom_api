<?php

declare(strict_types=1);

namespace App\Pricing\Domain\Model;

use App\Catalog\Domain\Model\ProductId;
use App\Pricing\Domain\Event\FlashSaleActivated;
use App\Pricing\Domain\Event\FlashSaleCancelled;
use App\Pricing\Domain\Event\FlashSaleEnded;
use App\Pricing\Domain\Event\FlashSaleScheduled;
use App\Shared\Domain\Aggregate\AggregateRoot;
use App\Shared\Domain\ValueObject\TenantId;

/**
 * FlashSale Aggregate Root.
 *
 * Represents a time-limited special pricing event for specific products.
 *
 * Business Rules:
 * - name: 3-100 characters
 * - duration: minimum 1 hour, maximum 7 days (168 hours)
 * - discount: must be valid Discount value object
 * - products: at least 1 product required
 * - status transitions: scheduled -> active -> ended, or scheduled -> cancelled
 * - only scheduled flash sales can be cancelled
 * - end time must be after start time
 * - flash sale discount overrides regular price list prices
 * - products can only be in one active flash sale at a time (enforced at application layer)
 */
final class FlashSale extends AggregateRoot
{
    private const MIN_NAME_LENGTH = 3;
    private const MAX_NAME_LENGTH = 100;
    private const MIN_DURATION_HOURS = 1;
    private const MAX_DURATION_HOURS = 168; // 7 days

    /**
     * @param array<ProductId> $productIds
     */
    private function __construct(
        private readonly FlashSaleId $id,
        private readonly TenantId $tenantId,
        private string $name,
        private array $productIds,
        private Discount $discount,
        private \DateTimeImmutable $startTime,
        private \DateTimeImmutable $endTime,
        private FlashSaleStatus $status,
        private readonly \DateTimeImmutable $createdAt,
        private \DateTimeImmutable $updatedAt,
    ) {
        $this->validateName($this->name);
        $this->validateProductIds($this->productIds);
        $this->validateTimeRange($this->startTime, $this->endTime);
    }

    /**
     * @param array<ProductId> $productIds
     */
    public static function create(
        FlashSaleId $id,
        TenantId $tenantId,
        string $name,
        array $productIds,
        Discount $discount,
        \DateTimeImmutable $startTime,
        \DateTimeImmutable $endTime,
    ): self {
        $now = new \DateTimeImmutable();

        $flashSale = new self(
            id: $id,
            tenantId: $tenantId,
            name: $name,
            productIds: $productIds,
            discount: $discount,
            startTime: $startTime,
            endTime: $endTime,
            status: FlashSaleStatus::SCHEDULED,
            createdAt: $now,
            updatedAt: $now
        );

        $flashSale->recordEvent(new FlashSaleScheduled(
            flashSaleId: $id,
            tenantId: $tenantId,
            name: $name,
            productIds: $productIds,
            startTime: $startTime,
            endTime: $endTime,
            occurredAt: $now
        ));

        return $flashSale;
    }

    /**
     * @param array<ProductId> $productIds
     */
    public static function reconstituteFromPersistence(
        FlashSaleId $id,
        TenantId $tenantId,
        string $name,
        array $productIds,
        Discount $discount,
        \DateTimeImmutable $startTime,
        \DateTimeImmutable $endTime,
        FlashSaleStatus $status,
        \DateTimeImmutable $createdAt,
        \DateTimeImmutable $updatedAt,
    ): self {
        return new self(
            id: $id,
            tenantId: $tenantId,
            name: $name,
            productIds: $productIds,
            discount: $discount,
            startTime: $startTime,
            endTime: $endTime,
            status: $status,
            createdAt: $createdAt,
            updatedAt: $updatedAt
        );
    }

    public function activate(): void
    {
        if (!$this->status->canBeActivated()) {
            throw new \DomainException(sprintf('Cannot activate flash sale in status: %s', $this->status->value));
        }

        $this->status = FlashSaleStatus::ACTIVE;
        $this->updatedAt = new \DateTimeImmutable();

        $this->recordEvent(new FlashSaleActivated(
            flashSaleId: $this->id,
            tenantId: $this->tenantId,
            name: $this->name,
            productIds: $this->productIds,
            occurredAt: $this->updatedAt
        ));
    }

    public function end(): void
    {
        if (!$this->status->canBeEnded()) {
            throw new \DomainException(sprintf('Cannot end flash sale in status: %s', $this->status->value));
        }

        $this->status = FlashSaleStatus::ENDED;
        $this->updatedAt = new \DateTimeImmutable();

        $this->recordEvent(new FlashSaleEnded(
            flashSaleId: $this->id,
            tenantId: $this->tenantId,
            occurredAt: $this->updatedAt
        ));
    }

    public function cancel(): void
    {
        if (!$this->status->canBeCancelled()) {
            throw new \DomainException(sprintf('Cannot cancel flash sale in status: %s. Only scheduled flash sales can be cancelled.', $this->status->value));
        }

        $this->status = FlashSaleStatus::CANCELLED;
        $this->updatedAt = new \DateTimeImmutable();

        $this->recordEvent(new FlashSaleCancelled(
            flashSaleId: $this->id,
            tenantId: $this->tenantId,
            occurredAt: $this->updatedAt
        ));
    }

    public function isActive(): bool
    {
        return $this->status->isActive();
    }

    public function isScheduled(): bool
    {
        return $this->status->isScheduled();
    }

    public function isEnded(): bool
    {
        return $this->status->isEnded();
    }

    public function isCancelled(): bool
    {
        return $this->status->isCancelled();
    }

    public function isActiveAt(\DateTimeImmutable $dateTime): bool
    {
        return $this->status->isActive()
            && $dateTime >= $this->startTime
            && $dateTime <= $this->endTime;
    }

    public function containsProduct(ProductId $productId): bool
    {
        foreach ($this->productIds as $id) {
            if ($id->equals($productId)) {
                return true;
            }
        }

        return false;
    }

    public function getDurationInHours(): int
    {
        $interval = $this->startTime->diff($this->endTime);

        return (int) ($interval->days * 24 + $interval->h);
    }

    private function validateName(string $name): void
    {
        $length = mb_strlen($name);

        if ($length < self::MIN_NAME_LENGTH || $length > self::MAX_NAME_LENGTH) {
            throw new \InvalidArgumentException(sprintf('Flash sale name must be between %d and %d characters, got: %d', self::MIN_NAME_LENGTH, self::MAX_NAME_LENGTH, $length));
        }
    }

    /**
     * @param array<ProductId> $productIds
     */
    private function validateProductIds(array $productIds): void
    {
        if (empty($productIds)) {
            throw new \InvalidArgumentException('Flash sale must have at least one product');
        }

        foreach ($productIds as $productId) {
            if (!$productId instanceof ProductId) {
                throw new \InvalidArgumentException('All product IDs must be instances of ProductId');
            }
        }
    }

    private function validateTimeRange(\DateTimeImmutable $startTime, \DateTimeImmutable $endTime): void
    {
        if ($endTime <= $startTime) {
            throw new \InvalidArgumentException('Flash sale end time must be after start time');
        }

        $interval = $startTime->diff($endTime);
        $durationHours = $interval->days * 24 + $interval->h;

        if ($durationHours < self::MIN_DURATION_HOURS) {
            throw new \InvalidArgumentException(sprintf('Flash sale duration must be at least %d hour(s), got: %d hour(s)', self::MIN_DURATION_HOURS, $durationHours));
        }

        if ($durationHours > self::MAX_DURATION_HOURS) {
            throw new \InvalidArgumentException(sprintf('Flash sale duration must not exceed %d hours (7 days), got: %d hours', self::MAX_DURATION_HOURS, $durationHours));
        }
    }

    // Getters
    public function id(): FlashSaleId
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

    /**
     * @return array<ProductId>
     */
    public function productIds(): array
    {
        return $this->productIds;
    }

    public function discount(): Discount
    {
        return $this->discount;
    }

    public function startTime(): \DateTimeImmutable
    {
        return $this->startTime;
    }

    public function endTime(): \DateTimeImmutable
    {
        return $this->endTime;
    }

    public function status(): FlashSaleStatus
    {
        return $this->status;
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
