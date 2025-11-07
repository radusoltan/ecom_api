<?php

declare(strict_types=1);

namespace App\Customer\Domain\Service;

use App\Customer\Domain\Model\Customer;
use App\Customer\Domain\ValueObject\CustomerSegment;
use App\Shared\Domain\ValueObject\Money;

/**
 * Customer Segmentation Service.
 *
 * Determines the appropriate customer segment based on business rules:
 * - VIP: total_spent > 1000 EUR OR loyalty_points > 500
 * - Wholesale: customer_type = business (future implementation)
 * - Regular: default segment
 */
final class CustomerSegmentationService
{
    /** VIP threshold: total spent exceeds 1000 EUR */
    private const VIP_TOTAL_SPENT_THRESHOLD = 100000; // in cents (1000 EUR)

    /** VIP threshold: loyalty points exceed 500 */
    private const VIP_LOYALTY_POINTS_THRESHOLD = 500;

    /**
     * Evaluate the appropriate segment for a customer based on their activity.
     *
     * @param Customer   $customer   The customer to evaluate
     * @param Money|null $totalSpent Total amount spent by customer (optional, calculated from orders)
     *
     * @return CustomerSegment The recommended segment
     */
    public function evaluateSegment(Customer $customer, ?Money $totalSpent = null): CustomerSegment
    {
        // Rule 1: If total spent > 1000 EUR, assign VIP
        if (null !== $totalSpent && $totalSpent->getAmount() > self::VIP_TOTAL_SPENT_THRESHOLD) {
            return CustomerSegment::vip();
        }

        // Rule 2: If loyalty points > 500, assign VIP
        if ($customer->loyaltyPoints() > self::VIP_LOYALTY_POINTS_THRESHOLD) {
            return CustomerSegment::vip();
        }

        // Rule 3: If customer type is business, assign Wholesale
        // TODO: Implement when customer_type field is added to Customer aggregate
        // if ($customer->customerType() === 'business') {
        //     return CustomerSegment::wholesale();
        // }

        // Default: Regular segment
        return CustomerSegment::regular();
    }

    /**
     * Check if a customer should be upgraded to a different segment.
     *
     * @param Customer   $customer   The customer to check
     * @param Money|null $totalSpent Total amount spent (optional)
     *
     * @return bool True if segment should change
     */
    public function shouldChangeSegment(Customer $customer, ?Money $totalSpent = null): bool
    {
        $currentSegment = $customer->segment();
        $recommendedSegment = $this->evaluateSegment($customer, $totalSpent);

        return !$currentSegment->equals($recommendedSegment);
    }

    /**
     * Get the recommended segment for a customer.
     *
     * @param Customer   $customer   The customer
     * @param Money|null $totalSpent Total amount spent (optional)
     *
     * @return CustomerSegment The recommended segment (may be same as current)
     */
    public function getRecommendedSegment(Customer $customer, ?Money $totalSpent = null): CustomerSegment
    {
        return $this->evaluateSegment($customer, $totalSpent);
    }

    /**
     * Get VIP total spent threshold in cents.
     */
    public function getVipTotalSpentThreshold(): int
    {
        return self::VIP_TOTAL_SPENT_THRESHOLD;
    }

    /**
     * Get VIP loyalty points threshold.
     */
    public function getVipLoyaltyPointsThreshold(): int
    {
        return self::VIP_LOYALTY_POINTS_THRESHOLD;
    }
}
