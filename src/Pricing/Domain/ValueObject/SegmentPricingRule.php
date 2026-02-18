<?php

declare(strict_types=1);

namespace App\Pricing\Domain\ValueObject;

use App\Customer\Domain\ValueObject\CustomerSegment;

/**
 * Segment Pricing Rule Value Object.
 *
 * Represents a pricing rule that applies to a specific customer segment.
 *
 * Business Rules:
 * - segment: REGULAR, VIP, WHOLESALE, PREMIUM
 * - discount: percentage or fixed amount
 * - priority: higher priority rules apply first when multiple rules match
 * - VIP customers: 10-20% better prices than REGULAR
 * - WHOLESALE customers: volume-based bulk pricing
 * - PREMIUM customers: exclusive access to special promotions
 */
final readonly class SegmentPricingRule
{
    private function __construct(
        private CustomerSegment $segment,
        private Discount $discount,
        private int $priority = 100,
    ) {
        $this->validatePriority();
    }

    public static function create(
        CustomerSegment $segment,
        Discount $discount,
        int $priority = 100,
    ): self {
        return new self($segment, $discount, $priority);
    }

    public static function fromArray(array $data): self
    {
        return new self(
            CustomerSegment::fromString($data['segment']),
            Discount::fromTypeAndValue($data['discount_type'], $data['discount_value']),
            $data['priority'] ?? 100
        );
    }

    public function toArray(): array
    {
        return [
            'segment' => $this->segment->toString(),
            'discount_type' => $this->discount->type()->toString(),
            'discount_value' => $this->discount->value(),
            'priority' => $this->priority,
        ];
    }

    private function validatePriority(): void
    {
        if ($this->priority < 0 || $this->priority > 1000) {
            throw new \InvalidArgumentException(sprintf('Priority must be between 0 and 1000, got %d', $this->priority));
        }
    }

    public function segment(): CustomerSegment
    {
        return $this->segment;
    }

    public function discount(): Discount
    {
        return $this->discount;
    }

    public function priority(): int
    {
        return $this->priority;
    }

    public function appliesTo(CustomerSegment $customerSegment): bool
    {
        return $this->segment->equals($customerSegment);
    }

    public function equals(self $other): bool
    {
        return $this->segment->equals($other->segment)
            && $this->discount->equals($other->discount)
            && $this->priority === $other->priority;
    }
}
