<?php

declare(strict_types=1);

namespace App\Pricing\Domain\Event;

use App\Shared\Domain\Event\DomainEvent;
use App\Shared\Domain\ValueObject\TenantId;

/**
 * Domain event triggered when multiple promotions are stacked.
 *
 * This event can be used for:
 * - Analytics: Track which promotions are frequently stacked together
 * - Reporting: Generate reports on discount effectiveness
 * - Notifications: Alert managers when high-value stacking occurs
 * - Fraud detection: Monitor unusual stacking patterns
 */
final readonly class DiscountsStacked implements DomainEvent
{
    /**
     * @param TenantId                                                                                                                                     $tenantId           Tenant context
     * @param array<int, array{promotionId: string, name: string, type: string, discountAmount: int, priceAfterDiscount: int}> $appliedPromotions  Array of applied promotion details
     * @param int                                                                                                                                          $originalAmount     Original price in minor units (cents)
     * @param int                                                                                                                                          $finalAmount        Final price after discounts in minor units
     * @param int                                                                                                                                          $totalDiscountAmount Total discount in minor units
     * @param string                                                                                                                                       $currency           Currency code (e.g., EUR, USD)
     */
    public function __construct(
        private TenantId $tenantId,
        private array $appliedPromotions,
        private int $originalAmount,
        private int $finalAmount,
        private int $totalDiscountAmount,
        private string $currency,
        private \DateTimeImmutable $occurredAt
    ) {
    }

    public function tenantId(): TenantId
    {
        return $this->tenantId;
    }

    /**
     * @return array<int, array{promotionId: string, name: string, type: string, discountAmount: int, priceAfterDiscount: int}>
     */
    public function appliedPromotions(): array
    {
        return $this->appliedPromotions;
    }

    public function originalAmount(): int
    {
        return $this->originalAmount;
    }

    public function finalAmount(): int
    {
        return $this->finalAmount;
    }

    public function totalDiscountAmount(): int
    {
        return $this->totalDiscountAmount;
    }

    public function currency(): string
    {
        return $this->currency;
    }

    public function occurredAt(): \DateTimeImmutable
    {
        return $this->occurredAt;
    }

    public function occurredOn(): \DateTimeImmutable
    {
        return $this->occurredAt;
    }

    public function getEventName(): string
    {
        return 'pricing.discounts.stacked';
    }

    /**
     * @return array{tenant_id: string, applied_promotions: array<int, array{promotionId: string, name: string, type: string, discountAmount: int, priceAfterDiscount: int}>, original_amount: int, final_amount: int, total_discount_amount: int, currency: string, occurred_at: string}
     */
    public function toArray(): array
    {
        return [
            'tenant_id' => $this->tenantId->toString(),
            'applied_promotions' => $this->appliedPromotions,
            'original_amount' => $this->originalAmount,
            'final_amount' => $this->finalAmount,
            'total_discount_amount' => $this->totalDiscountAmount,
            'currency' => $this->currency,
            'occurred_at' => $this->occurredAt->format(\DateTimeInterface::ATOM),
        ];
    }
}
