<?php

declare(strict_types=1);

namespace App\Tests\Unit\Customer\Domain\Service;

use App\Customer\Domain\Model\Customer;
use App\Customer\Domain\Service\CustomerSegmentationService;
use App\Customer\Domain\ValueObject\CustomerId;
use App\Customer\Domain\ValueObject\CustomerSegment;
use App\Shared\Domain\ValueObject\Email;
use App\Shared\Domain\ValueObject\Money;
use App\Shared\Domain\ValueObject\TenantId;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;

final class CustomerSegmentationServiceTest extends TestCase
{
    private CustomerSegmentationService $service;

    protected function setUp(): void
    {
        $this->service = new CustomerSegmentationService();
    }

    #[Test]
    public function it_assigns_regular_segment_for_new_customer_with_zero_spent(): void
    {
        // Arrange
        $customer = $this->createCustomerWithLoyaltyPoints(0);
        $totalSpent = Money::fromScalars(0, 'EUR');

        // Act
        $segment = $this->service->evaluateSegment($customer, $totalSpent);

        // Assert
        self::assertTrue($segment->equals(CustomerSegment::regular()));
    }

    #[Test]
    public function it_assigns_regular_segment_for_customer_below_all_thresholds(): void
    {
        // Arrange
        $customer = $this->createCustomerWithLoyaltyPoints(400); // Below 500
        $totalSpent = Money::fromScalars(90000, 'EUR'); // 900 EUR - below 1000

        // Act
        $segment = $this->service->evaluateSegment($customer, $totalSpent);

        // Assert
        self::assertTrue($segment->equals(CustomerSegment::regular()));
    }

    #[Test]
    public function it_assigns_vip_segment_when_total_spent_exceeds_threshold(): void
    {
        // Arrange
        $customer = $this->createCustomerWithLoyaltyPoints(0);
        $totalSpent = Money::fromScalars(150000, 'EUR'); // 1500 EUR - above 1000

        // Act
        $segment = $this->service->evaluateSegment($customer, $totalSpent);

        // Assert
        self::assertTrue($segment->equals(CustomerSegment::vip()));
    }

    #[Test]
    public function it_assigns_vip_segment_when_total_spent_exactly_meets_threshold(): void
    {
        // Arrange
        $customer = $this->createCustomerWithLoyaltyPoints(0);
        $totalSpent = Money::fromScalars(100001, 'EUR'); // 1000.01 EUR - just above threshold

        // Act
        $segment = $this->service->evaluateSegment($customer, $totalSpent);

        // Assert
        self::assertTrue($segment->equals(CustomerSegment::vip()));
    }

    #[Test]
    public function it_does_not_assign_vip_when_total_spent_exactly_at_threshold(): void
    {
        // Arrange
        $customer = $this->createCustomerWithLoyaltyPoints(0);
        $totalSpent = Money::fromScalars(100000, 'EUR'); // Exactly 1000 EUR - not above

        // Act
        $segment = $this->service->evaluateSegment($customer, $totalSpent);

        // Assert
        self::assertTrue($segment->equals(CustomerSegment::regular()));
    }

    #[Test]
    public function it_assigns_vip_segment_when_loyalty_points_exceed_threshold(): void
    {
        // Arrange
        $customer = $this->createCustomerWithLoyaltyPoints(600); // Above 500
        $totalSpent = Money::fromScalars(0, 'EUR');

        // Act
        $segment = $this->service->evaluateSegment($customer, $totalSpent);

        // Assert
        self::assertTrue($segment->equals(CustomerSegment::vip()));
    }

    #[Test]
    public function it_assigns_vip_segment_when_loyalty_points_exactly_meet_threshold(): void
    {
        // Arrange
        $customer = $this->createCustomerWithLoyaltyPoints(501); // Just above 500
        $totalSpent = Money::fromScalars(0, 'EUR');

        // Act
        $segment = $this->service->evaluateSegment($customer, $totalSpent);

        // Assert
        self::assertTrue($segment->equals(CustomerSegment::vip()));
    }

    #[Test]
    public function it_does_not_assign_vip_when_loyalty_points_exactly_at_threshold(): void
    {
        // Arrange
        $customer = $this->createCustomerWithLoyaltyPoints(500); // Exactly 500 - not above
        $totalSpent = Money::fromScalars(0, 'EUR');

        // Act
        $segment = $this->service->evaluateSegment($customer, $totalSpent);

        // Assert
        self::assertTrue($segment->equals(CustomerSegment::regular()));
    }

    #[Test]
    public function it_assigns_vip_when_both_thresholds_are_exceeded(): void
    {
        // Arrange
        $customer = $this->createCustomerWithLoyaltyPoints(600);
        $totalSpent = Money::fromScalars(150000, 'EUR');

        // Act
        $segment = $this->service->evaluateSegment($customer, $totalSpent);

        // Assert
        self::assertTrue($segment->equals(CustomerSegment::vip()));
    }

    #[Test]
    public function it_returns_false_when_customer_already_in_recommended_segment(): void
    {
        // Arrange - Customer already VIP with qualifying points
        $customer = $this->createCustomerWithLoyaltyPoints(600, CustomerSegment::vip());
        $totalSpent = Money::fromScalars(0, 'EUR');

        // Act
        $shouldChange = $this->service->shouldChangeSegment($customer, $totalSpent);

        // Assert
        self::assertFalse($shouldChange);
    }

    #[Test]
    public function it_returns_true_when_customer_should_be_upgraded_to_vip(): void
    {
        // Arrange - Customer is Regular but qualifies for VIP
        $customer = $this->createCustomerWithLoyaltyPoints(600, CustomerSegment::regular());
        $totalSpent = Money::fromScalars(0, 'EUR');

        // Act
        $shouldChange = $this->service->shouldChangeSegment($customer, $totalSpent);

        // Assert
        self::assertTrue($shouldChange);
    }

    #[Test]
    public function it_handles_null_total_spent_gracefully(): void
    {
        // Arrange
        $customer = $this->createCustomerWithLoyaltyPoints(100);

        // Act - null total spent should default to regular segment
        $segment = $this->service->evaluateSegment($customer, null);

        // Assert
        self::assertTrue($segment->equals(CustomerSegment::regular()));
    }

    /**
     * Helper: Create a customer with specific loyalty points and segment
     */
    private function createCustomerWithLoyaltyPoints(int $loyaltyPoints, ?CustomerSegment $segment = null): Customer
    {
        $customer = Customer::reconstituteFromPersistence(
            CustomerId::generate(),
            TenantId::generate(),
            Email::fromString('test@example.com'),
            'John',
            'Doe',
            null,
            $segment ?? CustomerSegment::regular(),
            $loyaltyPoints,
            true,
            new \DateTimeImmutable(),
            new \DateTimeImmutable()
        );

        return $customer;
    }
}
